---
name: Flarum Database Architecture
description: Design Flarum 2.0 models, migrations, queries and transactions across MySQL, MariaDB, PostgreSQL and SQLite.
version: 1.0.0
license: MIT
tags:
  - flarum
  - database
  - eloquent
  - migrations
required_permission: skills.read
---

# Flarum Database Architecture

Verified against Flarum 2.0.x, which supports MySQL, MariaDB, PostgreSQL and SQLite.
Recommendations are marked.

## Models

Extend `Flarum\Database\AbstractModel`, which integrates Flarum's event and
visibility systems:

```php
class Book extends AbstractModel
{
    use ScopeVisibilityTrait;

    protected $table = 'books';
    protected $casts = ['published_at' => 'datetime'];
}
```

Use relations and Eloquent builder methods. Do not write raw SQL unless a
driver-specific query is unavoidable, and then branch on the driver name.

## Migrations

Flarum extends Laravel migrations with helpers:

```php
use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('books', function (Blueprint $table) {
    $table->increments('id');
    $table->string('title', 160);
    $table->unsignedInteger('user_id')->index();
    $table->dateTime('created_at')->nullable();
});
```

Migrations run when an extension is enabled. `down()` runs on uninstall. Never
drop core tables or another extension's tables.

## Visibility

`whereVisibleTo($actor)` applies registered visibility scopers. Use it for every
read that can expose user content:

```php
Discussion::query()->whereVisibleTo($actor)->get();
$discussion->posts()->whereVisibleTo($actor)->get();
```

Scopers must be efficient. Prefer `whereIn` and joins over per-row PHP checks.

## Transactions

Wrap multi-row writes in a transaction. Flarum's JSON:API resources already
wrap discussion creation and first-post creation in a transaction. When writing
directly:

```php
$connection->transaction(function () { /* ... */ });
```

## Indexes

Index foreign-key columns and frequent filters. Flarum 2.0 is tested for N+1
queries; add eager loading instead of querying inside loops. Composite indexes
should match the actual filter and sort order.

## Drivers

- MySQL/MariaDB: utf8mb4, InnoDB.
- PostgreSQL: native JSON and full-text search.
- SQLite: supported, but SQLite fulltext differs and foreign-key behavior
  depends on the connection configuration.

Test migrations and queries on every driver the extension claims to support.
Do not assume MySQL-specific SQL.

## Data safety

- Back up before migrations.
- Do not store plaintext secrets.
- Do not log private message bodies or access tokens.
- On uninstall, delete only the extension's own tables.
