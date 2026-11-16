---
name: MCP Integration Development
description: Expose a Flarum extension through the Flarum MCP platform using tools, resources, prompts, Skills and declared permissions.
version: 1.0.0
license: MIT
tags:
  - flarum
  - mcp
  - integration
  - tools
required_permission: skills.read
---

# MCP Integration Development

Verified against Flarum MCP 1.0 and the official MCP PHP SDK used by the
extension. Recommendations are marked.

## Concepts

- **Tool**: executable function with a JSON Schema, an output and a permission.
- **Resource**: readable data with a URI and a permission.
- **Prompt**: reusable instructions or a workflow template.
- **Skill**: instructions and context for an AI client, not executable code.
- **Integration approval**: an administrator must approve a third-party
  extension before its capabilities are exposed.

## Register capabilities

Use the `Mcp` extender in the extension's `extend.php`:

```php
use CengizKemec\FlarumMcp\Extend\Mcp;

return [
    (new Mcp())
        ->permission('blog.articles.read', [
            'group' => 'integrations',
            'risk' => 'low',
        ])
        ->tool(
            name: 'list_articles',
            permission: 'blog.articles.read',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                ],
                'additionalProperties' => false,
            ],
            handler: function (array $arguments, $context): array {
                return ['articles' => []];
            },
            description: 'List published blog articles.'
        )
        ->resource(
            uri: 'flarum://blog/articles',
            name: 'blog_articles',
            permission: 'blog.articles.read',
            handler: fn ($context) => ['articles' => []],
            description: 'Published blog articles.'
        ),
];
```

The MCP connection must have the declared permission, the tool must be enabled,
and the integration must be approved. The handler must still enforce Flarum
policies on each record.

## Handlers

A tool handler receives `array $arguments` and a request context exposing the
Flarum actor, connection and PSR-7 request. Return an array, a scalar or an
MCP `CallToolResult`.

Throw `Flarum\Foundation\ValidationException` for invalid input and
`Flarum\User\Exception\PermissionDeniedException` for denied access. The platform
converts them into safe MCP tool errors and audit records.

## Resources

Resource handlers return arrays for JSON resources or strings for text. Use an
explicit MIME type in the definition. Never expose private data through a public
URI; declare a permission and re-check the actor.

## Skills

Register a Skill with content that follows the SKILL.md format:

```php
->skill(
    content: $skillMarkdown,
    permission: 'blog.articles.read'
)
```

Skill content is validated and stored as data. It is never executed.

## Testing

- Test with an approved and an unapproved integration.
- Test a connection without the declared permission.
- Test a disabled tool.
- Test invalid JSON Schema arguments.
- Test that policy denial happens even when the tool is visible.
- Test the audit record for allowed, denied and approval-required calls.
