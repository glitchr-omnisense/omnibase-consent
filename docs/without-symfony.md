---
title: Without Symfony
order: 16
---

# Without Symfony

The answer itself loads without the framework: `Base\Consent\ConsentPanel` (the
features and what each keeps, the countdown, the words, `options()`, `settings()`,
`json()`), `Base\Consent\ConsentConfig` (a configuration made whole) and
`Base\Consent\Bridge\Twig\ConsentExtension` (the partial's two Twig functions).
They need Twig and a translator (symfony/translation's contract), nothing of
Symfony's framework; `tests/BareTest.php` loads them with Composer's autoloader
alone (`tests/Fondation.php`).

## The panel

```php
use Base\Consent\Bridge\Twig\ConsentExtension;
use Base\Consent\ConsentConfig;
use Base\Consent\ConsentPanel;

$panel = new ConsentPanel(
    ConsentConfig::normalize($site),            // the site's `consent:` array, made whole
    $translator,                                // the `consent` domain in the visitor's language
    ConsentPanel::features(),                   // the bundle's own features (config/features.php)
    fn (string $route, array $parameters) => $router->url($route, $parameters),   // the privacy page's address
    fn () => session_name(),                    // the session cookie's name; null: PHP's own
);
$twig->addExtension(new ConsentExtension($panel));
```

| Constructor argument | Type | Effect |
|---|---|---|
| `$config` | array | The configuration as `ConsentConfiguration` processes it, or as `ConsentConfig::normalize()` makes it whole |
| `$translator` | `Symfony\Contracts\Translation\TranslatorInterface` | The words (domain `consent+intl-icu`); when it is also a `TranslatorBagInterface`, the catalogue's names tell which features have words |
| `$defaults` | array | The bundle's features, laid under the site's (`consent.defaults: true`) |
| `$urls` | `?Closure(string, array): string` | A route's address; none: no privacy link |
| `$session` | `?Closure(): ?string` | The session cookie's name (`{session}` in a storage entry) |

| Method | Returns |
|---|---|
| `ConsentPanel::features()` | The bundle's features (`config/features.php`) |
| `options()` | What the partial prints: `labels`, `groups`, `features`, `countdown`, `action`, `dismiss`, `ignore`, `erase`, `privacy` |
| `settings()` | The configuration of `@glitchr/flowjs/cookie`: `reopen`, `dismiss`, `ignore`, `countdown`, `record`, `signals`, `categories`, `texts`, `consent`, `position`, `version`, and `options` underneath |
| `json()` | `settings()` as JSON with no `<` left, for a `<script type="application/json">` |

`ConsentConfig::normalize(array $site)` gives what the Symfony tree gives: the keys
written first, then the others with their defaults (`ConsentConfig::DEFAULTS`,
`FEATURE`, `STORAGE`, `SIGNALS`, the tree's own defaults), a feature written
`NAME: ~` or `NAME: false` made an entry, a storage entry written as a name made
whole. It does not validate (an unknown key, a negative countdown): the Symfony
site's tree does.

`Bridge\Twig\ConsentExtension` adds `consent_options()` and `consent_settings()`
(safe HTML); `panel()` gives the panel back.

## Run in the core's harness

`tests/bare/consent-without-framework.php` builds the panel on the bundle's French
words (symfony/translation's own `Translator`, the YAML read by symfony/yaml), a
closure answering `/confidentialite` for `app_privacy` and one answering `LRSID`,
and renders the two functions in a plain Twig environment. Run with the harness's
autoloader and this checkout's classes (`tests/bare/autoload.php`), it returns:

```
array (
  'categories' =>
  array (
    0 => 'NECESSARY',
    1 => 'USER',
    2 => 'LOCATION',
    3 => 'ANALYTICS',
    4 => 'CHAT',
    5 => 'SOCIAL',
  ),
  'necessary' => 'Le fonctionnement du site',
  'session' => true,
  'countdown' =>
  array (
    'duration' => 10,
    'action' => 'default',
  ),
  'privacy' => true,
  'printed' => '/confidentialite|USER|',
  'json' => true,
)
```

and the panel's first category, `settings()['categories'][0]`:

```json
{
    "key": "NECESSARY",
    "label": "Le fonctionnement du site",
    "description": "Votre session, la langue du site, la protection des formulaires, votre réponse sur les cookies.",
    "default": false,
    "required": true,
    "onDemand": false,
    "storage": [
        { "type": "cookie", "name": "LRSID", "duration": "session", "purpose": "Votre session : votre connexion, un formulaire en cours." },
        { "type": "cookie", "name": "LANG", "duration": "session", "purpose": "La langue dans laquelle le site vous est montré." },
        { "type": "cookie", "name": "*csrf-token*", "duration": "session", "purpose": "Un jeton qui protège l'envoi des formulaires." },
        { "type": "cookie", "name": "REMEMBERME", "purpose": "« Rester connecté » : votre compte reconnu d'une visite à l'autre." },
        { "type": "cookie", "name": "cookie-consent", "duration": "180d", "purpose": "Votre réponse à cette question." }
    ]
}
```

(the JSON re-indented; `{session}` became `LRSID`, `{answer}` `cookie-consent`,
`{lifetime}` `180d`).

## Laravel

omnibridge/laravel's `Omnibridge\Laravel\View\OmnibaseExtensions` adds the extension
to the views' Twig environment when `omnibridge.consent.config` is set (an array,
or the path of the Symfony site's `config/packages/consent.yaml`, whose `consent`
key it reads) and the package is installed: `ConsentConfig::normalize()` on it,
the page's catalogue translator, `ConsentPanel::features()`, and Laravel's
addresses (`Urls::path()`) for the privacy page. The session's name is not given
there (`{session}` is PHP's `session_name()`).

## Under Symfony

`src/Bridge/Symfony` holds the bundle, its configuration and extension, and the
Twig extension built from the container (the site's `consent:` tree, the
translator, omnibase's router for the privacy page in the visitor's language, the
main request's session for its cookie's name). The former names still answer:
`Base\Consent\ConsentBundle`, `Base\Consent\Twig\ConsentExtension` (the service's
id an alias), `Base\Consent\DependencyInjection\*`.
