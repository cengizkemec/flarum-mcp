# Security

Security is a first-class requirement of Flarum MCP. This document describes the
threat model and the controls implemented in 1.0.0. It also states limitations
plainly.

## Threat model

The server assumes:

- AI clients and their arguments are untrusted.
- Uploaded SKILL.md content and remote archives are untrusted.
- A stolen bearer token is dangerous until revoked or expired.
- A misconfigured profile must not be able to exceed Flarum's authorization.
- Third-party extensions are installed PHP code, but their MCP capabilities must
  not become executable without administrator approval.
- Error messages must not leak private content or credentials.
- The public `/mcp` endpoint is reachable by attackers and must resist token
  guessing, oversized bodies and malformed requests.

## Authentication

- Bearer token in the `Authorization` header only.
- Tokens are generated with 256 bits from `random_bytes()`.
- Only a SHA-256 hash and a short prefix are stored.
- A plaintext token is shown once at creation or rotation.
- Tokens can expire and can be revoked immediately.
- Revocation rotates the stored hash, so a cached token cannot match later.
- Query-string tokens are ignored.
- Authentication failures are limited per IP.
- Sessions are bound to the connection principal. A session id from one
  connection cannot be used by another.
- Session files are stored under `storage/mcp-sessions`. Keep Flarum storage
  writable by PHP but outside the public web root; multi-node deployments need
  shared storage or sticky routing.

**Limitation:** the extension is a static-token resource server, not an OAuth 2.1
authorization server. Clients that require OAuth discovery/dynamic registration
need their own connector or a future OAuth integration.

## Authorization

Effective permission is the intersection of:

1. profile,
2. optional connection allowlist,
3. tool/resource/prompt switch,
4. integration approval,
5. Flarum policy and visibility scope.

Discovery filters hidden capabilities, but execution re-checks them. An attacker
who knows a hidden tool name still cannot execute it.

Flarum's `User::can()`, `assertCan()`, policies and visibility scopers remain
authoritative. Full Access does not bypass them. Administrator-only settings also
require `isAdmin()` at execution time.

## Approval

Sensitive operations can require approval. The first call stores the requested
arguments and returns an approval id without executing. Execution happens only
after an administrator approves, and the permission is re-checked at that
moment. Rejected, expired or already-decided approvals cannot execute.

Approval arguments are stored in the extension's table and visible only to
administrators. They are not written to the audit log.

## Tokens and logs

Never logged:

- bearer tokens
- authorization headers
- passwords
- session cookies
- full tool arguments or post content

Error messages are sanitized to redact `fmcp_…` tokens and bearer values and are
truncated. Audit metadata contains argument keys and a target id, not content.

## Rate limiting and abuse

- Per-connection minute/hour/day limits.
- Per-IP authentication failure limit (20 per 10 minutes).
- `Retry-After` on `429`.
- Maximum request body size.
- Strict `Content-Type` and HTTP method checks.
- Body size is enforced by the MCP SDK and pre-checked from `Content-Length`.

If the cache backend is unavailable, request rate limiting fails open to keep
the forum available; authentication and permissions still apply. Authentication
failure counting also fails open and logs a warning.

## SSRF

Remote Skill imports:

- require HTTPS;
- reject userinfo, non-443 ports and control characters;
- resolve IPv4 and IPv6 and reject private/reserved/loopback addresses;
- pin the resolved address with cURL `CURLOPT_RESOLVE` to prevent DNS rebinding;
- refuse automatic redirects and validate each manual hop;
- limit redirects to three;
- enforce a 512 KiB response limit;
- reject HTML responses.

A server that can reach a URL but cannot use cURL cannot import remote Skills.

## ZIP and upload safety

- ZIP uploads are capped at 5 MiB.
- At most 200 entries.
- At most 1 MiB per entry and 5 MiB total uncompressed.
- Compression ratio and empty-compressed-entry checks block zip bombs.
- Path traversal, absolute paths, drive letters, NUL bytes, duplicate entries and
  symlinks are rejected.
- The archive is read in memory. Nothing is extracted to disk.
- Exactly one `SKILL.md` is required at the root or in one top-level folder.
- SKILL.md is capped at 512 KiB and scanned for unsafe HTML outside code fences.

## XSS and content safety

- Skill content is never rendered as HTML by the extension. The admin preview is
  escaped text.
- Post content from Flarum is returned as plain text, not rendered HTML.
- User-controlled strings in the admin UI are rendered through Mithril text
  nodes, not raw HTML.
- The admin API returns JSON and sets `X-Content-Type-Options: nosniff`.

## CSRF

All `/mcp` routes are exempt from Flarum's session CSRF check because they use
bearer authentication and do not rely on a browser session. Admin API routes keep
Flarum's CSRF protection. The MCP endpoint does not accept cookies as
authentication.

## Browser security

- CORS is disabled by default for cross-origin requests.
- Explicit origins can be configured.
- Host header validation blocks DNS rebinding.
- The endpoint does not create authenticated browser sessions from bearer
  tokens.

## Audit

Every tool/resource/prompt attempt and every approval decision is recorded with
connection, actor, permission, decision, result, duration and safe error details.
Audit logs are admin-only and can be filtered and pruned. They are dropped when
the extension is uninstalled, so export records first if retention is required.

## Data exposure

The extension deliberately does not expose:

- email addresses
- password hashes
- access tokens or sessions
- IP addresses of other users
- private messages
- hidden or restricted content the actor cannot see
- secret settings
- extension package management
- arbitrary SQL, shell, filesystem or PHP execution

`set_system_setting` accepts only a small safe allowlist.

## Dependency and supply chain

- Uses the official MCP PHP SDK.
- `composer audit` should be run in CI.
- Keep Flarum and extensions updated.
- Installing a Flarum extension grants its PHP code process-level trust. MCP
  integration approval controls exposure, not the ability of installed code to
  run.

## Reporting a vulnerability

Do not open a public issue for a security vulnerability. Contact the maintainer
privately with:

- affected version
- reproduction steps
- impact
- any suggested fix

You will receive an acknowledgement and a coordinated disclosure timeline.

## Security checklist for administrators

- Use HTTPS.
- Create one connection per client; do not share tokens.
- Start with Minimum Access.
- Use expiry for temporary connections.
- Revoke unused connections.
- Enable approvals for destructive operations.
- Review the audit log and pending approvals.
- Keep `allowed_origins` empty unless a browser client is required.
- Add only the hosts your proxy actually uses.
- Test new Skills with a low-privilege connection.
- Back up before upgrades and uninstalls.
