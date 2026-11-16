---
name: Flarum Core Development
description: Work safely inside the Flarum 2.0 core architecture, including extenders, service providers, routes, middleware, events and policies.
version: 1.0.0
license: MIT
tags:
  - flarum
  - core
  - php
  - extenders
required_permission: skills.read
---

# Flarum Core Development

Verified against Flarum 2.0.x (PHP 8.3+, Laravel 13 components, Symfony 7.4).
Recommendations are marked with **Recommendation**.

## Mental model

Flarum is a Laravel-based PHP application with a JSON:API layer and a separate
Mithril frontend. Most features, including bundled features, are extensions.
Core provides discussions, posts, users, groups, notifications, settings,
routing, authorization and the extension system.

Extension code is declarative whenever possible. Use extenders from
`Flarum\Extend` instead of patching core files.

## Extension entry point

An extension ships an `extend.php` file that returns an array of extenders:

```php
<?php

use Flarum\Extend;

return [
    new Extend\Locales(__DIR__.'/locale'),
    (new Extend\Routes('forum'))
        ->get('/hello', 'acme.hello', HelloController::class),
];
```

Extenders implement `Flarum\Extend\ExtenderInterface`:

```php
public function extend(Container $container, ?Extension $extension = null): void;
```

Do not rely on internal classes unless the documentation or source marks them
as stable. Prefer documented extenders and public services.

## Service providers

Register a provider through the `ServiceProvider` extender:

```php
(new Extend\ServiceProvider())->register(MyServiceProvider::class)
```

Extend `Flarum\Foundation\AbstractServiceProvider` and implement `register()`
and/or `boot()`. Bind services in `register()`. Resolve other services lazily.

## Routes and controllers

Backend routes belong to the `forum`, `admin` or `api` collection. A controller
implements `Psr\Http\Server\RequestHandlerInterface`:

```php
public function handle(ServerRequestInterface $request): ResponseInterface
```

Return a PSR-7 response such as `Laminas\Diactoros\Response\HtmlResponse`.
Route parameters are merged into the request's query params.

## Middleware

Add middleware through `Extend\Middleware` for `forum`, `admin` or `api`.
Middleware implements `Psr\Http\Server\MiddlewareInterface`. Throw
`Flarum\Foundation\ValidationException` for field errors; Flarum formats it.

## Authorization

Never check group membership directly for a protected action. Use the gate:

```php
$actor->can('reply', $discussion);
$actor->assertCan('edit', $post);
```

Policies live under `Access` namespaces and extend
`Flarum\User\Access\AbstractPolicy`. Register them with `Extend\Policy`.

## Events

Domain events are raised by models and dispatched after persistence. Listen
with `Extend\Event`:

```php
(new Extend\Event())->listen(Discussion\Event\Started::class, MyListener::class)
```

Use events instead of duplicating another extension's side effects.

## Settings

Read settings through `Flarum\Settings\SettingsRepositoryInterface`. Register
defaults with `Extend\Settings()->default()`. Do not read secrets from the
database into the public forum payload.

## Verification checklist

- `php -l` every changed file.
- `composer validate`.
- Run extension integration tests with `flarum/testing`.
- Run migrations on a fresh database, then on an upgraded database.
- Verify both allowed and denied authorization paths.
- Check that disabling or uninstalling the extension leaves core data intact.
