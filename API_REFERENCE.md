# API Reference

## MCP endpoint

`POST /mcp` — JSON-RPC 2.0 over MCP Streamable HTTP.

Authentication:

```http
Authorization: Bearer fmcp_...
Content-Type: application/json
Accept: application/json
```

Never put a token in the URL. `GET /mcp` and `DELETE /mcp` are handshake-era
session operations. `OPTIONS /mcp` handles CORS preflight. All three MCP routes
are exempt from Flarum's CSRF middleware because authentication is bearer-token
based. The exact JSON-RPC body is captured before Flarum's JSON parser runs, so
empty objects such as `"arguments": {}` stay objects instead of becoming arrays.

## Protocol methods

The official `mcp/sdk` server handles protocol conformance. The extension
exposes these elements:

| Method | Status |
| --- | --- |
| `initialize` | Supported (handshake era) |
| `server/discover` | Supported (2026-07-28 era) |
| `ping` | Supported |
| `tools/list` | Supported, permission-filtered |
| `tools/call` | Supported |
| `resources/list` | Supported, permission-filtered |
| `resources/templates/list` | Supported, permission-filtered |
| `resources/read` | Supported |
| `resources/subscribe` | Protocol support; no live subscription bus is configured |
| `prompts/list` | Supported, permission-filtered |
| `prompts/get` | Supported |
| `notifications/initialized` | Supported |
| `notifications/cancelled` | Supported |

Unknown tools, resources and prompts return JSON-RPC errors, not empty success
results.

## JSON-RPC errors

| HTTP | Code | Meaning |
| --- | --- | --- |
| 400 | `-32600` / `-32700` | Invalid request or parse error |
| 401 | `-32001` | Missing, invalid, expired or revoked token |
| 403 | `-32002` | Authenticated but not allowed to access the forum |
| 404 | `-32002` | Resource/session not found (handshake semantics) |
| 405 | `-32600` | Method not allowed |
| 413 | `-32600` | Request body too large |
| 415 | `-32600` | Unsupported content type |
| 429 | `-32003` | Rate limited |
| 500 | `-32603` | Internal error, details not exposed |
| 503 | `-32000` | MCP server disabled |

A `401` includes `WWW-Authenticate: Bearer realm="Flarum MCP"`.
A `429` includes `Retry-After`, `X-RateLimit-Limit` and
`X-RateLimit-Remaining`.

## Tool call result

Successful object results:

```json
{
  "content": [{"type": "text", "text": "{\"id\":1,\"title\":\"...\"}"}],
  "structuredContent": {"id": 1, "title": "..."},
  "isError": false
}
```

Tool-level failures use `isError: true`. Protocol-level failures use a JSON-RPC
`error` object.

Approval-required results are successful results containing:

```json
{
  "status": "approval_required",
  "approval_id": 42,
  "tool": "delete_discussion",
  "expires_at": "...",
  "message": "..."
}
```

## Rate limit headers

| Header | Meaning |
| --- | --- |
| `X-RateLimit-Limit` | Lowest active window limit |
| `X-RateLimit-Remaining` | Remaining requests in the lowest window |
| `X-RateLimit-Reset` | Unix timestamp when that window resets |
| `Retry-After` | Seconds to wait after a `429` |

## Admin HTTP API

All admin endpoints require an authenticated Flarum administrator and are
protected by the API stack, including CSRF checks. The admin frontend uses
`app.request()` and sends the CSRF token automatically.

Success responses are plain JSON. Errors use:

```json
{"errors":[{"status":"422","code":"validation_error","detail":"..."}]}
```

### Overview and connections

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/mcp/overview` | Dashboard statistics, health, recent audit |
| `GET` | `/api/mcp/connections` | List connections |
| `POST` | `/api/mcp/connections` | Create a connection; returns the token once |
| `PATCH` | `/api/mcp/connections/{id}` | Update name, user, profile, limits, status |
| `DELETE` | `/api/mcp/connections/{id}` | Delete a connection |
| `POST` | `/api/mcp/connections/{id}/rotate` | Rotate the token; returns the new token once |
| `POST` | `/api/mcp/connections/{id}/revoke` | Revoke and disable the token |

Create body:

```json
{
  "name": "Production AI",
  "description": "Support assistant",
  "userId": 1,
  "permissionProfile": "minimum",
  "customPermissions": ["discussions.read"],
  "expiresInDays": 30,
  "rateLimitPerMinute": 60,
  "rateLimitPerHour": 1000,
  "rateLimitPerDay": 10000,
  "requireApproval": true
}
```

Response includes `connection` and `token`. The token is never returned again.

### Permission profiles

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/mcp/profiles` | Built-in and custom profiles + permission catalog |
| `POST` | `/api/mcp/profiles` | Create a custom profile |
| `PATCH` | `/api/mcp/profiles/{id}` | Update a custom profile |
| `DELETE` | `/api/mcp/profiles/{id}` | Delete a custom profile |
| `POST` | `/api/mcp/profiles/{id}/duplicate` | Clone built-in or custom |
| `GET` | `/api/mcp/profiles/{id}/export` | Export JSON |
| `POST` | `/api/mcp/profiles/import` | Import as a custom profile |

`{id}` is a built-in key (`minimum`, `standard`, `advanced`, `full`) or a custom
key (`custom-1`). Built-in profiles cannot be edited or deleted.

### Tools and resources

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/mcp/tools` | Tool registry with usage statistics |
| `PATCH` | `/api/mcp/tools/{name}` | Enable/disable a tool |
| `GET` | `/api/mcp/resources` | Resource registry |
| `PATCH` | `/api/mcp/resources` | Enable/disable a resource (`uri`, `enabled`) |

### Skills

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/mcp/skills` | Built-in and custom Skills |
| `POST` | `/api/mcp/skills` | Install manual content |
| `PATCH` | `/api/mcp/skills/{id}` | Update a custom Skill |
| `DELETE` | `/api/mcp/skills/{id}` | Delete a custom Skill |
| `POST` | `/api/mcp/skills/{id}/enable` | Enable/disable (`enabled`) |
| `POST` | `/api/mcp/skills/install-url` | Install from HTTPS URL |
| `POST` | `/api/mcp/skills/install-zip` | Install from base64 ZIP |
| `GET` | `/api/mcp/skills/{id}/export` | Export SKILL.md |

`{id}` is `builtin:<slug>`, `registered:<slug>` or a numeric custom id.

### Prompts and integrations

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/mcp/prompts` | Built-in and custom prompts |
| `POST` | `/api/mcp/prompts` | Create a custom prompt |
| `PATCH` | `/api/mcp/prompts/{id}` | Update a custom prompt |
| `DELETE` | `/api/mcp/prompts/{id}` | Delete a custom prompt |
| `GET` | `/api/mcp/integrations` | Capabilities grouped by extension |
| `PATCH` | `/api/mcp/integrations/{extensionId}` | Set `pending`, `approved` or `disabled` |

### Audit and approvals

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/mcp/audit` | Audit records with filters |
| `GET` | `/api/mcp/approvals` | Approval requests |
| `POST` | `/api/mcp/approvals/{id}/approve` | Approve and execute |
| `POST` | `/api/mcp/approvals/{id}/reject` | Reject |

Audit filters: `connectionId`, `toolName`, `status`, `operation`, `q`, `from`,
`to`, `limit`, `offset`.

Approval actions accept `{"note": "optional"}`. Approval executes the stored
operation and returns the approval plus the tool result.

### Settings

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/mcp/settings` | Effective MCP settings |
| `PATCH` | `/api/mcp/settings` | Update settings |

See [CONFIGURATION.md](CONFIGURATION.md) for keys and limits.

## PHP integration API

| Class | Purpose |
| --- | --- |
| `CengizKemec\FlarumMcp\Extend\Mcp` | Public extender for tools, resources, prompts, Skills and permissions |
| `CengizKemec\FlarumMcp\Mcp\ToolRegistry` | Registered tool definitions |
| `CengizKemec\FlarumMcp\Mcp\ResourceRegistry` | Registered resource definitions |
| `CengizKemec\FlarumMcp\Mcp\PromptRegistry` | Registered prompt definitions |
| `CengizKemec\FlarumMcp\Skill\SkillRegistry` | Extension-contributed Skills |
| `CengizKemec\FlarumMcp\Mcp\PermissionRegistry` | Permission catalogue |
| `CengizKemec\FlarumMcp\Mcp\McpRequestContext` | Request-scoped actor/connection context |
| `CengizKemec\FlarumMcp\Mcp\ToolDefinition` | Tool name, schema, permission, handler |
| `CengizKemec\FlarumMcp\Mcp\ResourceDefinition` | Resource/template definition |
| `CengizKemec\FlarumMcp\Mcp\PromptDefinition` | Prompt definition |

See [EXTENSION_DEVELOPMENT.md](EXTENSION_DEVELOPMENT.md) for examples.

## Audit record

| Field | Meaning |
| --- | --- |
| `connection_id` | MCP connection |
| `user_id` | Flarum actor |
| `operation` | `tool`, `approved_tool`, `resource`, `prompt`, `approval`, `auth` |
| `tool_name` | Tool/resource/prompt identifier |
| `permission` | Permission checked |
| `permission_decision` | `allowed`, `denied`, `approval_required`, `requested`, ... |
| `status` | `success`, `error`, `pending`, `auth_failed`, ... |
| `error_code` / `error_message` | Safe error details |
| `duration_ms` | Execution time |
| `approval_id` | Linked approval |
| `ip_address` | Request IP |
| `request_id` | `X-Request-Id` or generated id |
| `meta` | Argument keys and target id; never content |

Tokens, passwords and authorization headers are never written to the audit log.
