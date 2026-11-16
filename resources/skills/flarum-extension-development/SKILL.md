---
name: Flarum Extension Development
description: Create, structure, build, test and distribute a Flarum 2.0 extension as a Composer package.
version: 1.0.0
license: MIT
tags:
  - flarum
  - extension
  - composer
  - packaging
required_permission: skills.read
---

# Flarum Extension Development

Verified against Flarum 2.0.x. Recommendations are marked.

## Package metadata

A Flarum extension is a Composer package with type `flarum-extension`:

```json
{
  "name": "acme/flarum-hello",
  "type": "flarum-extension",
  "require": {
    "flarum/core": "^2.0"
  },
  "autoload": {
    "psr-4": { "Acme\\Hello\\": "src/" }
  },
  "extra": {
    "flarum-extension": {
      "title": "Hello",
      "category": "feature",
      "icon": {
        "name": "fas fa-smile",
        "backgroundColor": "#238c59",
        "color": "#fff"
      }
    }
  }
}
```

The Flarum extension ID is derived from the package name: `acme/flarum-hello`
becomes `acme-hello`. Locale namespaces use that ID.

## Required files

- `composer.json`
- `extend.php`
- `src/` PSR-4 classes
- `migrations/` if the extension stores data
- `locale/en.yml` (and additional locales)
- `js/src/` and built `js/dist/admin.js` for admin UI
- `tests/` for backend tests
- `README.md` and `LICENSE`

## Lifecycle

Migrations run when the extension is enabled. Disabling an extension does not
drop its tables. Uninstalling runs migrations down and removes the package.

**Recommendation:** all tables owned by the extension should be prefixed with
the extension ID and dropped only by the extension's own migrations.

## Frontend assets

Flarum loads one main JavaScript file per frontend:

```php
(new Extend\Frontend('admin'))
    ->js(__DIR__.'/js/dist/admin.js')
    ->css(__DIR__.'/less/admin.less')
```

The build uses `flarum-webpack-config`. The admin entry file must re-export the
`extend` module:

```ts
import app from 'flarum/admin/app';

export { default as extend } from './extend';

app.initializers.add('acme-hello', () => {
  // imperative-only code
});
```

## Admin page and settings

Use the `Admin` extender in `admin/extend.tsx`:

```ts
new Extend.Admin()
  .page(HelloPage)
  .setting(() => ({
    setting: 'acme-hello.greeting',
    label: app.translator.trans('acme-hello.admin.greeting_label'),
    type: 'text',
  }))
```

## Testing

Use `flarum/testing` integration tests. Enable the extension in `setUp()`:

```php
$this->extension('acme-hello');
```

Build a request with `$this->request(...)` and send it with `$this->send(...)`.
Cover authorized and unauthorized users.

## Distribution

- Tag a release and publish on Packagist.
- Keep `composer.lock` out of the package.
- Verify installation with a clean Flarum install.
- Verify uninstall does not remove unrelated forum data.
- Provide a changelog and upgrade notes for breaking changes.
