---
title: Installation
order: 10
---

# Installation

## Requirements

`composer.json` requires PHP 8.2 or later, `omnisense/omnibase` 3.0 or 4.0 and
`twig/twig` 3. Under Symfony the bundle needs `omnibridge/symfony` (suggested; an
omnibase site has it, `BaseBundle` refuses to start without it). In the browser,
the page needs `@glitchr/flowjs` 1.2.0 or later for its `Cookie` global: omnibase's
`base-async` script imports it.

## Under Symfony

```bash
composer require omnibase/consent:dev-main
```

```php
// config/bundles.php
return [
    // ...
    Base\Consent\ConsentBundle::class => ['all' => true],
];
```

`Base\Consent\ConsentBundle` is the name the sites register; it extends
`Base\Consent\Bridge\Symfony\ConsentBundle`, whose root is the package's
(`templates/` is `@Consent`, `public/` is installed under `bundles/consent/`,
`translations/` holds the `consent` domain). The extension registers one service,
the Twig extension (`Base\Consent\Bridge\Symfony\Twig\ConsentExtension`, also known
as `Base\Consent\Twig\ConsentExtension`), and the parameters `consent.config` (the
processed tree), `consent.defaults` (the bundle's features) and one per option
(`consent.countdown`...).

No configuration is needed: the bundle starts from its own features
([Features and the answer](concepts.md)).

## The partial

Include it where the circle goes - a footer, a tool bar:

```twig
{% include '@Consent/_cookie.html.twig' with {place: 'footer'} %}

{# elsewhere on the page #}
<button type="button" data-consent-open>Cookies</button>
```

| Variable | Default | Effect |
|---|---|---|
| `place` | `'footer'` | Tells two circles of a page apart: the panel's id is `ga-cookie-<place>` |
| `floating` | `true` | `false`: this circle never floats by itself (another one of the page does) |
| `icon` | a cookie drawn in SVG | The circle's content (HTML, printed raw) |

The partial prints the stylesheet (`bundles/consent/css/consent.css`), the circle
and its panel, the settings as `<script type="application/json"
data-consent-settings>` (`consent_settings()`), and two deferred scripts,
`consent-legacy.js` then `consent.js`. Anything with `data-consent-open` opens the
panel.

On a page that still packs `@glitchr/cookie` (an older core) instead of
`@glitchr/flowjs`, `consent.js` hands over to `consent-legacy.js`: the panel as it
was before the detailed section, reading the `data-consent-*` attributes of the
circle.
