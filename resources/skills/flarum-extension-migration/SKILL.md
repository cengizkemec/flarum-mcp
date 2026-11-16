---
name: Flarum Extension Migration
description: Port a Flarum 1.x extension to Flarum 2.0, including PHP, JSON:API, frontend, testing and packaging changes.
version: 1.0.0
license: MIT
tags:
  - flarum
  - migration
  - upgrade
  - 2.0
required_permission: skills.read
---

# Flarum Extension Migration

Verified against the Flarum 2.0 upgrade documentation. Recommendations are marked.

## Before changing code

1. Back up the database and files.
2. Check every dependency for a 2.0-compatible release.
3. Read the official 2.0 update guides.
4. Create a branch and a staging site.
5. Run the extension's tests against Flarum 2.0 before porting features.

## Platform changes

- PHP 8.3+.
- Laravel 13 components and Symfony 7.4.
- Mithril 2.2 with ES6 output and code splitting.
- MySQL, MariaDB, PostgreSQL and SQLite support.
- PHPUnit 12 and `flarum/testing` integration tests.

## API layer

The old Tobscure JSON:API package was replaced by `flarum/json-api-server`.
Classes such as `Tobscure\JsonApi\Document` and `ResponseBag` no longer exist.
Resources now extend `Flarum\Api\Resource\AbstractDatabaseResource` and declare
endpoints and fields with `Flarum\Api\Endpoint` and `Flarum\Api\Schema`.

Port old serializers and controllers to resources. Do not keep obsolete
controller base classes that no longer exist.

## Authorization

Policies and `$actor->can()` remain the model. Re-check every policy against 2.0
model changes and visibility scopers. Do not replace policies with direct group
checks during the port.

## Frontend

- TypeScript and ES6 are standard.
- Use `flarum/common/extenders` for routes, settings, permissions and pages.
- The admin entry must re-export `extend`.
- Rebuild `js/dist` assets with `flarum-webpack-config`.
- Replace removed core imports with their 2.0 equivalents.

## Database

Run migrations on all supported drivers. Check that old migrations still work
when applied in order on a fresh install. Add new migrations rather than editing
old ones in a released extension.

## Testing

- Replace PHPUnit docblock annotations with attributes.
- Use `flarum/testing` `TestCase`.
- Test allowed and denied access.
- Test token expiry, permission changes and extension disable/uninstall.

## Release

Update `composer.json` to require `flarum/core: ^2.0`, publish a major version,
and document breaking changes. Do not claim 2.0 support until a clean install and
an upgrade both pass.
