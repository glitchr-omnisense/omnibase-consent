<?php

/*
 * omnibase/consent where there is no framework: run by omniscient/omnibase's
 * docker/bin/bare with Composer's autoloader alone (tests/BareTest.php), it
 * builds the panel as another framework would - the site's configuration as
 * ConsentConfiguration gives it by default, symfony/translation's own
 * Translator on the bundle's French words (read by symfony/yaml), a closure for the privacy page's
 * address and one for the session's name - and prints it through the Twig
 * functions in a plain Twig environment. Nothing of the framework may be loaded.
 */

use Base\Consent\Bridge\Twig\ConsentExtension;
use Base\Consent\ConsentPanel;
use Symfony\Component\Translation\Loader\ArrayLoader as Words;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Symfony\Component\Yaml\Yaml;
use Twig\Loader\ArrayLoader;

$translator = new Translator('fr');
// The words read by symfony/yaml and handed as an array: symfony/translation's file loaders track
// their file with symfony/config's FileResource, the framework's.
$translator->addLoader('array', new Words());
$translator->addResource('array', Yaml::parseFile(dirname(__DIR__, 2).'/translations/consent+intl-icu.fr.yaml'), 'fr', 'consent+intl-icu');

// What ConsentConfiguration gives a site that says nothing (tests/DependencyInjection/ConsentConfigurationTest).
$config = [
    'groups' => ['USER'], 'defaults' => true, 'version' => null, 'position' => 'bottom-left', 'lifetime' => '180d',
    'countdown' => 10, 'countdown_action' => 'default', 'dismiss' => 'none', 'reopen' => 'details', 'ignore' => [],
    'signals' => ['gpc' => false, 'dnt' => false], 'features' => [], 'options' => [], 'erase_on_refuse' => [],
    'privacy_route' => 'app_privacy', 'privacy_route_parameters' => [],
];
$panel = new ConsentPanel(
    $config,
    $translator,
    ConsentPanel::features(),
    static fn (string $route, array $parameters): string => 'app_privacy' === $route ? '/confidentialite' : throw new InvalidArgumentException($route),
    static fn (): string => 'LRSID',
);

$twig = new Environment(new ArrayLoader(['panel' => '{% set o = consent_options() %}{{ o.privacy }}|{{ o.groups|join(",") }}|<script type="application/json">{{ consent_settings() }}</script>']));
$twig->addExtension(new ConsentExtension($panel));
$printed = $twig->render('panel');

$settings = $panel->settings();
$categories = array_column($settings['categories'], null, 'key');

return [
    'categories' => array_keys($categories),
    'necessary' => $categories['NECESSARY']['label'],
    'session' => in_array('LRSID', array_column($categories['NECESSARY']['storage'], 'name'), true),
    'countdown' => $settings['countdown'],
    'privacy' => str_contains($settings['texts']['text']['html'], '/confidentialite'),
    'printed' => substr($printed, 0, strpos($printed, '<script')),
    'json' => json_decode(substr($printed, strpos($printed, '>') + 1, -strlen('</script>')), true) === json_decode($panel->json(), true),
];
