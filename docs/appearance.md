---
title: Look and words
order: 35
---

# Look and words

## The stylesheet

`bundles/consent/css/consent.css`, themed by custom properties set anywhere above
the circle:

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

The states are classes of the circle (`.ga-cookie`): `is-floating` (not answered:
the panel at the bottom of the screen), `is-choosing` (the detailed section),
`is-refused`. The panel is `.ga-cookie-panel`; the question `.ga-cookie-summary`;
the detailed section `.ga-cookie-section`, its rows `.ga-cookie-category`.
`consent.position` (`bottom-left`, `bottom-right`, `bottom-center`, `top-left`,
`top-right`, `top-center`, or a word of the site's stylesheet) is written on the panel
as `data-consent-position`.

## The words

Every word is a translation of the `consent` domain (`translations/consent+intl-icu.<locale>.yaml`:
fr, en, de, it, ja), which a site overrides in its own
`translations/consent+intl-icu.<locale>.yaml`.

| Key | Where |
|---|---|
| `label`, `accepted`, `refused` | The circle's label and titles |
| `text` | The question (`{privacy}`: the privacy page's address, or `none`) |
| `implicit`, `implicit_refuse` | The countdown's line (`{count}`), for `countdown_action` other than `refuse`, and for `refuse` |
| `accept`, `refuse`, `details`, `choices`, `accept_all`, `refuse_all`, `save`, `close`, `required`, `stored` | The panel's buttons and titles |
| `duration.session`, `duration.seconds`, `.minutes`, `.hours`, `.days`, `.weeks`, `.months`, `.years` | The durations in words (`30d` reads "30 jours") |
| `storage.<purpose>` | What a storage entry is for (`session`, `language`, `csrf`, `remember`, `answer`, `info`, `theme`, `visit`, `timezone`, `visitor`; `own` and `third_party` for an `erase` prefix). A `purpose` that is no key is shown as written |
| `categories.<name>.label`, `.description` | A feature's words in the detailed section (the bundle has `necessary`, `user`, `location`) |
| `features.<name>.label`, `.description` | A feature's words, read after `categories.*` (the bundle has `user`, `chat`, `analytics`, `social`); a feature the translations name is known to the panel even when the configuration does not declare it |

(`features.user.*` still says "the site's working": what `USER` means on a page
served by `consent-legacy.js`, where it is all of the site's cookies.)
