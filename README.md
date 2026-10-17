# Consent

The cookie answer of the [omnibase](https://github.com/glitchr-studio/omnibase)
sites, written once: a small circle that opens a panel - what the site keeps,
**accept**, **refuse**, **customize** - instead of a copy of the same
controller in every site.

- **Feature by feature.** Whatever is kept in the visitor's browser belongs to
  a *feature* the visitor answers for: what the site cannot work without
  (`NECESSARY`, always on), the visitor's preferences (`USER`), the time zone
  (`LOCATION`), a chat, a measure of audience, Instagram... "Personnaliser"
  opens the detailed section: one row per feature, its switch, and what it
  keeps - each cookie by name, how long, what for.
- **The answer is enforced.** A refused feature writes nothing (through
  `Cookie.set()`, `Cookie.cookies`, `Cookie.local`, `Cookie.session`) and what
  it had kept is erased; accepted again, it writes again. A tracker never
  starts without a yes.
- A visitor who has not answered sees the panel float at the bottom of the
  screen with a countdown (`consent.countdown`, 10 s): when the time is up,
  each feature takes its default, the site's own on and the rest off.
  `countdown: 0` waits for an answer; `countdown_action` makes no answer a yes
  to everything, or a no. A click elsewhere answers nothing (`dismiss: none`):
  the question stays until the visitor answers or the countdown, which they
  can read, runs out. A click on Symfony's web debug toolbar, a `dump()`, or
  anything marked `data-consent-ignore` (`consent.ignore` adds selectors) is
  not on the page: it neither answers nor closes the panel.
- Anything with `data-consent-open` opens the panel - a "Cookies" entry of a
  sitemap, a link of the privacy page.
- Once answered (by the visitor, or by the countdown), the circle and the
  "Cookies" links open the detailed section (`reopen: details`): each switch
  as the answer was recorded, "Tout accepter", "Tout refuser", "Enregistrer mes choix"
  and "Fermer" - what was kept is seen at once, not one click further.
  `reopen: banner` asks the question again instead.
- The work is done by [`@glitchr/flowjs/cookie`](https://github.com/glitchr-studio/flowjs/blob/main/docs/cookie.md)
  (the `Cookie` global omnibase loads on every page): the answer and where it
  is kept, asking again, the countdown, the detailed section, the erasing.
  The bundle gives it the site's configuration and its words, the markup, and
  `window.Consent` for the scripts.
- Nobody is asked twice: the answers given before that library
  (`localStorage` `cookie/<GROUP>`, as `@glitchr/cookie` kept them) are taken
  over, and a feature split from another one takes that one's answer.

## Install

```bash
composer require omnibase/consent:dev-main
```

```php
// config/bundles.php
Base\Consent\ConsentBundle::class => ['all' => true],
```

```twig
{# where the circle goes: a footer, a tool bar #}
{% include '@Consent/_cookie.html.twig' with {place: 'footer'} %}

{# elsewhere on the page #}
<button type="button" data-consent-open>Cookies</button>
```

The page needs `@glitchr/flowjs` 1.2.0 or later for its `Cookie` global:
omnibase's `base-async` imports it. On a page that still packs
`@glitchr/cookie` (an older core) the bundle falls back on
`consent-legacy.js`, the panel as it was before the detailed section.

## Configure

Nothing is needed: the bundle starts from its own features
(`config/features.php`).

| Feature | | Keeps |
| --- | --- | --- |
| `NECESSARY` | required: never asked, its switch locked on | the session cookie, `LANG` (the language of the site), the CSRF token of a form being sent, `REMEMBERME`, the answer itself (`cookie-consent`) |
| `USER` | on until refused | `USER/INFO` (the browser's language, calendar, light or dark theme), `USER/THEME/*`, `USER/LAST_VISIT` |
| `LOCATION` | on until refused; takes the answer given to `USER` | `LOCATION/TIMEZONE` |
| `ANALYTICS`, `CHAT`, `SOCIAL` | on demand: listed once a script or an element uses them, off until a yes | `ANALYTICS/VISITOR_ID` |

```yaml
# config/packages/consent.yaml
consent:
    groups: [USER]               # the site's own features: on until refused; the first is "the site's answer" (Consent.refused())
    countdown: 10                # seconds before no answer is an answer; 0 = wait for one
    countdown_action: default    # default (each feature its default) | accept | refuse
    dismiss: none                # a click elsewhere while the question floats: none (it stays) | default | accept | refuse
    ignore: []                   # selectors whose clicks are not on the page, besides Symfony's toolbar and [data-consent-ignore]
    reopen: details              # what the circle and the "Cookies" links show once answered: details (the switches as recorded) | banner (the question)
    position: bottom-left        # where the question floats: bottom-right, bottom-center, top-left...
    lifetime: 180d               # how long an answer counts
    version: ~                   # changed, every visitor is asked again
    signals: { gpc: false, dnt: false }   # privacy signals honoured: true, or the features they cover
    privacy_route: app_privacy   # linked from the panel; null or a missing route leaves the link out
    privacy_route_parameters: {} # e.g. {slug: vie-privee} when a generic route serves the page
    erase_on_refuse: []          # other cookies erased with the site's own, by name prefix
    defaults: true               # false: none of the bundle's own features, only those below
    features:
        LOCATION: false          # one of the bundle's taken out (the time zone then goes with USER)
        CHAT: { erase: [crisp-client] }     # a third party's cookies, by name prefix
        SOCIAL: ~                # omnibase/social loads Instagram on the `social:consent` event this sends
        ANALYTICS:               # what it keeps: listed in the detailed section, erased at a refusal
            storage:
                - { name: '_pk_*', duration: 13mo, purpose: 'Matomo' }
                - { name: visits, type: local, purpose: analytics_visits }
        BASKET: { required: true, storage: [basket] }
        # implicit: true   -> on until refused, on when the visitor does not answer (only for what the site needs)
        # required: true   -> always on, its switch locked
        # on_demand: true  -> known, listed only once something uses it
        # inherits: USER   -> split from USER: takes the answer USER was given, nobody is asked again
    options: {}                  # anything else @glitchr/flowjs/cookie takes: record: {store: local, name: ...}, debug: true...
```

An `erase` prefix (and `erase_on_refuse`, the site's own feature's) is a line
of the detailed section, `<prefix>*`, unless one of the feature's `storage`
entries already takes it in (`crisp-client*` for `crisp-client`): declare the
cookies you can name and keep the prefix for the rest. The line says whose
cookies they are - the site's own when the prefix is a feature's group
(`ANALYTICS/`, what `Cookie.set()` writes) or a cookie another feature declares
(`LANG`), a third party's otherwise (`storage.own`, `storage.third_party`).

What a site says about a feature is laid over what the bundle says, field by
field; a storage entry replaces the bundle's of the same name. A storage
entry is `{name, type, duration, purpose, path, domain}` or the bare name:
`type` is `cookie` (default), `local` or `session`; `name` may be a pattern
(`USER/*`); `{session}` is the session cookie's name, `{answer}` the answer's,
`{lifetime}` its duration.

Every word is a translation of the `consent` domain (fr, en, de, it, ja),
which a site overrides in its own `translations/consent+intl-icu.<locale>.yaml`:

- `features.<name>.label` and `.description` - a feature the translations name
  is known to the panel even when the configuration does not declare it: a
  script that uses it gets its words;
- `categories.<name>.label` and `.description` - the same, read first by the
  detailed section: the bundle has them for `necessary`, `user` and
  `location`. (`features.user.*` still says "the site's working": what `USER`
  means on a page served by `consent-legacy.js`, where it is all of the
  site's cookies.)
- `storage.<purpose>` - what an entry is for (a `purpose` that is no key is
  shown as written);
- `duration.session`, `duration.days`... - the durations (`30d` reads
  "30 jours");
- the panel's own: `text`, `implicit`, `implicit_refuse`, `accept`, `refuse`,
  `details` ("Personnaliser"), `choices`, `accept_all`, `refuse_all`, `save`,
  `close`, `required`, `stored`.

## Look

`bundles/consent/css/consent.css`, themed by custom properties set anywhere
above the circle. The states are classes of the circle (`.ga-cookie`):
`is-floating` (not answered: the panel at the bottom of the screen),
`is-choosing` (the detailed section), `is-refused`.

```css
:root {
    --consent-ink: #1c2b3a;   --consent-soft: #66727f;
    --consent-bg: #fff;       --consent-line: rgba(0, 0, 0, .14);
    --consent-accent: #24447c; --consent-on-accent: #fff;
    --consent-danger: #c0392b; --consent-hover: rgba(0, 0, 0, .07);
    --consent-radius: 14px;   --consent-font: inherit;
    --consent-shadow: 0 12px 30px rgba(0, 0, 0, .16);
    --consent-size: 2rem;
    --consent-gutter: 1rem;   /* the room the panel keeps from the screen's edges (a phone's safe area if wider) */
}
```

Whatever circle or link opens it and wherever a site's stylesheet places it,
the panel stays inside the screen across: `consent.js` moves it by its margins
until it is `--consent-gutter` from the left and right edges (too wide for
the screen, its start stays in sight), and puts it back where the site put it
when it closes. Opened under a circle far from where the visitor is, it is
scrolled into sight up or down only, never sideways.

## In a script

A script does not decide on cookies itself: it subscribes to its feature,
and the panel decides.

```js
// Declares the feature (its switch appears in the panel), runs the first
// function when it is on - now, or the moment the visitor says yes - and
// the second when it goes off. Returns whether it is on now.
window.Consent.use('CHAT', { label: 'The chat' }, () => loadCrisp(), () => window.$crisp?.push(['do', 'chat:hide']));

// A cookie through the answer: nothing is written while ANALYTICS is off.
window.Consent.cookie('ANALYTICS', 'VISITOR_ID', id, 30 * 24 * 3600);
```

`Cookie.set(group, ...)` is superseded the same way: any group a script
writes becomes a feature of the panel, and stays unwritten while it is off -
unless the cookie is declared under a feature (`storage`), whose answer then
decides. Markup can wait too:

```html
<script type="text/plain" data-consent-script="ANALYTICS" data-src="https://..."></script>
<iframe data-consent-script="SOCIAL" data-consent-src="https://www.instagram.com/p/.../embed"></iframe>
<template data-consent-script="LOCATION"><div id="map"></div></template>
<p data-consent-placeholder="LOCATION">Shown until the feature is on. <button type="button" data-consent-open>Cookies</button></p>
```

The scripts of a page run before the library is there (the bundle's comes
first): until then `Consent` answers from what the browser already holds, and
a `Consent.use()` is kept and made once the page's scripts have all run.

The rest of the API:

```js
window.Consent.state('CHAT')    // true | false | null (not answered)
window.Consent.enabled('CHAT')  // what to do now: an implicit feature is on until refused
window.Consent.features()       // every feature, its label and state
window.Consent.refused()        // the site's own cookies refused, e.g. before writing a preference
window.Consent.accept(); window.Consent.refuse(); window.Consent.open(); window.Consent.close();
window.addEventListener('consent:change', (e) => e.detail.features); // {USER: true, CHAT: false...}
document.addEventListener('chat:consent', () => {});               // a feature just turned on
```

Several circles of a page share one panel: it sits under the first the visitor
can see while the question floats, under the one clicked afterwards. A circle
may leave the page with it - a page swap empties the bar it sat in, a script
rebuilds the footer: the next circle's panel takes over, and nothing throws.

## Tests

```bash
npm install && npm test     # node --test, on jsdom: the partial itself, consent.js on @glitchr/flowjs/cookie, consent-legacy.js without it
vendor/bin/phpunit          # the configuration, what the partial prints, every language's words
```

In omnibase's harness (`glitchr/omnibase`, `docker/`), on this checkout:

```bash
docker compose -f compose.yml run --rm -v /path/to/consent:/srv/demo/vendor/omnibase/consent omnibase test consent
```

## License

MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
