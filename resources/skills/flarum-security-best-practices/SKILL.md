---
name: Flarum Security Best Practices
description: Secure Flarum 2.0 extensions and MCP integrations against authorization bypass, token theft, SSRF, XSS and unsafe uploads.
version: 1.0.0
license: MIT
tags:
  - flarum
  - security
  - mcp
  - tokens
required_permission: skills.read
---

# Flarum Security Best Practices

Verified against Flarum 2.0.x security guidance. Recommendations are marked.

## Authorization

- Never trust the client to enforce permissions.
- Check the MCP connection permission and the Flarum policy on every execution.
- Check resource visibility in the query, not only in serialization.
- Do not let an administrator-only operation become available merely because a
  tool is hidden from `tools/list`.
- Approval workflows must execute only after an administrator approves the
  exact stored request.

## Tokens

- Generate at least 256 bits of entropy with `random_bytes()`.
- Store only a SHA-256 hash of the token, never plaintext.
- Show the token once at creation or rotation.
- Require `Authorization: Bearer`, never a query-string token.
- Support expiry and immediate revocation.
- Do not log tokens, authorization headers or password values.
- Compare hashes with `hash_equals()`.

## Input and output

- Validate every tool argument with a strict JSON Schema.
- Reject unknown properties where practical.
- Treat AI-generated arguments and uploaded Skill content as untrusted.
- Escape output for its context. Do not render uploaded Markdown as HTML.
- Do not expose email addresses, IP addresses, passwords, sessions or private
  messages unless the user explicitly has the corresponding permission.

## SSRF

Remote Skill imports must:
- require HTTPS,
- reject loopback, private and reserved addresses,
- resolve DNS and pin the address for the request,
- validate every redirect,
- enforce a response size limit,
- reject HTML content.

## Uploads and archives

- Enforce compressed and uncompressed size limits.
- Limit the number of archive entries.
- Reject path traversal, absolute paths, duplicate entries and symlinks.
- Read the single SKILL.md without extracting the archive to disk.
- Reject suspicious compression ratios.

## Rate limiting and abuse

- Limit requests per connection and window.
- Limit authentication failures per IP.
- Log security events without sensitive payloads.
- Use approval for destructive operations when the risk warrants it.

## Dependencies

- Keep Flarum and extensions updated.
- Run `composer audit`.
- Remove abandoned packages.
- Review third-party code before installing it; installing PHP code grants it
  process-level trust even if MCP exposure is separately approved.
