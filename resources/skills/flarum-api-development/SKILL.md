---
name: Flarum API Development
description: Build JSON:API resources, endpoints, fields and internal API calls for Flarum 2.0 correctly.
version: 1.0.0
license: MIT
tags:
  - flarum
  - api
  - json-api
  - resources
required_permission: skills.read
---

# Flarum API Development

Verified against Flarum 2.0.x. Recommendations are marked.

## Resource structure

```php
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Endpoint;
use Flarum\Api\Schema;

class BookResource extends AbstractDatabaseResource
{
    public function type(): string { return 'books'; }
    public function model(): string { return Book::class; }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make(),
            Endpoint\Index::make()->paginate(),
            Endpoint\Create::make()->authenticated()->can('create'),
            Endpoint\Update::make()->authenticated(),
            Endpoint\Delete::make()->authenticated()->can('delete'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('title')->requiredOnCreate()->maxLength(120),
            Schema\Str::make('body')->requiredOnCreate(),
            Schema\DateTime::make('createdAt'),
            Schema\Relationship\ToOne::make('user')->includable(),
        ];
    }
}
```

Register the resource with `Extend\ApiResource`. Field callbacks receive the
model and a `Flarum\Api\Context`, which exposes `creating()`, `updating()`,
`getActor()` and `getBody()`.

## Visibility

`scope()` must constrain the query to what the actor may see. Use model
visibility scopes:

```php
public function scope(Builder $query, Context $context): void
{
    $query->whereVisibleTo($context->getActor());
}
```

Do not hide rows only in serialization. If a row must not be discoverable, the
query must exclude it.

## Authorization

Endpoints can use `->authenticated()` and `->can('ability')`. Field writability
can be conditional:

```php
Schema\Str::make('title')
    ->writable(fn (Book $book, Context $context) =>
        $context->creating() || $context->getActor()->can('edit', $book))
```

## Internal calls

When one resource needs another endpoint, use the `JsonApi` service rather than
duplicating logic. Pass the actor explicitly and keep the original request for
locale, IP and extensions.

## Errors

Throw Flarum or JSON:API exceptions:
- `Flarum\Foundation\ValidationException` for field errors.
- `Flarum\User\Exception\PermissionDeniedException` for denied actions.
- `Illuminate\Database\Eloquent\ModelNotFoundException` for invisible rows.

Do not return a different error shape for "not found" and "not allowed" when
that would disclose private data.

## Tests

Use `flarum/testing` and assert status codes, document structure and side
effects. Test both a user who may perform the action and one who may not.
