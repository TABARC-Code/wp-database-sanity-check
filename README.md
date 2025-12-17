<p align="center">
  <img src=".branding/tabarc-icon.svg" width="180" alt="TABARC-Code Icon">
</p>

# WP Database Sanity Check
WordPress does not enforce database integrity.
It never has.
This plugin exists to show me the consequences of that, calmly, without trying to fix everything automatically.

## What it does
Adds:
Tools
DB Sanity Check
It audits core tables and reports:
Orphaned postmeta
Attachments and revisions pointing at missing parents
Orphaned commentmeta
Comments attached to missing posts
Broken taxonomy relationships
Term count mismatches
Exports JSON so I can review or hand it to someone else.

## What it does not do
No deletion
No repair
No optimisation
No magic
It shows facts. I decide what to do next.

## Notes
Cleaning database rows is destructive.
Even when rows look unused, they might be referenced indirectly by plugins or custom code.
Always back up.
Prefer staging.
Delete in small batches.

## License
GPL-3.0-or-later. See LICENSE.
