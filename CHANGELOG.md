# Changelog

All notable changes to Flarum MCP are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
the project uses [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-11-15

### Added

- Flarum 2.0 extension package `cengizkemec/flarum-mcp`.
- MCP Streamable HTTP endpoint at `/mcp` using the official `mcp/sdk` PHP SDK.
- Initialize-handshake and stateless `2026-07-28` protocol support.
- Bearer token authentication with SHA-256 hashing, expiry, rotation and
  revocation.
- Multiple AI connections with assigned Flarum users and independent tokens.
- Per-connection rate limits, token expiry and active/disabled status are editable
  from the dashboard.
- Per-connection minute/hour/day rate limits and authentication-failure limits.
- Minimum, Standard, Advanced and Full Access profiles.
- Unlimited custom permission profiles with create, edit, delete, duplicate,
  import and export.
- Tool-level enable/disable controls and permission identifiers.
- Dynamic tool, resource and prompt discovery scoped to the connection.
- Built-in discussion, post, user, tag, notification, forum, moderation, system,
  Skill, audit and approval tools.
- JSON:API resource reuse for creation, editing and deletion so Flarum
  validation, policies, events and tag rules apply.
- MCP resources for forum information, statistics, configuration, recent
  discussions, tags, the current user, notifications and Skills.
- Resource templates for discussions, users, tags and Skills.
- Eight built-in prompts with Turkish and English descriptions.
- Fifteen built-in SKILL.md documents covering Flarum 2.0 and MCP integration.
- Custom Skill installation from manual content, HTTPS URL and ZIP archive.
- SKILL.md validation, size limits, unsafe content checks, SSRF protection and
  ZIP bomb/path traversal/symlink protections.
- Third-party extension integration API via the `Mcp` extender.
- Per-extension approval, with unknown or unapproved tools non-executable.
- Approval workflow for destructive operations with expiry, approve/reject and
  re-checked permissions.
- Audit logging with filters, retention and a scheduled `mcp:prune` command.
- Admin dashboard with Overview, Connections, Profiles, Tools, Skills, Resources,
  Prompts, Extensions, Approvals, Audit and Settings sections.
- Turkish and English localization for the admin UI, permissions, tools,
  resources, prompts and errors.
- Integration and unit test suites, PHP syntax checks and frontend type checks.
- README, installation, configuration, permission, tool, Skill, API and security
  documentation.

### Fixed

- Connection listing and lifecycle operations now serialize `created_at` and
  `updated_at` correctly after a fresh database query.
- The Full Access profile now includes permissions registered dynamically by
  third-party extensions, not only the permissions known at build time.
- Custom prompts now return the message-list shape expected by the MCP SDK.
- Built-in Skill identifiers containing a colon work for enable, disable and
  export after URL encoding.
- SKILL.md files saved with a UTF-8 byte-order mark are parsed correctly.
- Connection rotate, revoke and delete no longer depend on a Laravel `now()`
  helper or an undefined model constant.
- Administrative state changes invalidate permission caches in long-running
  processes.
- Permission profile lookups are cached per request, removing repeated database
  queries during tool, resource and prompt discovery.
- Admin form grids are responsive and form controls keep a visible surface on
  both light and dark themes.
- MCP requests now preserve the exact JSON body, so an empty `arguments: {}`
  object is no longer flattened into an array by Flarum's JSON middleware.
- MCP `DELETE` session termination is exempt from CSRF checks, as required for
  bearer-token clients.
- New connections now actually use the configured default permission profile and
  default rate limits, and those settings are editable in the dashboard.

### Security

- No plaintext tokens.
- No URL tokens.
- No MCP bypass of Flarum policies.
- Strict HTTP method and content-type validation.
- CORS and DNS rebinding protection.
- Sanitized audit errors and no token logging.
- Administrator-only settings writes with a safe allowlist.
- Approval re-validation at execution time.

### Known limitations

- No OAuth 2.1 authorization server or dynamic client registration; static bearer
  tokens only.
- Approval decisions are made in the Flarum admin UI, not through MCP tools.
- Extension enable/disable/package management is not exposed through MCP.
- Lock/sticky tools require the corresponding Flarum extensions; tag tools
  require Flarum Tags.
- MCP client support for resources, prompts and Skills varies.
