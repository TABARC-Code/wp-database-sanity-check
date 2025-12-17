# IdiotsGuide
WP Database Sanity Check

###This tool is for when your site has been alive for years and you suspect the database is carrying baggage.###

## What this is checking

WordPress tables do not enforce relationships.
That means rows can point at things that no longer exist.
This plugin checks for the most common forms of that.

## How to read the numbers
Zero is good.
Small numbers are normal on old sites.
Large numbers mean neglect, bad plugins, or messy migrations.

## What you should not do
Do not run random SQL deletes on production because a table says “orphan”.
Do not clean everything in one go.
Do not assume WordPress will forgive you.

## A safe pattern
Back up the database.
Clone to staging.
Clean one category at a time.
Verify the site after each step.
If something feels unclear, stop.
Confusion is how outages start.

## One uncomfortable truth
Many sites run fine with thousands of orphaned rows.
That does not mean it is healthy.
It just means the damage is quiet.
And one ends up spending hours looking. 
