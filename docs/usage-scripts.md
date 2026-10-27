---
title: In a script and in the markup
order: 30
---

# In a script and in the markup

A script does not decide on cookies itself: it subscribes to its feature, and the
panel decides. `public/js/consent.js` (deferred, printed by the partial) defines
`window.Consent` in front of `@glitchr/flowjs/cookie`.

## `window.Consent`

| Method | Returns, does |
|---|---|
| `use(name, options, onEnable, onDisable)` | Declares the feature (its switch appears in the panel; `options.label`, else the configuration's words, else the name), runs `onEnable` when it is on - now, or the moment the visitor says yes - and `onDisable` when it goes off. Returns whether it is on now. `options` may be left out: `use(name, onEnable, onDisable)` |
| `state(name)` | `true`, `false`, or `null` (not answered); no name: the site's own feature |
| `enabled(name)` | What to do now: an implicit feature is on until refused |
| `cookie(group, name, value, expires)` | `Cookie.set()` through the answer: nothing is written while the feature is off; returns whether the cookie is there |
| `features()` | Every feature: `{name, label, implicit, required, state, enabled}` |
| `undecided()` | Whether the visitor has not answered yet |
| `refused()`, `accepted()` | The site's own feature refused; not refused |
| `accept()`, `refuse()` | Answers for the visitor |
| `open()`, `close()` | Opens the panel; closes it (not while the question is pending) |
| `scan(root)` | Looks for the elements that wait for a feature again (after a part of the page was replaced) |

```js
window.Consent.use('CHAT', { label: 'The chat' }, () => loadCrisp(), () => window.$crisp?.push(['do', 'chat:hide']));

// A cookie through the answer: nothing is written while ANALYTICS is off.
window.Consent.cookie('ANALYTICS', 'VISITOR_ID', id, 30 * 24 * 3600);
```

`Cookie.set(group, ...)` is superseded the same way: any group a script writes
becomes a feature of the panel, and stays unwritten while it is off - unless the
cookie is declared under a feature (`storage`), whose answer then decides.

The page's scripts run before the library is there (the bundle's comes first):
until then `Consent` answers from what the browser already holds
(`localStorage` `cookie/<GROUP>`), and `use()`, `cookie()`, `accept()`, `refuse()`,
`open()`, `close()` and `scan()` are kept and made once the page's scripts have
all run.

## Events

| Event | On | When | Detail |
|---|---|---|---|
| `consent:change` | `window` | Every answer | `{consent, groups, features}`: the site's own feature on or not, the names, `{USER: true, CHAT: false...}` |
| `ga:cookie` | `window` | Every answer | none (for the sites written before this bundle) |
| `<name>:consent` | `document` | A feature turned on; and for every feature already on, at load and after each page swap (`transparent:load`, `turbo:load`) | none |

```js
window.addEventListener('consent:change', (e) => e.detail.features);
document.addEventListener('chat:consent', () => loadCrisp());
```

omnibase/social loads Instagram on `social:consent`.

## Elements that wait for a feature

The library's attributes, under the prefix `data-consent`:

```html
<script type="text/plain" data-consent-script="ANALYTICS" data-src="https://..."></script>
<iframe data-consent-script="SOCIAL" data-consent-src="https://www.instagram.com/p/.../embed"></iframe>
<template data-consent-script="LOCATION"><div id="map"></div></template>
<p data-consent-placeholder="LOCATION">Shown until the feature is on. <button type="button" data-consent-open>Cookies</button></p>
```

| Element | Once the feature is on |
|---|---|
| `<script type="text/plain" data-consent-script="X">` | becomes a script and runs (`data-src`: loaded from there) |
| `<iframe data-consent-script="X" data-consent-src="...">` | gets its `src` (taken away again when the feature goes off) |
| `<template data-consent-script="X">` | its content is put in the page (and taken out when the feature goes off) |
| `[data-consent-placeholder="X"]` | is shown while the feature is off |
| `[data-consent-open]` (or `[data-cookie-open]`) | opens the panel when clicked, at any time |

An element naming a feature nobody declared declares it.

## Several circles

Several circles of a page share one panel: it sits under the first the visitor can
see while the question floats, under the one clicked afterwards. A circle may leave
the page with it - a page swap empties the bar it sat in, a script rebuilds the
footer: the next circle's panel takes over, and nothing throws. Wherever a site's
stylesheet places it, the panel stays inside the screen across: it is moved by its
margins until it is `--consent-gutter` from the left and right edges, and put back
when it closes; opened under a circle far from where the visitor is, it is scrolled
into sight up or down only.
