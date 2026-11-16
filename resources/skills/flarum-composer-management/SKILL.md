---
name: Flarum Composer Management
description: Manage Flarum 2.0 dependencies, extension packages, version constraints and Composer workflows safely.
version: 1.0.0
license: MIT
tags:
  - flarum
  - composer
  - dependencies
  - deployment
required_permission: skills.read
---

# Flarum Composer Management

Verified against Flarum 2.0.x. Recommendations are marked.

## Core constraint

A Flarum 2.0 site requires `flarum/core: ^2.0` and PHP 8.3 or newer. Extension
packages must require `flarum/core: ^2.0`; they are installed into the site's
`vendor` directory.

## Installing extensions

```bash
composer require vendor/flarum-extension:"*"
php flarum migrate
php flarum cache:clear
```

Enable the extension in the admin panel afterwards.

## Removing extensions

```bash
composer remove vendor/flarum-extension
php flarum migrate
```

Disable the extension first if it has a lifecycle hook. Review migrations before
uninstalling a data-bearing extension. Core data must remain untouched.

## Local development

Use a path repository:

```bash
composer config repositories.local path "packages/*"
composer require acme/flarum-hello:"*@dev"
```

Symlink or copy the package into `packages/`. Re-run `composer dump-autoload`
after adding PSR-4 classes.

## Version constraints

- Libraries and extensions: `^2.0` for Flarum core.
- Development branches: `dev-main` or `*@dev` only in local environments.
- Pin exact versions only when a known incompatibility requires it.
- Do not commit a `composer.lock` in a reusable extension package.

## Validation

```bash
composer validate --strict
composer audit
```

Check that `composer.json` declares the extension type, autoload namespace,
Flarum core constraint and `extra.flarum-extension` metadata.

## Production

- Run `composer install --no-dev --optimize-autoloader` from the lock file.
- Back up the database and `storage` before upgrades.
- Use the command line for major upgrades; do not upgrade through the browser.
- Run `php flarum migrate` and `php flarum cache:clear` after deployment.
- Keep queue workers and the scheduler running if the site uses them.
