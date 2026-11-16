# Configuration

Settings are stored in Flarum's settings table under the `cengizkemec-mcp.`
namespace and can be edited from **Administration → MCP Server → Settings** or
with Flarum's normal settings API.

## Global settings

| Setting | Default | Description |
| --- | --- | --- |
| `cengizkemec-mcp.enabled` | `1` | Master switch for the `/mcp` endpoint. |
| `cengizkemec-mcp.max_request_bytes` | `4194304` | Maximum request body, 16 KiB–16 MiB. |
| `cengizkemec-mcp.session_ttl` | `3600` | Handshake-era session lifetime in seconds, 300–86400. |
| `cengizkemec-mcp.approval_ttl_hours` | `24` | How long a pending approval remains valid. |
| `cengizkemec-mcp.audit_retention_days` | `90` | Audit retention; `mcp:prune` deletes older rows. |
| `cengizkemec-mcp.allowed_hosts` | `[]` | Additional Host header names, JSON array. |
| `cengizkemec-mcp.allowed_origins` | `[]` | Browser origins allowed by CORS, JSON array. |
| `cengizkemec-mcp.default_permission_profile` | `minimum` | Default profile applied when a new connection omits one. |
| `cengizkemec-mcp.default_rate_limit_per_minute` | `60` | Default for new connections; `0` = unlimited. |
| `cengizkemec-mcp.default_rate_limit_per_hour` | `1000` | Default for new connections. |
| `cengizkemec-mcp.default_rate_limit_per_day` | `10000` | Default for new connections. |
| `cengizkemec-mcp.disabled_builtin_skills` | `[]` | Internal list of disabled built-in Skills. |

## Per-connection settings

| Field | Description |
| --- | --- |
| Flarum user | The actor used for every MCP operation. Its policies apply. |
| Permission profile | Built-in or custom upper bound. |
| Custom permissions | Optional allowlist intersected with the profile. |
| Token expiry | Optional; expired tokens fail authentication. |
| Rate limits | Per minute/hour/day; `0` disables that window. |
| Require approval | Enables approval for high-risk tools. |
| Status | `active` or `disabled`; disabling blocks authentication. |

Tokens are generated with 256 bits of entropy, hashed with SHA-256 and stored as
a hash. Rotating a token invalidates the previous one immediately.

## Transport

- Streamable HTTP, JSON-RPC 2.0.
- `POST /mcp` is the request endpoint.
- `GET /mcp` and `DELETE /mcp` are handshake-era session operations.
- `POST` requires `Content-Type: application/json` (or `application/*+json`).
- Responses are JSON or request-scoped SSE streams.
- The server supports the initialize handshake and the stateless
  `2026-07-28` revision through the official PHP SDK.
- `OPTIONS /mcp` returns CORS preflight headers.

## Host and origin validation

The transport validates the `Host` header against the forum host plus
`localhost`, `127.0.0.1` and `[::1]`. Add proxy or alternate hosts in
**Allowed hosts**. Wildcards are not accepted.

Cross-origin browser requests are denied unless the origin is listed in
**Allowed origins**. Add the exact scheme and host, for example
`https://app.example.com`. Same-origin requests do not need an entry.

Server-side MCP clients do not send an `Origin` header and are unaffected.

## Rate limits

Each authenticated request consumes one unit in the minute, hour and day
windows. The strictest exceeded window returns HTTP `429` with `Retry-After`.
Limits are tracked in Flarum's cache; if the cache is unavailable the request is
allowed and a warning is logged, while authentication still applies.

Authentication failures are limited separately per IP (20 failures per 10
minutes). This protects the endpoint from token guessing without locking a
legitimate connection out after a configuration mistake.

## Approvals

When a connection has **Require approval** enabled, high-risk tools create a
pending approval instead of executing:

- deleting discussions or posts
- hiding or restoring content
- locking, unlocking, pinning or unpinning
- suspending or editing users
- changing forum settings
- managing tags
- managing Skills or prompts

The tool result contains `status: approval_required` and an `approval_id`.
Nothing is executed until an administrator approves it in
**MCP Server → Approvals**. Pending requests expire after the configured TTL.

Approval does not weaken permissions: the operation is re-checked against the
current profile, tool switch and Flarum policy at execution time.

## Audit retention

Every tool, resource, prompt and approval operation writes an audit row without
arguments or credentials. Old rows are removed by:

```bash
php flarum mcp:prune
php flarum mcp:prune --days=30
```

The command is also scheduled daily by the extension. Adjust
`cengizkemec-mcp.audit_retention_days` to change the default.

## Language

The extension ships `locale/en.yml` and `locale/tr.yml`. Admin text follows the
administrator's Flarum language. Tool, resource and prompt descriptions follow
the forum's current locale, with English fallback.

Custom prompts may declare a `locale` value, but the description and content are
returned as stored. Skill content is returned verbatim; it is not translated by
the server.

## Disabling the endpoint

Set **Enable MCP server** off. The `/mcp` route remains registered but returns a
JSON-RPC error with HTTP `503`; existing tokens are not deleted. Re-enable the
setting to resume without rotating tokens.
