---
name: Flarum Permissions and Policies
description: Implement Flarum 2.0 authorization correctly with policies, group permissions and visibility scopes.
version: 1.0.0
license: MIT
tags:
  - flarum
  - permissions
  - policies
  - security
required_permission: skills.read
---

# Flarum Permissions and Policies

Verified against Flarum 2.0.x. Recommendations are marked.

## Gate order

Flarum's authorization gate evaluates:

1. Every registered policy's result.
2. Group permissions matching the ability name.
3. Administrator status.
4. Deny.

Policy results have priority `FORCE_DENY > FORCE_ALLOW > DENY > ALLOW`.
A policy can therefore override group permissions, but an administrator check
is only reached when policies abstain.

## Checking

```php
$actor->can('reply', $discussion);
$actor->assertCan('delete', $post);
$actor->assertRegistered();
$actor->assertAdmin();
```

Use `hasPermission()` only inside a policy or when implementing a permission
check. It does not run policies.

## Policies

```php
class BookPolicy extends AbstractPolicy
{
    public function edit(User $actor, Book $book): ?string
    {
        if ($book->user_id === $actor->id && $actor->can('reply', $book->discussion)) {
            return $this->allow();
        }

        return null;
    }
}
```

Register with `Extend\Policy()->modelPolicy(Book::class, BookPolicy::class)`.
Global policies are registered with `globalPolicy()`.

## Visibility

Policies decide abilities. Visibility scopers decide which rows a query may
return. Both are required:

```php
public function scope(Builder $query, Context $context): void
{
    $query->whereVisibleTo($context->getActor());
}
```

Do not expose a hidden row through a different endpoint. A hidden tool is not
secure unless execution is checked again.

## Tags

Tag-scoped permissions use identifiers such as `tag12.startDiscussion`.
Restricted tags are enforced by the Tags extension's policies and visibility
scopers. When accepting a tag argument, resolve it through the visible tag
query and let Flarum validate tag counts.

## Testing permissions

Create at least two actors: one with the permission and one without. Assert
both the API response and any side effect. For policy regressions, test the
gate directly and through the endpoint.
