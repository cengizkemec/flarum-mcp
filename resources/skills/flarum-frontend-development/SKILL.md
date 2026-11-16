---
name: Flarum Frontend Development
description: Extend the Flarum 2.0 Mithril frontend with components, admin pages, routes, settings and build tooling.
version: 1.0.0
license: MIT
tags:
  - flarum
  - frontend
  - mithril
  - typescript
required_permission: skills.read
---

# Flarum Frontend Development

Verified against Flarum 2.0.x. Recommendations are marked.

## Two applications

- `forum`: public interface.
- `admin`: administration interface.

They share `common` code. Each has its own entry file and webpack bundle.

## Layout

```text
js/
  src/
    admin/
      components/
      extend.tsx
      index.tsx
    common/
    forum/
  dist/
  package.json
  tsconfig.json
  webpack.config.cjs
```

`admin/index.tsx` must re-export the extenders:

```ts
import app from 'flarum/admin/app';

export { default as extend } from './extend';

app.initializers.add('acme-hello', () => {
  // only imperative code here
});
```

## Extenders

```ts
import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';

export default [
  new Extend.Admin()
    .page(HelloPage)
    .setting(() => ({
      setting: 'acme-hello.message',
      label: app.translator.trans('acme-hello.admin.message_label'),
      type: 'text',
    })),
];
```

Prefer extenders over monkey-patching for settings, permissions and pages.
Use `extend()` or `override()` from `flarum/common/extend` only for UI changes
that have no declarative extender.

## Admin pages

Extend `ExtensionPage` when you want the standard extension header, enable
switch and permission grid. Override `content()` and use `this.setting()` for
form fields. For fully custom pages, extend `AdminPage` and render your own
layout.

## Requests

Use `app.request()` so the CSRF token, base path and error handling are applied:

```ts
const data = await app.request<{ items: Item[] }>({
  method: 'GET',
  url: app.forum.attribute('apiUrl') + '/mcp/items',
});
```

Do not hardcode `/api`; use the API URL from forum attributes.

## Routing

Register forum routes in both PHP and JS. Backend `Frontend::route()` serves the
shell; frontend `Extend.Routes().add()` renders the component.

## Internationalization

All user-facing text must go through `app.translator.trans()`. Never hardcode
English strings in components.

## Building

```bash
npm install
npm run build
```

Commit `js/dist/admin.js`. Verify the admin page loads with debug mode on and
with compiled assets.
