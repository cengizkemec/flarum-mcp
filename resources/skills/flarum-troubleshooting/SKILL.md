---
name: Flarum Troubleshooting
description: Diagnose Flarum 2.0 errors systematically using logs, cache, migrations, extension bisect and request-level evidence.
version: 1.0.0
license: MIT
tags:
  - flarum
  - troubleshooting
  - debugging
  - operations
required_permission: skills.read
---

# Flarum Troubleshooting

Verified against Flarum 2.0.x. Recommendations are marked.

## Start with evidence

1. Read `storage/logs/flarum.log`.
2. Check the browser console and network response.
3. Reproduce with a minimal request.
4. Identify the failing layer: web server, PHP, Flarum middleware, extension,
   database, queue or frontend bundle.

Do not change multiple variables at once.

## Common commands

```bash
php flarum info
php flarum cache:clear
php flarum migrate
composer validate
composer audit
```

`php flarum info` reports environment and version details. Use its output when
asking for help.

## White page or 500

- Enable debug mode only on a non-public environment and inspect the exception.
- Check file permissions on `storage/` and `public/assets/`.
- Clear the cache after extension changes.
- Verify PHP extensions required by the database and image processing.
- Check whether a recent extension or Composer update introduced the failure.

## Extension conflicts

Use extension bisect to narrow the set. Disable extensions in groups, not one at
a time. Confirm the result by re-enabling the suspected extension alone.

## Frontend issues

- Rebuild the extension's `js/dist` assets.
- Check that the admin entry re-exports `extend`.
- Verify the extension's locale keys exist.
- Inspect the network tab for 404s on chunks or assets.
- Clear the browser cache after asset changes.

## Database issues

- Run pending migrations.
- Check the configured driver and server version.
- Look for failing foreign keys or duplicate indexes.
- Never edit production rows without a backup and a transaction.

## Queue issues

- Confirm the queue driver and worker.
- Inspect failed jobs in the queue dashboard.
- Check for fatal errors in the log at the job's timestamp.
- Retry a job only after fixing the cause.

## When asking for help

Provide Flarum version, PHP version, database driver and version, the exact
error, relevant log lines with secrets removed, and the steps that reproduce it.
