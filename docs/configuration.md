---
title: Configuration reference
order: 40
---

# Configuration reference

`config/packages/consent.yaml`, processed by
`Base\Consent\Bridge\Symfony\DependencyInjection\ConsentConfiguration` (its defaults
are `Base\Consent\ConsentConfig`'s, which a host without Symfony reads the same
file with). The tree, dumped with Symfony's reference dumper in the core's harness:

```yaml
consent:

    # The site's own cookie groups (omnibase writes USER/*): on by default, on when the visitor does not answer. Shorthand for features with implicit: true. The first one is the site's own answer (Consent.refused(), the circle's colour).
    groups:

        # Default:
        - USER

    # Start from the bundle's own features (config/features.php: NECESSARY, USER, LOCATION and the on-demand ones). false: only what is declared here. One of them is taken out with `features: {NAME: false}`.
    defaults:             true

    # The version of the features: changed, every visitor is asked again (a feature added that does more than the answer covered).
    version:              null

    # How long an answer counts before the visitor is asked again: "180d", "13mo", "1y", seconds.
    lifetime:             180d

    # Seconds before a visitor who has not answered gets the answer of countdown_action (counted down in the panel). 0: no countdown, the panel waits for an answer.
    countdown:            10

    # What the countdown answers when the time is up - default: each feature takes its default (the implicit ones on, the rest off); accept: every one on; refuse: every one off but the required ones.
    countdown_action:     default # One of "default"; "accept"; "refuse"

    # What a click elsewhere answers while the question floats - none: nothing, the question stays until the visitor answers or the countdown runs out; default, accept, refuse: that answer, given without a word the visitor sees.
    dismiss:              none # One of "default"; "accept"; "refuse"; "none"

    # Selectors of elements whose clicks are not on the page, added to the bundle's own (Symfony's web debug toolbar, a dump(), [data-consent-ignore]): a click there never answers by dismiss and never closes the panel.
    ignore:               []

    # What the circle and the "Cookies" links show once answered - details: the detailed section, each switch as the answer was recorded (the countdown's included), with "Tout accepter", "Tout refuser" and "Fermer"; banner: the question again, the switches one click further.
    reopen:               details # One of "banner"; "details"

    # Where the question floats: bottom-left, bottom-right, bottom-center, top-left, top-right, top-center - or any word of the site's own stylesheet (data-consent-position).
    position:             bottom-left

    # The privacy signals of the browser the site honours: true (every feature that is not required is off without asking), or the list of the features it covers.
    signals:

        # Global Privacy Control.
        gpc:                  false

        # Do Not Track.
        dnt:                  false

    # The route of the privacy page linked from the panel; null, or a route that does not exist, leaves the link out.
    privacy_route:        app_privacy

    # What the visitor has a say over, by name (the cookie group: CHAT, ANALYTICS, SOCIAL...), each with its own switch in the detailed section. Scripts may also declare theirs at run time (window.Consent.use()). Labels: translations consent.features.<name>.label / .description.
    features:

        # Prototype
        name:
            enabled:              true

            # On until refused, and on when the visitor does not answer (the countdown). Keep it for what the site needs to work; a tracker must wait for a yes.
            implicit:             null

            # Always on, its switch locked: the session, a basket.
            required:             null

            # Known (its words and what it stores are ready) but listed only once a script or an element of the page uses it, and never a reason to ask again.
            on_demand:            null

            # The feature it was split from: a visitor who answered for that one is not asked again for this one, which takes the same answer.
            inherits:             null

            # Name prefixes of cookies deleted when it is turned off (e.g. crisp-client). Each is a line of the detailed section unless one of its storage entries takes it in; said to be the site's own when it is a group's (ANALYTICS/) or another feature's entry, a third party's otherwise.
            erase:                []

            # What it keeps in the browser: listed in the detailed section, erased when it is refused, and written through the answer (Cookie.cookies / .local / .session).
            storage:

                # Prototype
                -

                    # The name, or a pattern with * (USER/*, _pk_*). {session} is the session cookie's name, {answer} the one the answer is kept under.
                    name:                 ~ # Required

                    # cookie, local (localStorage) or session (sessionStorage).
                    type:                 cookie # One of "cookie"; "local"; "session"

                    # How long it is kept: "30d", "12h", "13mo", "1y", "session", seconds; {lifetime} is the answer's.
                    duration:             null

                    # What it is for: a key of the translations (consent.storage.<purpose>), else the words themselves.
                    purpose:              null
                    path:                 null

                    # Of a third party's cookie, needed to erase it.
                    domain:               null

    # Parameters of privacy_route, e.g. {slug: vie-privee} for a page served by a generic route.
    privacy_route_parameters:

        # Prototype
        name:                 ~

    # Name prefixes of cookies also deleted when the site's own feature is refused, e.g. a chat's ("crisp-client"). The groups' own cookies always are.
    erase_on_refuse:      []

    # Anything else @glitchr/flowjs/cookie takes, handed as it is: record: {store, name}, cookie: {sameSite, domain}, undeclared, debug, variables...
    options:              []
```

## Options

| Option | Type | Default | Effect |
|---|---|---|---|
| `groups` | list of names | `[USER]` | The site's own groups: features implicit unless said otherwise; the first is the site's own feature (`Consent.refused()`, the circle's colour) |
| `defaults` | bool | `true` | Start from the bundle's features (`config/features.php`); `false`: only those declared here |
| `version` | string | `null` | Changed, every visitor is asked again |
| `lifetime` | string | `180d` | How long an answer counts: `180d`, `13mo`, `1y`, seconds |
| `countdown` | int, 0 or more | `10` | Seconds before no answer is the answer of `countdown_action`; `0`: wait for an answer |
| `countdown_action` | `default`, `accept`, `refuse` | `default` | What the countdown answers ([Features and the answer](concepts.md#the-question-the-countdown-and-the-dismissal)) |
| `dismiss` | `default`, `accept`, `refuse`, `none` | `none` | What a click elsewhere answers while the question floats |
| `ignore` | list of selectors | `[]` | More elements whose clicks are not on the page (after Symfony's toolbar, a `dump()`, `[data-consent-ignore]`, `[data-cookie-ignore]`) |
| `reopen` | `banner`, `details` | `details` | What the circle and the "Cookies" links show once answered |
| `position` | string | `bottom-left` | Where the question floats |
| `signals.gpc`, `signals.dnt` | bool or list of features | `false` | The browser's privacy signals honoured |
| `privacy_route` | string | `app_privacy` | The privacy page's route, linked from the question; `null` or a missing route leaves the link out |
| `privacy_route_parameters` | map | `{}` | Its parameters (`{slug: vie-privee}`) |
| `erase_on_refuse` | list of name prefixes | `[]` | Cookies also erased when the site's own feature is refused |
| `features` | map of name => feature | `{}` | The site's features, laid over the bundle's ([below](#a-feature)) |
| `options` | any | `[]` | Anything else `@glitchr/flowjs/cookie` takes, handed under what the bundle sets: `record: {store, name}`, `cookie: {sameSite, domain}`, `undeclared`, `debug`, `variables`... |

### A feature

`features.<NAME>` is `false` (taken out), `~` or `true` (declared as it is), or:

| Key | Type | Default | Effect |
|---|---|---|---|
| `enabled` | bool | `true` | `false` takes the feature out |
| `implicit` | bool | `null` (the bundle's, else off) | On until refused, on without an answer |
| `required` | bool | `null` | Always on, switch locked |
| `on_demand` | bool | `null` (a feature the site declares is listed) | Listed only once used |
| `inherits` | string | `null` | The feature it was split from |
| `erase` | list of name prefixes | `[]` | Cookies deleted when it goes off; added to the bundle's |
| `storage` | list | `[]` | What it keeps; an entry replaces the bundle's of the same type and name |

### A storage entry

A bare name, or:

| Key | Type | Default | Effect |
|---|---|---|---|
| `name` | string, required | | The name, or a pattern with `*` (`USER/*`, `_pk_*`); `{session}`, `{answer}` |
| `type` | `cookie`, `local`, `session` | `cookie` | A cookie, `localStorage` or `sessionStorage` |
| `duration` | string | `null` | `30d`, `12h`, `13mo`, `1y`, `session`, seconds; `{lifetime}` |
| `purpose` | string | `null` | A key of `consent.storage.*`, else the words themselves |
| `path` | string | `null` | The cookie's path |
| `domain` | string | `null` | A third party's cookie's domain, needed to erase it |

An `erase` prefix (and `erase_on_refuse`, the site's own feature's) is a line of the
detailed section, `<prefix>*`, unless one of the feature's `storage` entries already
takes it in (`crisp-client*` for `crisp-client`). The line says whose cookies they
are: the site's own when the prefix is a feature's group (`ANALYTICS/`) or a cookie
another feature declares (`LANG`), a third party's otherwise.

## An example

```yaml
# config/packages/consent.yaml
consent:
    countdown: 10
    countdown_action: refuse
    privacy_route: app_page
    privacy_route_parameters: { slug: vie-privee }
    features:
        LOCATION: false          # the time zone then goes with USER
        CHAT: { erase: [crisp-client] }
        ANALYTICS:
            storage:
                - { name: '_pk_*', duration: 13mo, purpose: 'Matomo' }
                - { name: visits, type: local, purpose: analytics_visits }
        BASKET: { required: true, storage: [basket] }
```

## Parameters

Under Symfony the extension sets `consent.config` (the processed tree, which the Twig
extension reads), `consent.defaults` (`ConsentPanel::features()`) and one parameter
per node (`consent.countdown`, `consent.signals.gpc`...).
