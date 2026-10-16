<?php

namespace Base\Consent\Twig;

use Base\Routing\AdvancedRouterInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * consent_options(): what the partial prints - the features and their
 * switches, the countdown, the privacy page's URL.
 * consent_settings(): the same, as the configuration @glitchr/flowjs/cookie
 * reads (JSON): the features are its categories, with what each stores.
 */
class ConsentExtension extends AbstractExtension
{
    /** A duration's unit, its seconds and the translation that says it (consent.duration.<key>). */
    private const UNITS = [
        'y' => [31536000, 'years'], 'mo' => [2592000, 'months'], 'w' => [604800, 'weeks'], 'd' => [86400, 'days'],
        'h' => [3600, 'hours'], 'min' => [60, 'minutes'], 'm' => [60, 'minutes'], 's' => [1, 'seconds'],
    ];

    /**
     * What is not the page's: a click there neither answers the question
     * (dismiss) nor closes the panel - Symfony's web debug toolbar (.sf-toolbar,
     * open or folded, around sfwdt<token> and its sfToolbar* parts), a dump()
     * printed in the page, and what a site marks with data-consent-ignore (or
     * the library's data-cookie-ignore).
     */
    public const IGNORE = ['[data-consent-ignore]', '[data-cookie-ignore]', '.sf-toolbar', '[id^="sfwdt"]', '[id^="sfToolbar"]', '.sf-dump'];

    /**
     * @param array<string, mixed>                $config   the site's consent: configuration
     * @param array<string, array<string, mixed>> $defaults the bundle's own features (config/features.php)
     */
    public function __construct(
        #[Autowire('%consent.config%')] private readonly array $config,
        // omnibase's router: the URL in the visitor's language (/datenschutz, not /confidentialite).
        private readonly AdvancedRouterInterface $urls,
        private readonly TranslatorInterface $translator,
        #[Autowire('%consent.defaults%')] private readonly array $defaults = [],
        private readonly ?RequestStack $requests = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('consent_options', $this->options(...)),
            new TwigFunction('consent_settings', $this->json(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * The site's own groups and the features it declares itself, as the
     * partial has always handed them (data-consent-features): what a page
     * whose scripts are from before @glitchr/flowjs/cookie still reads.
     *
     * @return array{labels: array<string, array{label: string, description: ?string}>, groups: list<string>, features: list<array{name: string, label: string, description: ?string, implicit: bool, required: bool, erase: list<string>}>, countdown: int, action: string, dismiss: string, ignore: list<string>, erase: list<string>, privacy: ?string}
     */
    public function options(): array
    {
        $features = [];
        // The site's own groups first: on by default, and on without an answer.
        foreach ($this->groups() as $group) {
            $features[$group] = ['implicit' => true, 'required' => false, 'erase' => []];
        }
        foreach ($this->config['features'] ?? [] as $name => $feature) {
            $name = strtoupper($name);
            if (false === ($feature['enabled'] ?? true)) {
                unset($features[$name]);
                continue;
            }
            $features[$name] = ['implicit' => (bool) ($feature['implicit'] ?? ($features[$name]['implicit'] ?? false)), 'required' => (bool) ($feature['required'] ?? false), 'erase' => $feature['erase'] ?? []];
        }

        $list = [];
        foreach ($features as $name => $feature) {
            $list[] = [
                'name' => $name,
                'label' => $this->text($name, 'label') ?? ucfirst(strtolower($name)),
                'description' => $this->text($name, 'description'),
                'implicit' => $feature['implicit'],
                'required' => $feature['required'],
                'erase' => array_values($feature['erase']),
            ];
        }

        // The names the translations know (consent.features.<name>.*): a
        // script that declares one of them later gets its words.
        $labels = [];
        foreach ($this->knownNames() as $name) {
            if ($label = $this->text($name, 'label')) {
                $labels[strtoupper($name)] = ['label' => $label, 'description' => $this->text($name, 'description')];
            }
        }

        return [
            'labels' => $labels,
            'groups' => array_keys($features),
            'features' => $list,
            'countdown' => (int) $this->config['countdown'],
            'action' => (string) ($this->config['countdown_action'] ?? 'default'),
            'dismiss' => (string) ($this->config['dismiss'] ?? 'none'),
            'ignore' => $this->ignore(),
            'erase' => array_values($this->config['erase_on_refuse']),
            'privacy' => $this->privacy(),
        ];
    }

    /**
     * The configuration of @glitchr/flowjs/cookie: every feature a category
     * (the bundle's own under the site's), its words in the visitor's
     * language, what it stores; the countdown, the answer's lifetime, the
     * privacy signals; and, under "consent", what the bundle's own script
     * reads (the site's own feature, the features split from another, the
     * durations in words).
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $options = \is_array($this->config['options'] ?? null) ? $this->config['options'] : [];
        $lifetime = (string) ($this->config['lifetime'] ?? '180d');
        $record = ($options['record'] ?? []) + ['store' => 'cookie', 'name' => 'cookie-consent'];
        $places = ['{session}' => $this->sessionName(), '{answer}' => \is_string($record['name']) ? $record['name'] : 'cookie-consent', '{lifetime}' => $lifetime];

        $features = $this->features();
        $categories = [];
        $inherits = [];
        $durations = [];
        foreach ($features as $name => $feature) {
            $storage = [];
            foreach ($feature['storage'] as $entry) {
                $answer = '{answer}' === $entry['name'];
                $entry['name'] = strtr((string) $entry['name'], $places);
                $made = ['type' => $answer && 'local' === $record['store'] ? 'local' : ($entry['type'] ?? 'cookie'), 'name' => $entry['name']];
                if (null !== ($entry['duration'] ?? null) && '' !== $entry['duration']) {
                    $made['duration'] = strtr((string) $entry['duration'], $places);
                    $durations[$made['duration']] ??= $this->duration($made['duration']);
                }
                if (null !== ($entry['purpose'] ?? null) && '' !== $entry['purpose']) {
                    $made['purpose'] = $this->word('storage.'.$entry['purpose']) ?? (string) $entry['purpose'];
                }
                foreach (['path', 'domain'] as $field) {
                    if (null !== ($entry[$field] ?? null)) {
                        $made[$field] = (string) $entry[$field];
                    }
                }
                $storage[] = $made;
            }
            // The cookies erased with the feature, known by the start of their name: a line of their own unless
            // one of the feature's entries already takes them in (erased by it, and said by its own words); the
            // site's own cookies (a feature's GROUP/..., an entry of another feature) said to be the site's, the
            // rest a third party's.
            foreach ($feature['erase'] as $prefix) {
                $prefix = strtr((string) $prefix, $places);
                if ('' === $prefix || self::covers($storage, $prefix)) {
                    continue;
                }
                $own = $this->own($prefix, $name, $features, $places);
                $storage[] = array_filter(['type' => 'cookie', 'name' => $prefix.'*', 'purpose' => $this->word($own ? 'storage.own' : 'storage.third_party')], static fn ($value) => null !== $value);
            }

            $categories[] = [
                'key' => $name,
                'label' => $this->category($name, 'label') ?? ucfirst(strtolower($name)),
                'description' => $this->category($name, 'description'),
                'default' => $feature['implicit'],
                'required' => $feature['required'],
                'onDemand' => $feature['on_demand'],
                'storage' => $storage,
            ];
            if ($feature['inherits']) {
                $inherits[$name] = strtoupper($feature['inherits']);
            }
        }

        $countdown = (int) $this->config['countdown'];
        $action = (string) ($this->config['countdown_action'] ?? 'default');
        $settings = [
            'reopen' => $this->config['reopen'] ?? 'banner',
            'dismiss' => $this->config['dismiss'] ?? 'none',
            'ignore' => $this->ignore(),
            'countdown' => $countdown > 0 ? ['duration' => $countdown, 'action' => $action] : false,
            'record' => ['lifetime' => $lifetime],
            'signals' => ['gpc' => $this->config['signals']['gpc'] ?? false, 'dnt' => $this->config['signals']['dnt'] ?? false],
            'categories' => $categories,
            'texts' => $this->texts($action),
            'consent' => ['primary' => $this->primary(), 'inherits' => (object) $inherits, 'durations' => (object) array_filter($durations)],
        ];
        foreach (['version', 'position'] as $key) {
            if (null !== ($this->config[$key] ?? null) && '' !== $this->config[$key]) {
                $settings[$key] = (string) $this->config[$key];
            }
        }

        // The site's free options underneath: what is said by name above wins.
        return array_replace_recursive($options, $settings);
    }

    /**
     * The selectors of what is not the page's: the bundle's own, then the site's (consent.ignore).
     *
     * @return list<string>
     */
    private function ignore(): array
    {
        $site = array_filter(array_map(static fn ($selector): string => trim((string) $selector), (array) ($this->config['ignore'] ?? [])), static fn (string $selector): bool => '' !== $selector);

        return array_values(array_unique([...self::IGNORE, ...$site]));
    }

    /** settings(), for a <script type="application/json">: no "<" left to end the script with. */
    public function json(): string
    {
        return json_encode($this->settings(), \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }

    /**
     * Whether one of these cookie entries already takes in every cookie whose
     * name starts with $prefix: the prefix itself, or a pattern ending in *
     * that the prefix matches (crisp-client* takes in crisp-client-session).
     *
     * @param list<array<string, mixed>> $entries
     */
    private static function covers(array $entries, string $prefix): bool
    {
        foreach ($entries as $entry) {
            if ('cookie' !== ($entry['type'] ?? 'cookie')) {
                continue;
            }
            $name = (string) $entry['name'];
            if ($name === $prefix) {
                return true;
            }
            if (str_ends_with($name, '*') && preg_match('/^'.str_replace('\*', '.*', preg_quote($name, '/')).'$/s', $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the cookies under $prefix are the site's own: a feature's group
     * (USER/..., ANALYTICS/... - what Cookie.set() writes), or a cookie of the
     * site's domain another feature declares (the session, LANG...).
     *
     * @param array<string, array{storage: list<array<string, mixed>>}> $features
     * @param array<string, string>                                     $places
     */
    private function own(string $prefix, string $feature, array $features, array $places): bool
    {
        // GROUP/ and what follows; a bare name is no group's (an "intercom" prefix is Intercom's, whatever the feature is called).
        if (str_contains($prefix, '/') && isset($features[strtoupper(explode('/', $prefix, 2)[0])])) {
            return true;
        }
        foreach ($features as $name => $other) {
            if ($name === $feature) {
                continue;
            }
            $entries = array_map(static fn (array $entry): array => ['name' => strtr((string) $entry['name'], $places)] + $entry, array_filter($other['storage'], static fn (array $entry): bool => null === ($entry['domain'] ?? null)));
            if (self::covers(array_values($entries), $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> the site's own groups, upper case; USER when none is named */
    private function groups(): array
    {
        return array_values(array_unique(array_map('strtoupper', ($this->config['groups'] ?? []) ?: ['USER'])));
    }

    /** The site's own feature: the first group the site did not take out. */
    private function primary(): string
    {
        $features = $this->features();
        foreach ($this->groups() as $group) {
            if (isset($features[$group])) {
                return $group;
            }
        }

        return $this->groups()[0];
    }

    /**
     * Every feature, by name: the bundle's own (consent.defaults), the site's
     * groups, what the site declares - over the bundle's, field by field -
     * and the names the translations know, on demand.
     *
     * @return array<string, array{implicit: bool, required: bool, on_demand: bool, inherits: ?string, erase: list<string>, storage: list<array<string, mixed>>}>
     */
    private function features(): array
    {
        $blank = ['implicit' => null, 'required' => null, 'on_demand' => null, 'inherits' => null, 'erase' => [], 'storage' => []];
        $features = [];
        if ($this->config['defaults'] ?? true) {
            foreach ($this->defaults as $name => $feature) {
                $features[strtoupper($name)] = $feature + $blank;
            }
        }
        foreach ($this->groups() as $position => $group) {
            $features[$group] ??= $blank;
            $features[$group]['implicit'] ??= true;
            $features[$group]['on_demand'] = false;
            if (0 === $position) {
                $features[$group]['erase'] = array_merge($features[$group]['erase'], array_values($this->config['erase_on_refuse'] ?? []));
            }
        }
        $out = [];
        foreach ($this->config['features'] ?? [] as $name => $feature) {
            $name = strtoupper($name);
            if (false === ($feature['enabled'] ?? true)) {
                unset($features[$name]);
                $out[$name] = true;
                continue;
            }
            $base = $features[$name] ?? $blank;
            // Declared by the site, it is listed, unless the site says on demand itself.
            $base['on_demand'] = false;
            foreach (['implicit', 'required', 'on_demand', 'inherits'] as $field) {
                if (null !== ($feature[$field] ?? null)) {
                    $base[$field] = $feature[$field];
                }
            }
            $base['erase'] = array_values(array_unique(array_merge($base['erase'], $feature['erase'] ?? [])));
            // An entry of the site's replaces the bundle's of the same name.
            foreach ($feature['storage'] ?? [] as $entry) {
                $base['storage'] = array_values(array_filter($base['storage'], static fn (array $kept): bool => ($kept['type'] ?? 'cookie') !== ($entry['type'] ?? 'cookie') || $kept['name'] !== $entry['name']));
                $base['storage'][] = $entry;
            }
            $features[$name] = $base;
        }
        // The names the translations know: a script that declares one of them gets its words.
        foreach ($this->knownNames() as $known) {
            if (!isset($out[strtoupper($known)])) {
                $features[strtoupper($known)] ??= ['on_demand' => true] + $blank;
            }
        }

        $list = [];
        foreach ($features as $name => $feature) {
            $list[$name] = [
                'implicit' => (bool) $feature['implicit'],
                'required' => (bool) $feature['required'],
                'on_demand' => (bool) $feature['on_demand'],
                'inherits' => isset($feature['inherits']) && isset($features[strtoupper($feature['inherits'])]) ? (string) $feature['inherits'] : null,
                'erase' => array_values($feature['erase']),
                'storage' => array_values($feature['storage']),
            ];
        }

        return $list;
    }

    /**
     * Every word @glitchr/flowjs/cookie writes in the panel (the partial has
     * them too: these reword a panel that outlives its page).
     *
     * @return array<string, string|array{html: string}>
     */
    private function texts(string $action): array
    {
        $texts = [
            'label' => $this->word('label'),
            'accept' => $this->word('accept'),
            'refuse' => $this->word('refuse'),
            'customize' => $this->word('details'),
            'detailsTitle' => $this->word('choices'),
            'acceptAll' => $this->word('accept_all'),
            'refuseAll' => $this->word('refuse_all'),
            'save' => $this->word('save'),
            'close' => $this->word('close'),
            'required' => $this->word('required'),
            'countdown' => $this->word('refuse' === $action ? 'implicit_refuse' : 'implicit', ['count' => '{count}']),
        ];
        $texts = array_filter($texts, static fn (?string $words): bool => null !== $words);
        $texts['text'] = ['html' => $this->translator->trans('text', ['privacy' => $this->privacy() ?? 'none'], 'consent')];

        return $texts;
    }

    /**
     * A duration in the visitor's words: "30d" is "30 jours", "session" what
     * consent.duration.session says, a bare number its seconds in the
     * largest unit that divides them. Null when nothing says it.
     */
    private function duration(string $duration): ?string
    {
        if (!preg_match('/^\s*(\d+(?:\.\d+)?)\s*([a-z]*)\s*$/i', $duration, $match)) {
            return $this->word('duration.'.strtolower(trim($duration)));
        }
        $count = (float) $match[1];
        $unit = strtolower($match[2]);
        if ('' === $unit) {
            $unit = 's';
            foreach (self::UNITS as $name => [$seconds]) {
                if ($count >= $seconds && 0.0 === fmod($count, $seconds)) {
                    $count /= $seconds;
                    $unit = $name;
                    break;
                }
            }
        }
        if (!isset(self::UNITS[$unit])) {
            return null;
        }

        return $this->word('duration.'.self::UNITS[$unit][1], ['count' => $count == (int) $count ? (int) $count : $count]);
    }

    private function sessionName(): string
    {
        try {
            $request = $this->requests?->getMainRequest();
            if ($request?->hasSession()) {
                return $request->getSession()->getName();
            }
        } catch (\Throwable) {
            // No session here (a command, a test without one): PHP's own name.
        }

        return session_name() ?: 'PHPSESSID';
    }

    /** @return list<string> the feature names under consent.features.* and consent.categories.* in the current catalogue (bundle's and site's) */
    private function knownNames(): array
    {
        if (!$this->translator instanceof TranslatorBagInterface) {
            return [];
        }
        $names = [];
        for ($catalogue = $this->translator->getCatalogue(); $catalogue; $catalogue = $catalogue->getFallbackCatalogue()) {
            foreach (['consent', 'consent'.MessageCatalogueInterface::INTL_DOMAIN_SUFFIX] as $domain) {
                foreach (array_keys($catalogue->all($domain)) as $key) {
                    if (preg_match('/^(?:features|categories)\.([^.]+)\.label$/', $key, $match)) {
                        $names[$match[1]] = true;
                    }
                }
            }
        }

        return array_keys($names);
    }

    /** consent.features.<name>.<field>, null when neither the bundle nor the site says it. */
    private function text(string $name, string $field): ?string
    {
        return $this->word('features.'.strtolower($name).'.'.$field);
    }

    /**
     * A feature's words in the detailed section: consent.categories.<name>.<field>,
     * else consent.features.<name>.<field>. The first are for the features
     * whose meaning the detailed section changed - USER, once all of the
     * site's own cookies, is the visitor's preferences beside NECESSARY and
     * LOCATION - and leave the second as the pages from before it show them.
     */
    private function category(string $name, string $field): ?string
    {
        return $this->word('categories.'.strtolower($name).'.'.$field) ?? $this->text($name, $field);
    }

    /**
     * A key of the consent domain, null when neither the bundle nor the site says it.
     *
     * @param array<string, mixed> $parameters
     */
    private function word(string $key, array $parameters = []): ?string
    {
        $text = $this->translator->trans($key, $parameters, 'consent');

        return $text === $key || '' === $text ? null : $text;
    }

    private function privacy(): ?string
    {
        $route = $this->config['privacy_route'] ?? null;
        if (!$route) {
            return null;
        }

        try {
            return $this->urls->generate($route, $this->config['privacy_route_parameters'] ?? []);
        } catch (\Throwable) {
            // No such page on this site: the panel goes without the link.
            return null;
        }
    }
}
