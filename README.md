# Flarum MCP

**Universal Model Context Protocol server and AI integration platform for Flarum 2.0.**

Flarum MCP turns a Flarum community into a secure, permission-scoped MCP server.
AI clients can search discussions, read and write content, use reusable prompts,
discover Skills, and call capabilities registered by other Flarum extensions —
without bypassing Flarum's users, groups or policies.

- **Package:** `cengizkemec/flarum-mcp`
- **Platform:** Flarum 2.0 (PHP 8.3+)
- **Protocol:** MCP Streamable HTTP with JSON-RPC 2.0
- **Endpoint:** `https://your-forum.example/mcp`
- **License:** MIT

> Status: 1.0.0. The MCP transport is built on the official `mcp/sdk` PHP SDK,
> which serves both the initialize-based protocol era and the stateless
> `2026-07-28` revision over one endpoint.

---

## Features

- **MCP server inside Flarum** — no separate Node.js process, no sidecar.
- **Multiple AI connections** — each with its own Flarum user, token, permission
  profile, rate limits, expiry and approval setting.
- **Four built-in permission profiles** plus unlimited custom profiles.
- **Tool-level permissions** — every tool has its own identifier and can be
  enabled or disabled individually.
- **Flarum-native authorization** — every operation still passes through Flarum
  policies and visibility scopes.
- **Built-in tools** for discussions, posts, users, tags, notifications, forum
  information, moderation, settings and Skills.
- **Resources and prompts** with independent permissions.
- **AI Skills manager** — 15 built-in Flarum/MCP Skills, custom SKILL.md
  installation from upload, URL or ZIP, validation and export.
- **Extension integration API** — other Flarum extensions can register tools,
  resources, prompts, Skills and permissions. Integrations must be approved by
  an administrator before they become executable.
- **Approval workflow** — sensitive operations can require explicit approval.
- **Audit log** — every tool call, denial, error and approval decision, with
  retention and filters.
- **Turkish and English** admin and AI-facing copy.
- **No plaintext tokens** — only SHA-256 hashes are stored; tokens are shown once.

## Requirements

- Flarum 2.0.x
- PHP 8.3 or newer
- MySQL/MariaDB, PostgreSQL or SQLite (whatever the forum uses)
- PHP extensions: `json`, `mbstring`, `openssl`, `pdo`
- Optional: `curl` for remote Skill installation, `zip` for ZIP Skill uploads

## Installation

```bash
composer require cengizkemec/flarum-mcp
php flarum migrate
php flarum cache:clear
```

Then open **Administration → MCP Server** and create an AI connection.
The token is displayed exactly once.

See [INSTALLATION.md](INSTALLATION.md) for a full walkthrough.

## First connection

1. Open **Administration → MCP Server → Connections**.
2. Choose a Flarum user, a permission profile, rate limits and expiry.
3. Create the connection and copy the `fmcp_…` token.
4. Give the token to your MCP client as an `Authorization: Bearer` header.

The endpoint is `https://your-forum.example/mcp`.

## MCP client examples

The formats below are verified against the clients' current documentation. MCP
support changes quickly; check your client version if a field is rejected.

### Claude Code

```bash
claude mcp add --transport http flarum https://forum.example.com/mcp \
  --header "Authorization: Bearer fmcp_your_token_here"
```

A project `.mcp.json` entry uses the `http` type:

```json
{
  "mcpServers": {
    "flarum": {
      "type": "http",
      "url": "https://forum.example.com/mcp",
      "headers": {
        "Authorization": "Bearer ${FLARUM_MCP_TOKEN}"
      }
    }
  }
}
```

### Cursor

`~/.cursor/mcp.json` or `.cursor/mcp.json`:

```json
{
  "mcpServers": {
    "flarum": {
      "url": "https://forum.example.com/mcp",
      "headers": {
        "Authorization": "Bearer ${env:FLARUM_MCP_TOKEN}"
      }
    }
  }
}
```

### OpenCode

`opencode.jsonc`:

```jsonc
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "servers": {
      "flarum": {
        "type": "remote",
        "url": "https://forum.example.com/mcp",
        "oauth": false,
        "headers": {
          "Authorization": "Bearer {env:FLARUM_MCP_TOKEN}"
        }
      }
    }
  }
}
```

OpenCode defaults to the initialize handshake, which this server supports. You
do not need `"protocol": "2026-07-28"` unless you explicitly want the stateless
revision.

### Other clients

Use Streamable HTTP (not the deprecated HTTP+SSE transport) and send the token in
the `Authorization` header. Do not put tokens in the URL. Some desktop clients
use `mcpServers` with a `url` and `headers` object; others use a command/UI. Use
the shape documented by your client.

**ChatGPT:** MCP connectors are configured through the product UI, which varies
by plan and workspace policy. There is no universal JSON snippet. Use the
connector's custom MCP URL field with `https://your-forum.example/mcp` and a
bearer header if the UI offers one.

## Permission profiles

| Profile | Summary |
| --- | --- |
| Minimum Access | Read-only public forum content. |
| Standard Access | Read plus create discussions/replies and edit own content where Flarum allows. |
| Advanced Access | Standard plus moderation tools permitted by Flarum. |
| Full Access | All supported MCP operations. Must be chosen explicitly. |

Every profile is an upper bound. A tool is only visible when:

1. the connection's profile grants its permission,
2. the optional connection-specific allowlist grants it, and
3. the tool is globally enabled and its integration is approved.

Execution additionally checks Flarum's policy for the target record. Full Access
never bypasses Flarum's authorization.

See [PERMISSIONS.md](PERMISSIONS.md).

## Tools, resources and prompts

Built-in tools cover:

- discussions: search, read, create, edit, delete, hide, restore, lock, unlock,
  pin, unpin
- posts: read, create, edit, delete, hide, restore
- users: search, read own/other profiles
- tags: list/read (when Flarum Tags is enabled)
- notifications: read and mark read
- forum: info, statistics, recent activity
- system: safe settings read, allow-listed settings write (administrator only)
- Skills: list/read
- audit and pending approvals (read-only)

See [TOOLS.md](TOOLS.md) for the full table.

## Skills

The extension ships 15 built-in SKILL.md documents:

1. Flarum Core Development
2. Flarum Extension Development
3. Flarum 2.0 Architecture
4. Flarum API Development
5. Flarum Permissions and Policies
6. Flarum Frontend Development
7. Flarum Mithril.js
8. Flarum Composer Management
9. Flarum Administration
10. Flarum Troubleshooting
11. Flarum Database Architecture
12. Flarum Localization
13. Flarum Extension Migration
14. Flarum Security Best Practices
15. MCP Integration Development

Skills are instructions, not executable code. They are exposed through MCP
resources (`flarum://skills`, `flarum://skills/{slug}`) and through
`list_skills` / `get_skill`. They can also be exported as standard SKILL.md
files for clients with their own Skill system. Not every MCP client installs
Skills automatically.

See [SKILLS.md](SKILLS.md).

## Third-party extension integration

Other extensions register capabilities with the public `Mcp` extender:

```php
use CengizKemec\FlarumMcp\Extend\Mcp;

return [
    (new Mcp())
        ->permission('blog.articles.read', ['risk' => 'low'])
        ->tool(
            name: 'list_articles',
            permission: 'blog.articles.read',
            inputSchema: [
                'type' => 'object',
                'properties' => ['limit' => ['type' => 'integer']],
                'additionalProperties' => false,
            ],
            handler: fn (array $args, $context) => ['articles' => []],
            description: 'List published blog articles.'
        ),
];
```

The administrator must approve the integration before its tools are exposed.
See [EXTENSION_DEVELOPMENT.md](EXTENSION_DEVELOPMENT.md).

## Security

- Bearer tokens only; no URL tokens.
- SHA-256 token hashes; tokens shown once.
- Per-connection rate limits and authentication-failure limits.
- Permission checks on discovery **and** execution.
- Flarum policies and visibility scopes remain authoritative.
- Approval workflow for sensitive operations.
- SSRF protections for remote Skill imports.
- ZIP traversal, symlink and zip-bomb protections.
- Audit records without tokens or passwords.

Read [SECURITY.md](SECURITY.md).

## Screenshots

Screenshot placeholders live in `docs/screenshots/`:

- `overview.png` — dashboard cards and recent activity
- `connections.png` — AI connection management
- `profiles.png` — permission profile editor
- `tools.png` — tool registry and statistics
- `skills.png` — Skill library and upload

## Documentation

- [INSTALLATION.md](INSTALLATION.md)
- [CONFIGURATION.md](CONFIGURATION.md)
- [PERMISSIONS.md](PERMISSIONS.md)
- [TOOLS.md](TOOLS.md)
- [SKILLS.md](SKILLS.md)
- [EXTENSION_DEVELOPMENT.md](EXTENSION_DEVELOPMENT.md)
- [API_REFERENCE.md](API_REFERENCE.md)
- [SECURITY.md](SECURITY.md)
- [CONTRIBUTING.md](CONTRIBUTING.md)
- [CHANGELOG.md](CHANGELOG.md)

## Known limitations

- Static bearer tokens are supported. The extension does not implement an OAuth
  2.1 authorization server or dynamic client registration. Build a connector for
  a client that requires OAuth, or use a client that supports static headers.
- Approval decisions are made in the Flarum admin panel. MCP clients can read
  pending approvals but cannot approve their own requests.
- `set_system_setting` only accepts an explicit safe allowlist of forum settings.
  It is not a general settings editor.
- Lock/sticky tools require the corresponding Flarum extensions. Tag tools
  require Flarum Tags.
- Extension enable/disable and package management are intentionally not exposed
  as MCP tools.
- MCP clients differ in resource, prompt and Skill support. The server exposes
  standards-compliant capabilities; the client decides what to display.
- Handshake-era sessions are stored under Flarum's `storage/mcp-sessions`
  directory. Multi-server deployments need shared writable storage or sticky
  routing so a session reaches the node that created it.
- Remote Skill import needs the PHP cURL extension and outbound HTTPS. ZIP Skill
  import needs the PHP ZIP extension.

## License

MIT. See [LICENSE](LICENSE).
