<?php
/**
 * Plugin Name: WP Database Sanity Check
 * Plugin URI: https://github.com/TABARC-Code/wp-database-sanity-check
 * Description: Audits core WordPress database tables for orphaned rows, broken relationships and quietly wrong counts. Read only. No fixes. I am not your YOLO button.
 * Version: 1.0.0.11
 * Author: TABARC-Code
 * Author URI: https://github.com/TABARC-Code
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Why this exists:
 * WordPress does not enforce referential integrity.
 * It never has.
 * Over time, rows accumulate that point at things which no longer exist.
 * Nothing crashes. Everything just gets heavier.
 *
 * This plugin is here to tell me what is broken, how much of it exists,
 * and how risky it would be to clean it up by hand.
 *
 * No automatic deletes. No "repair all". I like my sites alive.
 *
 * TODO: add optional WP-CLI command for audits.
 * TODO: add snapshot diff so I can see if things are getting worse.
 * FIXME: counts are estimates on very large tables. That is still useful.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_Database_Sanity_Check' ) ) {

    class WP_Database_Sanity_Check {

        private $screen_slug   = 'wp-database-sanity-check';
        private $export_action = 'wdsc_export_json';

        public function __construct() {
            add_action( 'admin_menu', array( $this, 'add_tools_page' ) );
            add_action( 'admin_post_' . $this->export_action, array( $this, 'handle_export_json' ) );
            add_action( 'admin_head-plugins.php', array( $this, 'inject_plugin_list_icon_css' ) );
        }

        private function get_brand_icon_url() {
            return plugin_dir_url( __FILE__ ) . '.branding/tabarc-icon.svg';
        }

        public function add_tools_page() {
            add_management_page(
                __( 'Database Sanity Check', 'wp-database-sanity-check' ),
                __( 'DB Sanity Check', 'wp-database-sanity-check' ),
                'manage_options',
                $this->screen_slug,
                array( $this, 'render_screen' )
            );
        }

        public function render_screen() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-database-sanity-check' ) );
            }

            $audit = $this->run_audit();

            $export_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=' . $this->export_action ),
                'wdsc_export_json'
            );
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'WP Database Sanity Check', 'wp-database-sanity-check' ); ?></h1>
                <p>
                    This is a structural audit of core WordPress tables.
                    It looks for rows that point at nothing, relationships that no longer make sense,
                    and counts that stopped matching reality years ago.
                </p>
                <p>
                    <a class="button button-secondary" href="<?php echo esc_url( $export_url ); ?>">
                        <?php esc_html_e( 'Export audit as JSON', 'wp-database-sanity-check' ); ?>
                    </a>
                </p>

                <h2><?php esc_html_e( 'Summary', 'wp-database-sanity-check' ); ?></h2>
                <?php $this->render_summary( $audit ); ?>

                <h2><?php esc_html_e( 'Post and postmeta integrity', 'wp-database-sanity-check' ); ?></h2>
                <?php $this->render_post_checks( $audit ); ?>

                <h2><?php esc_html_e( 'Comments and commentmeta integrity', 'wp-database-sanity-check' ); ?></h2>
                <?php $this->render_comment_checks( $audit ); ?>

                <h2><?php esc_html_e( 'Taxonomy integrity', 'wp-database-sanity-check' ); ?></h2>
                <?php $this->render_taxonomy_checks( $audit ); ?>

                <p style="font-size:12px;opacity:0.8;margin-top:2em;">
                    <?php esc_html_e( 'This plugin does not modify the database. Cleanup should be done carefully, with backups, preferably on staging.', 'wp-database-sanity-check' ); ?>
                </p>
            </div>
            <?php
        }

        public function inject_plugin_list_icon_css() {
            $icon_url = esc_url( $this->get_brand_icon_url() );
            ?>
            <style>
                .wp-list-table.plugins tr[data-slug="wp-database-sanity-check"] .plugin-title strong::before {
                    content: '';
                    display: inline-block;
                    vertical-align: middle;
                    width: 18px;
                    height: 18px;
                    margin-right: 6px;
                    background-image: url('<?php echo $icon_url; ?>');
                    background-repeat: no-repeat;
                    background-size: contain;
                }
            </style>
            <?php
        }

        public function handle_export_json() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'No.' );
            }

            check_admin_referer( 'wdsc_export_json' );

            $audit = $this->run_audit();

            $payload = array(
                'generated_at' => gmdate( 'c' ),
                'site_url'     => site_url(),
                'audit'        => $audit,
            );

            nocache_headers();
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="wp-db-sanity-check.json"' );

            echo wp_json_encode( $payload, JSON_PRETTY_PRINT );
            exit;
        }

        private function run_audit() {
            global $wpdb;

            $results = array();

            // Postmeta pointing at missing posts
            $results['postmeta_orphans'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                 LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE p.ID IS NULL"
            );

            // Attachments with missing parent
            $results['attachments_missing_parent'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = 'attachment'
                   AND post_parent != 0
                   AND post_parent NOT IN (SELECT ID FROM {$wpdb->posts})"
            );

            // Revisions with missing parent
            $results['revisions_missing_parent'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = 'revision'
                   AND post_parent NOT IN (SELECT ID FROM {$wpdb->posts})"
            );

            // Commentmeta pointing at missing comments
            $results['commentmeta_orphans'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm
                 LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
                 WHERE c.comment_ID IS NULL"
            );

            // Comments pointing at missing posts
            $results['comments_missing_posts'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->comments}
                 WHERE comment_post_ID NOT IN (SELECT ID FROM {$wpdb->posts})"
            );

            // Term relationships pointing at missing posts
            $results['term_relationships_missing_posts'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr
                 LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                 WHERE p.ID IS NULL"
            );

            // Term taxonomy pointing at missing terms
            $results['term_taxonomy_missing_terms'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} tt
                 LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                 WHERE t.term_id IS NULL"
            );

            // Term count mismatches
            $results['term_count_mismatches'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} tt
                 WHERE tt.count != (
                     SELECT COUNT(*) FROM {$wpdb->term_relationships} tr
                     WHERE tr.term_taxonomy_id = tt.term_taxonomy_id
                 )"
            );

            return $results;
        }

        private function render_summary( $audit ) {
            ?>
            <table class="widefat striped" style="max-width:900px;">
                <tbody>
                    <tr><th>Orphaned postmeta rows</th><td><?php echo esc_html( $audit['postmeta_orphans'] ); ?></td></tr>
                    <tr><th>Attachments with missing parent</th><td><?php echo esc_html( $audit['attachments_missing_parent'] ); ?></td></tr>
                    <tr><th>Revisions with missing parent</th><td><?php echo esc_html( $audit['revisions_missing_parent'] ); ?></td></tr>
                    <tr><th>Orphaned commentmeta rows</th><td><?php echo esc_html( $audit['commentmeta_orphans'] ); ?></td></tr>
                    <tr><th>Comments linked to missing posts</th><td><?php echo esc_html( $audit['comments_missing_posts'] ); ?></td></tr>
                    <tr><th>Term relationships missing posts</th><td><?php echo esc_html( $audit['term_relationships_missing_posts'] ); ?></td></tr>
                    <tr><th>Term taxonomy rows missing terms</th><td><?php echo esc_html( $audit['term_taxonomy_missing_terms'] ); ?></td></tr>
                    <tr><th>Term count mismatches</th><td><?php echo esc_html( $audit['term_count_mismatches'] ); ?></td></tr>
                </tbody>
            </table>
            <?php
        }

        private function render_post_checks( $audit ) {
            echo '<p>These issues usually come from deleted posts, imports, or plugins that never cleaned up after themselves.</p>';
        }

        private function render_comment_checks( $audit ) {
            echo '<p>Comment and commentmeta problems rarely break anything immediately, but they add noise and waste space.</p>';
        }

        private function render_taxonomy_checks( $audit ) {
            echo '<p>Taxonomy mismatches can cause incorrect counts, broken archives and confusing admin behaviour.</p>';
        }
    }

    new WP_Database_Sanity_Check();
}
