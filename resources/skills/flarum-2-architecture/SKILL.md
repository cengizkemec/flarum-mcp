---
name: Flarum 2.0 Architecture
description: Understand the Flarum 2.0 platform architecture, its Laravel and JSON:API foundations, frontend model and extension points.
version: 1.0.0
license: MIT
tags:
  - flarum
  - architecture
  - laravel
  - json-api
required_permission: skills.read
---

# Flarum 2.0 Architecture

Verified against Flarum 2.0.x. Recommendations are marked.

## Platform

- PHP 8.3 or newer.
- Laravel 13 components for containers, Eloquent, validation, queues and events.
- Symfony 7.4 components for console, HTTP client, translation and YAML.
- Mithril 2.2 for the frontend, with ES6 output and code splitting.
- PSR-7 requests and PSR-15 middleware throughout.

## Applications

Flarum serves three applications:

- `forum` for public pages and utility routes.
- `admin` for the administration interface.
- `api` for JSON:API and API-adjacent routes.

Each application has its own middleware pipe and route collection.

## API layer

Flarum 2.0 replaced the old Tobscure JSON:API package with
`flarum/json-api-server`. Resources extend
`Flarum\Api\Resource\AbstractDatabaseResource` and declare endpoints and fields
with `Flarum\Api\Endpoint` and `Flarum\Api\Schema`.

The internal `Flarum\Api\JsonApi` service can run a resource endpoint inside PHP:

```php
$discussion = $api
    ->withRequest($request)
    ->forResource(DiscussionResource::class)
    ->forEndpoint('create')
    ->process($body, [], ['actor' => $actor]);
```

This preserves validation, policies, domain events and extension hooks. It is
preferable to writing rows directly when a resource exists.

## Search

Search is driver-based. `Flarum\Search\SearchManager` selects a driver per
resource. The database driver supports SQLite, MySQL, MariaDB and PostgreSQL.
Use `SearchCriteria` and let the driver apply filters such as `q`, `author` and
`tag`.

## Extensions

Extensions are Composer packages. They contribute extenders, service providers,
routes, migrations, assets, locales, policies, API resources and search filters.
Extensions can also expose their own extenders for other extensions.

## Frontend

The frontend is a single-page Mithril application. It consumes the JSON:API.
Code splitting loads pages and components on demand. Admin and forum share the
`common` layer.

## Data model

Core models include discussions, posts, users, groups, notifications and access
tokens. Discussions and posts use visibility scopers to constrain queries.
Tags, locks, sticks, flags and other features are extensions.

## Operational features

Flarum 2.0 includes a queue dashboard, extension health checks, extension
bisect, admin search, audit integration, GDPR integration, multiple database
drivers and a `tinker` console. Use these instead of inventing parallel tools.
