# Extension Development

Third-party Flarum extensions can expose MCP tools, resources, prompts and Skills
through the public integration API. Capabilities are registered at boot, tagged
with the registering extension's ID, and remain non-executable until an
administrator approves the integration.

## Register with the `Mcp` extender

```php
<?php

use CengizKemec\FlarumMcp\Extend\Mcp;

return [
    (new Mcp())
        ->permission('blog.articles.read', [
            'group' => 'integrations',
            'risk' => 'low',
            'label' => 'Read blog articles',
        ])
        ->tool(
            name: 'list_articles',
            permission: 'blog.articles.read',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                ],
                'required' => [],
                'additionalProperties' => false,
            ],
            handler: function (array $arguments, $context): array {
                return ['articles' => []];
            },
            description: 'List published blog articles.',
            title: 'List articles'
        ),
];
```

The extension ID is derived from `$extension->getId()`. The tools are listed in
**MCP Server → Extensions** and must be approved before they become visible.

## Tools

```php
->tool(
    name: 'get_article',
    permission: 'blog.articles.read',
    inputSchema: [
        'type' => 'object',
        'properties' => ['id' => ['type' => 'integer', 'minimum' => 1]],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    handler: function (array $arguments, $context): array {
        $article = BlogArticle::query()->findOrFail($arguments['id']);

        $context->actor->assertCan('view', $article);

        return [
            'id' => $article->id,
            'title' => $article->title,
            'content' => $article->content,
        ];
    },
    description: 'Read one published article.',
    requiresApproval: false,
    outputSchema: [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'content' => ['type' => 'string'],
        ],
    ]
);
```

Handler arguments:

- `array $arguments` — validated against the input schema.
- `McpRequestContext $context` — `connection`, `actor`, `request`, `ipAddress`,
  `origin`, and `approval` for approved executions.

Return an array, scalar, or `Mcp\Schema\Result\CallToolResult`. Object arrays
become `structuredContent` and JSON text content.

Throw `Flarum\Foundation\ValidationException` for invalid input and
`Flarum\User\Exception\PermissionDeniedException` for denied access. The platform
writes an audit record and returns a safe MCP error.

## Resources

```php
->resource(
    uri: 'flarum://blog/articles',
    name: 'blog_articles',
    permission: 'blog.articles.read',
    handler: fn ($context) => ['articles' => []],
    description: 'Published blog articles.',
    mimeType: 'application/json',
    title: 'Blog articles'
)

->resourceTemplate(
    uriTemplate: 'flarum://blog/articles/{id}',
    name: 'blog_article',
    permission: 'blog.articles.read',
    handler: function ($context, array $variables): array {
        return ['id' => (int) $variables['id']];
    },
    description: 'One blog article.',
    mimeType: 'application/json'
)
```

Static resource handlers receive the context. Template handlers receive the
context and parsed template variables. A resource is listed only when its
permission is granted and the integration is approved.

## Prompts

```php
->prompt(
    name: 'draft_article_summary',
    permission: 'blog.articles.read',
    handler: function (array $arguments, $context): array {
        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => 'Summarise article '.$arguments['id'].' for the homepage.',
                ],
            ],
        ];
    },
    description: 'Draft a homepage summary.',
    arguments: [
        ['name' => 'id', 'description' => 'Article id.', 'required' => true],
    ]
)
```

Return an array of messages (`role` + `content`) or a format accepted by the MCP
SDK prompt formatter. Do not return a custom `description`/`messages` envelope.

## Skills

```php
->skill(
    content: $markdown,
    permission: 'blog.articles.read'
)
```

`$markdown` must be a valid SKILL.md document (front matter optional, level-one
heading required). It is validated at boot. The Skill is exposed with the
extension's integration approval and permission, and appears as
`registered:<slug>` in the admin list.

## Permissions

```php
->permission('blog.articles.write', [
    'group' => 'integrations',
    'risk' => 'high',
    'label' => 'Write blog articles',
])
```

- `risk` is `low`, `medium` or `high`. High-risk permissions require approval
  when the connection enables approvals.
- `admin_only` is reserved for platform administration; do not use it for
  ordinary extension capabilities.
- Register every permission used by a tool, resource or prompt.

## Approval and trust

Registering a tool does **not** make it executable. The administrator must:

1. Approve the extension integration.
2. Enable the individual tool if desired.
3. Grant the permission to the connection's profile.

Unknown or unapproved tools are not listed and cannot be called. Installing a
Flarum extension already grants its PHP code process-level trust; MCP approval is
a separate exposure control.

## Schema rules

- Root input schema must be `type: object`.
- Use `additionalProperties: false`.
- Keep limits and enums strict.
- Do not accept raw SQL, file paths or shell commands.
- Model untrusted arguments defensively; the MCP SDK validates types, not
  business rules.

## Testing

Use `flarum/testing` integration tests:

```php
$this->extension('cengizkemec-mcp', 'acme-blog');
```

Test:

- listed tools for an approved and an unapproved integration
- allowed and denied permissions
- disabled tools
- invalid schema arguments
- Flarum policy denial on the target record
- audit records and approval-required results

## Direct registries

The public registries are container singletons:

```php
resolve(\CengizKemec\FlarumMcp\Mcp\ToolRegistry::class)->register($definition);
resolve(\CengizKemec\FlarumMcp\Mcp\ResourceRegistry::class)->register($definition);
resolve(\CengizKemec\FlarumMcp\Mcp\PromptRegistry::class)->register($definition);
resolve(\CengizKemec\FlarumMcp\Skill\SkillRegistry::class)->register($parsedSkill, $extensionId);
```

Prefer the `Mcp` extender: it fills in the extension ID and keeps registration
declarative.
