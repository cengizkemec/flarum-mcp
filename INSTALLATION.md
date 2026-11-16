# Installation

## Requirements

- Flarum 2.0.x
- PHP 8.3+
- A database supported by Flarum (MySQL, MariaDB, PostgreSQL or SQLite)
- Composer 2
- PHP cURL extension for remote Skill imports
- PHP ZIP extension for ZIP Skill uploads

The extension does not require Node.js in production. Its admin JavaScript is
prebuilt and shipped in `js/dist/admin.js`.

## Install with Composer

From the Flarum root:

```bash
composer require cengizkemec/flarum-mcp
php flarum migrate
php flarum cache:clear
```

If the extension is not enabled automatically:

```bash
php flarum extension:enable cengizkemec-mcp
php flarum migrate
php flarum cache:clear
```

Or enable **MCP Server** in **Administration → Extensions**.

## Verify the extension

```bash
php flarum info
composer show cengizkemec/flarum-mcp
```

Open **Administration → MCP Server**. The Overview tab should show the endpoint
`https://your-forum.example/mcp` and the server as running.

## Create the first connection

1. Go to **MCP Server → Connections**.
2. Enter a connection name.
3. Enter the Flarum user ID that the AI should act as.
4. Choose a permission profile. Start with **Minimum Access**.
5. Optionally set token expiry and rate limits.
6. Create the connection.
7. Copy the `fmcp_…` token immediately. It is not stored in plaintext and
   cannot be shown again.

The bearer token is the only credential. There is no separate client secret.

## Test the endpoint

```bash
curl -sS https://forum.example.com/mcp \
  -H 'Authorization: Bearer fmcp_your_token_here' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "initialize",
    "params": {
      "protocolVersion": "2025-11-25",
      "capabilities": {},
      "clientInfo": {"name": "curl", "version": "1.0.0"}
    }
  }'
```

A successful response includes `serverInfo.name` = `Flarum MCP` and a
`Mcp-Session-Id` header. Reuse that header for subsequent handshake-era calls:

```bash
curl -sS https://forum.example.com/mcp \
  -H 'Authorization: Bearer fmcp_your_token_here' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -H 'Mcp-Session-Id: YOUR_SESSION_ID' \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'
```

Modern `2026-07-28` requests are stateless and do not need a session header.

## Reverse proxy and HTTPS

- Serve the forum over HTTPS. Bearer tokens must not travel over plain HTTP.
- Forward the `Authorization`, `Content-Type`, `Accept`, `MCP-Protocol-Version`,
  `Mcp-Session-Id`, `Mcp-Method`, `Mcp-Name` and `Mcp-Param-*` headers.
- Do not strip the request body for `POST /mcp`.
- Do not enable a proxy that rewrites the response body; Streamable HTTP may
  return an SSE stream.
- If the public host differs from Flarum's configured URL, add it in
  **Settings → Additional allowed hosts**.
- Browser-based MCP clients need an explicit entry in **Allowed browser origins**.
  Same-origin requests do not need one.

## Development install

From the Flarum root:

```bash
composer config repositories.flarum-mcp path "packages/flarum-mcp"
composer require cengizkemec/flarum-mcp:"*@dev"
php flarum migrate
php flarum cache:clear
```

Build the admin assets inside the package:

```bash
cd packages/flarum-mcp/js
npm install
npm run build
```

## Uninstall

```bash
php flarum extension:disable cengizkemec-mcp
composer remove cengizkemec/flarum-mcp
php flarum migrate
php flarum cache:clear
```

Disabling stops the endpoint and keeps the extension's tables. Uninstalling runs
the extension's own migrations down and drops only its MCP tables. It does not
delete discussions, posts, users, groups, tags, settings or any other core data.

**Back up the database first.** Export any audit records you are required to
retain before uninstalling.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| `404` on `/mcp` | Extension enabled, cache cleared, route not blocked by the web server. |
| `401` | Token copied correctly, connection active, token not expired or revoked. |
| `403` | The assigned Flarum user can access the forum; the permission profile grants the tool. |
| `429` | Connection rate limit; see **Connections**. |
| `405` | Use `POST` for JSON-RPC; `GET`/`DELETE` are session operations. |
| `415` | Send `Content-Type: application/json`. |
| Tools missing | Profile permission, tool switch, integration approval, optional extension. |
| Admin page blank | Rebuild `js/dist/admin.js`; clear cache; check the browser console. |
| Remote Skill import fails | HTTPS only, public host, cURL installed, size under 512 KiB. |
