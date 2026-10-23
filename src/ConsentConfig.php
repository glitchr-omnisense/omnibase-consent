<?php

namespace Base\Consent;

/**
 * A site's `consent:` configuration made whole without Symfony's Config
 * component: what Bridge\Symfony\DependencyInjection\ConsentConfiguration
 * gives the panel under Symfony - every key with its default, a feature
 * written `NAME: ~` or `NAME: false` made an entry, each storage entry
 * written as a name made whole - for a framework that has none of it
 * (Laravel: omnibridge/laravel builds the panel from the Symfony site's
 * config/packages/consent.yaml). The defaults are this class's, and the
 * configuration tree takes them from here (audit "lesringostars face au
 * master plan", R24: formerly the Laravel twin's own copy).
 *
 * One configuration, as written (the `consent` key of the YAML); the
 * validation of the tree (an unknown key, a countdown below 0...) is the
 * Symfony site's.
 */
final class ConsentConfig
{
    /** The configuration's keys, in the tree's order, with their defaults. */
    public const DEFAULTS = [
        'groups' => ['USER'],
        'defaults' => true,
        'version' => null,
        'lifetime' => '180d',
        'countdown' => 10,
        'countdown_action' => 'default',
        'dismiss' => 'none',
        'ignore' => [],
        'reopen' => 'details',
        'position' => 'bottom-left',
        'signals' => self::SIGNALS,
        'privacy_route' => 'app_privacy',
        'features' => [],
        'privacy_route_parameters' => [],
        'erase_on_refuse' => [],
        'options' => [],
    ];

    /** The privacy signals of the browser, none honoured by default. */
    public const SIGNALS = ['gpc' => false, 'dnt' => false];

    /** A feature's keys, in the tree's order, with their defaults (`enabled` aside). */
    public const FEATURE = [
        'implicit' => null,
        'required' => null,
        'on_demand' => null,
        'inherits' => null,
        'erase' => [],
        'storage' => [],
    ];

    /** A storage entry's keys, in the tree's order, with their defaults (`name` aside). */
    public const STORAGE = [
        'type' => 'cookie',
        'duration' => null,
        'purpose' => null,
        'path' => null,
        'domain' => null,
    ];

    /**
     * @param array<string, mixed> $site the site's `consent:` configuration
     *
     * @return array<string, mixed> what the tree gives: the keys written first, in their order, then
     *                              those left unsaid with their defaults, in the tree's order
     */
    public static function normalize(array $site): array
    {
        $config = self::whole($site, self::DEFAULTS);
        $config['signals'] = self::whole((array) $config['signals'], self::SIGNALS);
        $config['features'] = self::features((array) $config['features']);

        return $config;
    }

    /**
     * @param array<string, mixed> $features
     *
     * @return array<string, array<string, mixed>>
     */
    private static function features(array $features): array
    {
        $normalized = [];
        foreach ($features as $name => $feature) {
            // `NAME: false` takes a feature out, `NAME: ~` or `NAME: true` declares it.
            if (\is_bool($feature) || null === $feature) {
                $feature = ['enabled' => false !== $feature];
            }
            $entry = self::whole((array) $feature, ['enabled' => true] + self::FEATURE);
            $entry['storage'] = array_map(self::storage(...), array_values((array) $entry['storage']));
            $normalized[$name] = $entry;
        }

        return $normalized;
    }

    /**
     * @param string|array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private static function storage(string|array $entry): array
    {
        return self::whole(\is_string($entry) ? ['name' => $entry] : $entry, ['name' => null] + self::STORAGE);
    }

    /**
     * The keys given, as given (a null for a key with a default is the default), then the
     * defaults of the others - as Symfony's Config component makes a node whole.
     *
     * @param array<string, mixed> $given
     * @param array<string, mixed> $defaults
     *
     * @return array<string, mixed>
     */
    private static function whole(array $given, array $defaults): array
    {
        $whole = [];
        foreach ($given as $key => $value) {
            if (\array_key_exists($key, $defaults)) {
                $whole[$key] = $value ?? ('privacy_route' === $key ? null : $defaults[$key]);
            }
        }

        return $whole + $defaults;
    }
}
