---
name: Flarum Administration
description: "Operate a Flarum 2.0 forum: settings, permissions, extensions, queues, maintenance, audit and health checks."
version: 1.0.0
license: MIT
tags:
  - flarum
  - administration
  - operations
  - moderation
required_permission: skills.read
---

# Flarum Administration

Verified against Flarum 2.0.x. Recommendations are marked.

## Admin dashboard

The admin panel includes a dashboard, settings pages, permissions, extension
management, a queue dashboard, admin search and extension health information.
Use admin search to locate a setting instead of hunting through pages.

## Permissions

Permissions are assigned to groups. Core groups are Admin, Guest, Member and
Moderator. A permission can be global or tag-scoped when the Tags extension is
enabled. Administrators bypass group permissions unless a policy force-denies
the ability.

## Extensions

- Enable or disable extensions from the extension page.
- Use **extension bisect** to find a broken extension.
- Read the health widget for abandoned packages and suggested integrations.
- Check `composer.json` requirements before upgrading.
- Disable an extension before removing its package.

## Queues

Flarum 2.0 has a queue dashboard with failed-job management and pause/resume.
If jobs are not processing, verify the queue driver and that a worker is
running. Failed jobs should be inspected before retrying.

## Audit and GDPR

The bundled Audit extension records logins, renames, group and permission
changes, settings, deleted posts, locked and stickied discussions, approvals,
flags, suspensions, extension state changes, GDPR erasures and exports, and
private conversations. The GDPR extension handles erasure and export requests.
Do not build a parallel audit trail when the bundled integration is available.

## Maintenance

```bash
php flarum cache:clear
php flarum migrate
php flarum info
```

Flarum 2.0 supports maintenance modes for planned work. Announce downtime and
verify that the queue and scheduler are running after maintenance.

## Backups

Back up:
- the database,
- `storage/`,
- uploaded assets and avatars,
- `config.php`.

Test restore procedures. A backup that has never been restored is a hypothesis.

## Health checklist

- PHP and database versions supported.
- Queue worker and scheduler running.
- Disk space and log rotation healthy.
- Admin accounts protected with strong credentials and 2FA where available.
- Extensions up to date and not abandoned.
- Audit retention and privacy policy appropriate for the community.
