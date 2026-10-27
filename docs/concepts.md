---
title: Features and the answer
order: 20
---

# Features and the answer

## A feature

A feature is what the visitor answers for, under an upper-case name - a cookie
group (`USER`, `CHAT`, `ANALYTICS`). In the browser it is a category of
`@glitchr/flowjs/cookie`: one yes or no, a switch in the detailed section, and what
it keeps. A feature has four flags and two lists:

| Field | Effect |
|---|---|
| `required` | Always on, its switch locked: never asked (the session, a basket) |
| `implicit` | On until refused, and on when the visitor does not answer. Keep it for what the site needs to work; a tracker waits for a yes |
| `on_demand` | Known (its words and what it keeps are ready) but listed only once a script or an element of the page uses it, and never a reason to ask again |
| `inherits` | The feature it was split from: a visitor who answered for that one is not asked again, this one takes the same answer |
| `erase` | Name prefixes of cookies deleted when it goes off (`crisp-client`) |
| `storage` | What it keeps: `{name, type, duration, purpose, path, domain}` entries, listed in the detailed section, erased at a refusal, written through the answer |

A feature that is none of `required`, `implicit` is off until the visitor says yes.

## The bundle's features

`config/features.php`, what every site starts from (`consent.defaults: true`):

| Feature | Kind | Keeps |
|---|---|---|
| `NECESSARY` | required | `{session}` (the session cookie, for the session), `LANG` (session), `*csrf-token*` (session), `REMEMBERME`, `{answer}` (the answer itself, for `{lifetime}`) |
| `USER` | implicit (the site's own group) | `USER/INFO` (30 days: the browser's language, calendar, light or dark theme), `USER/THEME/*`, `USER/LAST_VISIT` |
| `LOCATION` | implicit, inherits `USER` | `LOCATION/TIMEZONE` (30 days) |
| `ANALYTICS` | on demand | `ANALYTICS/VISITOR_ID` (13 months) |
| `CHAT` | on demand | |
| `SOCIAL` | on demand | |

In a storage name, `{session}` is the session cookie's name, `{answer}` the name
the answer is kept under (`cookie-consent` unless `options.record.name`), and in a
duration `{lifetime}` is the answer's (`consent.lifetime`).

## How a site's features are made

`ConsentPanel` resolves the features in this order:

1. the bundle's (unless `defaults: false`);
2. the site's groups (`consent.groups`, `[USER]` by default): implicit unless said
   otherwise, never on demand; the first is **the site's own feature** (what
   `Consent.refused()` answers for, the circle's colour), and `erase_on_refuse`
   adds to its `erase`;
3. the site's `features`, laid over the bundle's field by field: a feature the
   site declares is listed (not on demand, unless it says so); `NAME: false` takes
   one out; its `erase` adds to the bundle's; a storage entry replaces the
   bundle's of the same type and name;
4. any feature the translations name (`consent.features.<name>.*`) and nobody
   took out, on demand: a script that declares it later gets its words.

A feature `inherits` only when the feature it names exists. Scripts add features as
they run ([In a script](usage-scripts.md)).

## The question, the countdown and the dismissal

A visitor who has not answered sees the panel float (at `consent.position`,
`bottom-left` by default) with a countdown of `consent.countdown` seconds (10).
When the time is up, `consent.countdown_action` answers:

| `countdown_action` | No answer is |
|---|---|
| `default` | each feature its default: the implicit ones on, the rest off |
| `accept` | every feature on |
| `refuse` | every feature off but the required ones; the panel says so ("c'est refusé dans 10 s", `implicit_refuse`) |

`countdown: 0` waits for an answer. A click elsewhere while the question floats
answers `consent.dismiss`: `none` (the default: nothing, the question stays),
`default`, `accept` or `refuse`. A click on Symfony's web debug toolbar
(`.sf-toolbar`, `[id^="sfwdt"]`, `[id^="sfToolbar"]`), a `dump()` (`.sf-dump`), or
anything marked `data-consent-ignore` (or the library's `data-cookie-ignore`) is not
on the page: it neither answers nor closes the panel; `consent.ignore` adds
selectors.

## Once answered

The circle and every `data-consent-open` element open, with `reopen: details` (the
default), the detailed section with each switch as the answer was recorded,
"Tout accepter", "Tout refuser", "Enregistrer mes choix" and "Fermer"; with
`reopen: banner`, the question again. A refused feature writes nothing - through
`Cookie.set()`, `Cookie.cookies`, `Cookie.local`, `Cookie.session` - and what it had
kept is erased; accepted again, it writes again.

The answer counts for `consent.lifetime` (`180d`; `13mo`, `1y` or seconds are read
too). Changing `consent.version` asks every visitor again. It is kept in the cookie
`cookie-consent` (`options.record` changes its store and name), and in
`localStorage` `cookie/<GROUP>` as `@glitchr/cookie` kept it: the answers given
before the library are taken over, and nobody is asked twice.

## The browser's privacy signals

`consent.signals.gpc` (Global Privacy Control) and `.dnt` (Do Not Track): `true`
turns every feature that is not required off without asking when the browser sends
the signal; a list of features limits it to those; `false` (the default) ignores
it.
