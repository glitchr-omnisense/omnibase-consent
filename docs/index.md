# omnibase/consent

The cookie answer of the omnibase sites, written once: a small circle that opens
a panel - what the site keeps, **accept**, **refuse**, **customize** - instead of
a copy of the same controller in every site.

The visitor answers **feature by feature**. Whatever a page keeps in the browser
belongs to a feature: what the site cannot work without (`NECESSARY`, always on),
the visitor's preferences (`USER`), the time zone (`LOCATION`), a chat, a measure
of audience, Instagram. "Personnaliser" opens the detailed section: one row per
feature, its switch, and what it keeps - each cookie by name, how long, what for.
**The answer is enforced**: a refused feature writes nothing, what it had kept is
erased, and a tracker never starts without a yes.

The work in the browser is done by
[`@glitchr/flowjs/cookie`](https://github.com/glitchr-studio/flowjs/blob/main/docs/cookie.md)
(the `Cookie` global omnibase loads on every page): the answer and where it is
kept, asking again, the countdown, the detailed section, the erasing. The bundle
gives it the site's configuration and words, the markup, and `window.Consent` for
the scripts. The PHP side (`Base\Consent\ConsentPanel`) needs no framework; under
Symfony it is a bundle.

Use it on any omnibase site that keeps something in the visitor's browser beyond
what it cannot work without - that is, every site with a preference, a time zone,
a chat or a measure of audience.

## How this manual is organised

- [Installation](installation.md): requirements, the bundle, the partial, the
  JavaScript it needs.
- [Without Symfony](without-symfony.md): `ConsentPanel`, `ConsentConfig`, the Twig
  functions in any environment, the Laravel twin.
- [Features and the answer](concepts.md): what a feature is, the bundle's own,
  what each kind does, the countdown, the dismissal, the reopening, the privacy
  signals, where the answer is kept.
- [In a script and in the markup](usage-scripts.md): `window.Consent`, the events,
  the elements that wait for a feature.
- [Look and words](appearance.md): the stylesheet's properties and states, the
  translations.
- [Configuration reference](configuration.md): every option of `consent:`.
- [Testing](testing.md): the JavaScript and PHP suites, the harness.
