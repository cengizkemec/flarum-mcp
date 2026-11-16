---
name: Flarum Mithril.js
description: Use Mithril 2.2 idioms correctly in Flarum components, including lifecycle, streams, routing and redraw behavior.
version: 1.0.0
license: MIT
tags:
  - flarum
  - mithril
  - javascript
  - ui
required_permission: skills.read
---

# Flarum Mithril.js

Verified against Flarum 2.0.x, which uses Mithril 2.2. Recommendations are marked.

## Components

Flarum components extend `flarum/common/Component`, which extends Mithril's
class component API:

```ts
import Component from 'flarum/common/Component';

export default class Counter extends Component {
  count = 0;

  view() {
    return (
      <button className="Button" onclick={() => { this.count++; }}>
        {this.count}
      </button>
    );
  }
}
```

Call `super.oninit(vnode)` and other `super` lifecycle methods when overriding
them. Attributes are available as `this.attrs`.

## Reactivity

Mithril redraws automatically after DOM event handlers. After asynchronous work
that mutates state, call `m.redraw()`:

```ts
async load() {
  this.loading = true;
  this.data = await app.request({ url: '...' });
  this.loading = false;
  m.redraw();
}
```

`m.redraw.sync()` exists but is rarely appropriate; avoid fighting the redraw
cycle.

## Streams

Flarum exposes Mithril streams via `flarum/common/utils/Stream`:

```ts
import Stream from 'flarum/common/utils/Stream';

this.title = Stream('initial');
```

Streams are useful for form fields and can be passed to `bidi` bindings.

## ItemList

Many UI extension points return `ItemList`. Add items with a key and priority:

```ts
items.add('hello', <a href="/hello">Hello</a>, 10);
```

Higher priority appears first. Use stable keys.

## Routing

`app.route(name, params)` generates URLs. `m.route.set()` navigates. Route
components receive params in `attrs`. Use `Link` from
`flarum/common/components/Link` for internal links.

## Forms and modals

Use `Form`, `FormGroup`, `Button`, `Switch`, `Select` and `Modal` from core
rather than raw markup when possible. This keeps focus, loading and accessibility
behavior consistent.

## Performance

- Avoid expensive work in `view()`.
- Load data in `oninit` and redraw once.
- Use `LoadingIndicator` and `Placeholder` for async states.
- Do not create new component classes inside `view()`.
