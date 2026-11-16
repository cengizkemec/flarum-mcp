# Skills

Skills are **instructions and contextual knowledge** for an AI client. They are
not executable tools. A Skill can teach an agent Flarum conventions, house style
or a support workflow, but it cannot run PHP, shell commands or JavaScript.

Tools are executable operations. Skills, tools, resources and prompts are managed
independently.

## Built-in library

The extension ships 15 Skills:

| Skill | Focus |
| --- | --- |
| Flarum Core Development | Extenders, service providers, routes, middleware, events, policies |
| Flarum Extension Development | Package structure, assets, admin pages, testing, distribution |
| Flarum 2.0 Architecture | Laravel 13, JSON:API rebuild, search, frontend, operations |
| Flarum API Development | JSON:API resources, endpoints, fields, internal calls |
| Flarum Permissions and Policies | Gate order, policies, visibility, tag permissions |
| Flarum Frontend Development | Mithril, admin extenders, routes, settings, build |
| Flarum Mithril.js | Components, lifecycle, streams, redraw, routing |
| Flarum Composer Management | Dependencies, path repositories, upgrades, production installs |
| Flarum Administration | Settings, permissions, extensions, queues, audit, maintenance |
| Flarum Troubleshooting | Logs, cache, migrations, extension bisect, queues, frontend |
| Flarum Database Architecture | Models, migrations, visibility, transactions, drivers |
| Flarum Localization | YAML locales, ICU messages, natural Turkish and English |
| Flarum Extension Migration | 1.x to 2.0 PHP, API, frontend and testing changes |
| Flarum Security Best Practices | Tokens, policies, SSRF, uploads, output, dependencies |
| MCP Integration Development | Registering tools, resources, prompts, Skills, permissions |

Built-in Skills are read from `resources/skills/` and can be enabled, disabled,
previewed and exported. They cannot be deleted. Updating the extension updates
the bundled content.

## SKILL.md format

```markdown
---
name: Support Reply Guide
description: House style for support replies.
version: 1.0.0
license: MIT
tags:
  - support
  - tone
required_permission: skills.read
---

# Support Reply Guide

Use a calm, factual tone. Acknowledge the problem, state what you know, and
give the next concrete step.
```

Requirements:

- valid UTF-8, no NUL bytes
- maximum 512 KiB
- a level-one heading
- a name and description (front matter or derived from the document)
- optional version, license, tags and `required_permission`

Code fences are allowed and are never executed. Outside code fences, unsafe HTML
and `javascript:` links are rejected. Local file references such as
`[x](../file)` are rejected.

## Installation sources

Administrators can install Skills from:

1. **Built-in library** — always available; enable/disable only.
2. **GitHub raw URL** or any HTTPS URL returning Markdown/plain text.
3. **ZIP archive** containing `SKILL.md` at the root or in one top-level folder.
4. **Manual SKILL.md upload.**
5. **Custom Markdown documentation** pasted into the editor.

Update an existing custom Skill by installing with the same slug and the update
flag enabled.

## Validation and security

- Remote imports require HTTPS.
- The host is resolved and the address is pinned for cURL, blocking DNS
  rebinding and private/reserved addresses.
- Redirects are manual, limited and revalidated.
- Response size is limited to 512 KiB.
- ZIP archives are read in memory, not extracted to disk.
- Path traversal, absolute paths, duplicate entries, symlinks and oversized
  entries are rejected.
- Compression ratios are checked for zip bombs.
- Uploaded content is stored as data. It is never executed or rendered as HTML.
- The admin preview uses escaped text.

Skill content is untrusted instructions. An AI client may follow it; that is why
only administrators can install it and why every Skill has a permission.

## Permissions

- `skills.read` — list and read Skills.
- `skills.install` — install or update Skills from the admin API.
- `skills.manage` — edit, enable/disable, delete, import/export.

Built-in Skills default to `skills.read`; a Skill may declare a stricter
`required_permission`.

## MCP exposure

Skills are exposed through:

- resource `flarum://skills` — JSON list of enabled Skills
- resource template `flarum://skills/{slug}` — raw SKILL.md text
- tool `list_skills`
- tool `get_skill`

The connection must have `skills.read` and any stricter Skill permission.
Third-party Skills also require their integration to be approved.

## Exporting to a client

Use **Skills → Export** to download a standard SKILL.md file. Place it in the
Skill directory used by your AI client, following that client's documentation.
Some clients support native Skill installation, some only read MCP resources,
and some do neither. The server does not claim that every client installs Skills
automatically.

For clients that read MCP resources, point the agent at `flarum://skills` or ask
it to call `list_skills`.

## Creating a custom Skill

1. Open **MCP Server → Skills**.
2. Enter a URL, upload a file, or paste Markdown.
3. Keep the content focused on one workflow.
4. State what is verified and what is a recommendation.
5. Avoid secrets, internal URLs and personal data.
6. Test it with a Minimum Access connection before granting broader access.
