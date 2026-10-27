---
title: Testing
order: 60
---

# Testing

## The suites

```bash
npm install && npm test     # node --test, on jsdom
vendor/bin/phpunit
```

`tests/js/consent.test.mjs` runs `consent.js` on `@glitchr/flowjs/cookie` in jsdom
(`tests/js/page.mjs` builds a page with the partial's markup), `tests/js/legacy.test.mjs`
`consent-legacy.js` without it. The PHP suite checks the configuration tree
(`tests/DependencyInjection/ConsentConfigurationTest.php`), `ConsentConfig::normalize()`
against it (`tests/ConsentConfigTest.php`), what the partial prints and every
language's words (`tests/Twig/ConsentExtensionTest.php`), the classes loaded without
the framework (`tests/BareTest.php`, over `tests/Fondation.php`) and the former names
(`tests/KeptNamesTest.php`).

The JavaScript suite, run in a `node:22-alpine` container on this checkout
(`node --test tests/js/*.test.mjs`), ends:

```
1..27
# tests 27
# suites 0
# pass 27
# fail 0
# cancelled 0
# skipped 0
# todo 0
```

## In the core's harness

omnisense/omnibase's harness (`docker/`) runs the PHP suite with this checkout
mounted in place of the package:

```bash
docker compose -f compose.yml run --rm -v /path/to/consent:/srv/demo/vendor/omnibase/consent omnibase test consent
```

## In a site's tests

A functional test of a page that includes the partial finds the circle
(`.ga-cookie[data-consent]`), the panel (`#ga-cookie-<place>`) and the settings
(`script[data-consent-settings]`, JSON: `categories`, `countdown`, `texts`...).
What a visitor answers is the browser's: test it in a browser (headless Chrome),
or with the jsdom page of `tests/js/page.mjs`.
