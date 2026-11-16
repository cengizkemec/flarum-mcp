---
name: Flarum Localization
description: Internationalize Flarum 2.0 extensions with YAML locale files, ICU messages and natural Turkish and English translations.
version: 1.0.0
license: MIT
tags:
  - flarum
  - localization
  - i18n
  - turkish
required_permission: skills.read
---

# Flarum Localization

Verified against Flarum 2.0.x. Recommendations are marked.

## Locale files

Place `en.yml` and `tr.yml` in the extension's `locale/` directory and register
the directory:

```php
new Extend\Locales(__DIR__.'/locale')
```

The first-level key is the extension ID in `vendor-package` form. For
`cengizkemec/flarum-mcp`, the ID is `cengizkemec-mcp`.

```yaml
cengizkemec-mcp:
  admin:
    title: MCP Server
  lib:
    error:
      permission_denied: You do not have permission to perform this action.
```

## Using translations

JavaScript:

```ts
app.translator.trans('cengizkemec-mcp.admin.title')
```

PHP:

```php
$translator->trans('cengizkemec-mcp.lib.error.permission_denied');
```

Never concatenate translated fragments or hardcode English in components.
Pass variables and HTML placeholders through the translator.

## ICU MessageFormat

Plurals and selection use ICU syntax:

```yaml
items: "{count, plural, one {# item} other {# items}}"
```

Turkish often needs no plural suffix after a number, but the ICU message still
allows the translator to choose the natural form. Do not force English plural
rules.

## Key conventions

- `_label`, `_help`, `_button`, `_title`, `_message` suffixes.
- Snake_case keys.
- One key per context; reuse translations with `=>` references in the `ref`
  namespace instead of reusing a key in code.
- Escape YAML when a value starts with special characters or contains `:`,
  `{`, `}` or quotes.

## Turkish quality

Write natural Turkish, not word-for-word English:

- "You do not have permission" becomes "Bu işlemi yapma izniniz yok."
- "Connection" in the MCP sense is "Bağlantı"; "permission profile" is
  "İzin profili".
- Keep established technical terms such as "token", "JSON" and "MCP" where
  translating them would confuse administrators.
- Use consistent formal register.

## Verification

- Load the admin and forum pages in both locales.
- Check that no raw translation keys appear.
- Test plural messages with zero, one and many.
- Verify emails and server-side error messages use the translator.
