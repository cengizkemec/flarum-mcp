# Contributing

Thanks for improving Flarum MCP. This project targets Flarum 2.0 and PHP 8.3+.

## Development setup

1. Install a Flarum 2.0 development site.
2. Add the package as a path repository:
   ```bash
   composer config repositories.flarum-mcp path "packages/flarum-mcp"
   composer require cengizkemec/flarum-mcp:"*@dev"
   ```
3. Install PHP dependencies in the package:
   ```bash
   composer install
   ```
4. Install and build the admin assets:
   ```bash
   cd js
   npm install
   npm run build
   ```
5. Enable the extension and clear the cache:
   ```bash
   php flarum extension:enable cengizkemec-mcp
   php flarum cache:clear
   ```

## Tests

```bash
composer test:unit
composer test:integration
composer lint
```

Integration tests use `flarum/testing` with SQLite by default. To prepare the
test database once:

```bash
composer test:setup
```

Frontend checks:

```bash
cd js
npm run check-typings
npm run build
```

## Coding standards

- PHP 8.3 syntax, PSR-12 style.
- Keep user-facing text in `locale/en.yml` and `locale/tr.yml`.
- Do not hardcode English in Mithril components.
- Use Flarum extenders and public services. Avoid direct SQL when a model,
  repository, resource or policy exists.
- Reuse Flarum's JSON:API resources for writes.
- Add tests for allowed and denied access.
- Do not log tokens, passwords, authorization headers or private content.
- Do not weaken an authorization check to make a test pass.

## Pull requests

- Keep changes focused.
- Describe the behavior change and the security impact.
- Include test evidence.
- Update documentation and the changelog for user-visible changes.
- Do not claim MCP or Flarum compatibility that was not tested.

## Translations

Turkish and English are maintained in this repository. Keep keys stable, use ICU
MessageFormat for plurals, and write natural Turkish rather than literal
translations. Do not add a translation key without both locales.

## Skills

Built-in Skills live in `resources/skills/<slug>/SKILL.md`. Validate them with:

```bash
php tools/validate-content.php
```

Do not include secrets, private URLs or personal data. Mark recommendations
clearly and do not invent Flarum APIs.

## Security

Report vulnerabilities privately. Do not open a public issue with exploit
details. See [SECURITY.md](SECURITY.md).
