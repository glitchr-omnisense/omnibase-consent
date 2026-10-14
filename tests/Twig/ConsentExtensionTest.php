<?php

namespace Base\Consent\Tests\Twig;

use Base\Consent\Tests\DependencyInjection\ConsentConfigurationTest;
use Base\Consent\Twig\ConsentExtension;
use Base\Routing\AdvancedRouterInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * What the partial prints: the configuration @glitchr/flowjs/cookie reads
 * (consent_settings()) - the bundle's own features under the site's, in the
 * visitor's words - and what it has always handed its script (consent_options()).
 */
class ConsentExtensionTest extends TestCase
{
    private const LOCALES = ['fr', 'en', 'de', 'it', 'ja'];

    /** @param array<string, mixed> $config */
    private function extension(array $config = [], string $locale = 'fr'): ConsentExtension
    {
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (self::LOCALES as $each) {
            $translator->addResource('yaml', \dirname(__DIR__, 2).'/translations/consent+intl-icu.'.$each.'.yaml', $each, 'consent+intl-icu');
        }
        $router = $this->createStub(AdvancedRouterInterface::class);
        $router->method('generate')->willReturn('/confidentialite');

        return new ConsentExtension(ConsentConfigurationTest::process($config), $router, $translator, require \dirname(__DIR__, 2).'/config/features.php');
    }

    /** @return array<string, array<string, mixed>> the categories, by key */
    private static function categories(array $settings): array
    {
        return array_column($settings['categories'], null, 'key');
    }

    public function testASiteThatSaysNothingGetsTheBundlesFeatures(): void
    {
        $settings = $this->extension()->settings();
        $categories = self::categories($settings);

        self::assertSame(['NECESSARY', 'USER', 'LOCATION', 'ANALYTICS', 'CHAT', 'SOCIAL'], array_keys($categories));
        self::assertTrue($categories['NECESSARY']['required']);
        self::assertFalse($categories['NECESSARY']['onDemand']);
        self::assertSame('Le fonctionnement du site', $categories['NECESSARY']['label']);
        self::assertTrue($categories['USER']['default'], 'the site\'s own: on until refused');
        self::assertFalse($categories['USER']['required']);
        self::assertSame('Vos préférences', $categories['USER']['label']);
        self::assertTrue($categories['LOCATION']['default']);
        self::assertFalse($categories['LOCATION']['onDemand']);
        foreach (['ANALYTICS', 'CHAT', 'SOCIAL'] as $name) {
            self::assertTrue($categories[$name]['onDemand'], $name.' is known, and listed only where something uses it');
            self::assertFalse($categories[$name]['default']);
        }

        // What each keeps, named, with its duration and its purpose in the visitor's words.
        $stored = array_column($categories['NECESSARY']['storage'], null, 'name');
        self::assertArrayHasKey(session_name(), $stored, 'the session cookie, under the name PHP gives it');
        self::assertSame('session', $stored[session_name()]['duration']);
        self::assertSame(['type' => 'cookie', 'name' => 'cookie-consent', 'duration' => '180d', 'purpose' => 'Votre réponse à cette question.'], $stored['cookie-consent']);
        self::assertSame(['USER/INFO', 'USER/THEME/*', 'USER/LAST_VISIT'], array_column($categories['USER']['storage'], 'name'));
        self::assertSame([['type' => 'cookie', 'name' => 'LOCATION/TIMEZONE', 'duration' => '30d', 'purpose' => 'Votre fuseau horaire.']], $categories['LOCATION']['storage']);
        self::assertArrayNotHasKey('duration', $stored['REMEMBERME'], 'a duration nobody knows is not invented');

        self::assertSame(['duration' => 10, 'action' => 'default'], $settings['countdown']);
        self::assertSame(['lifetime' => '180d'], $settings['record']);
        self::assertSame('bottom-left', $settings['position']);
        self::assertSame('banner', $settings['reopen']);
        self::assertSame('default', $settings['dismiss']);
        self::assertArrayNotHasKey('version', $settings);
        self::assertSame('USER', $settings['consent']['primary']);
        self::assertSame(['LOCATION' => 'USER'], (array) $settings['consent']['inherits'], 'split from USER: its answer is taken over');
        self::assertSame(['session' => 'le temps de la visite', '180d' => '180 jours', '30d' => '30 jours', '13mo' => '13 mois'], (array) $settings['consent']['durations']);

        self::assertSame('Personnaliser', $settings['texts']['customize']);
        self::assertSame('Tout accepter', $settings['texts']['acceptAll']);
        self::assertSame('Toujours actif', $settings['texts']['required']);
        self::assertSame('Sans réponse de votre part, c\'est accepté dans {count} s.', $settings['texts']['countdown'], 'the seconds go where the library puts them');
        self::assertStringContainsString('<a href="/confidentialite">Confidentialité</a>', $settings['texts']['text']['html']);
    }

    public function testASiteAddsRewritesAndTakesOutFeatures(): void
    {
        $settings = $this->extension([
            'version' => '2026-10',
            'lifetime' => '13mo',
            'countdown' => 0,
            'position' => 'bottom-right',
            'reopen' => 'details',
            'signals' => ['gpc' => true, 'dnt' => ['ANALYTICS']],
            'erase_on_refuse' => ['legacy-'],
            'features' => [
                'LOCATION' => false,
                'chat' => ['erase' => ['crisp-client']],
                'ANALYTICS' => ['storage' => [['name' => '_pk_*', 'duration' => '13mo', 'purpose' => 'Matomo'], ['name' => 'ANALYTICS/VISITOR_ID', 'duration' => '1y', 'purpose' => 'visitor']]],
                'BASKET' => ['required' => true, 'storage' => [['name' => 'basket', 'type' => 'local']]],
                'MAP' => ['implicit' => true, 'inherits' => 'USER'],
            ],
        ])->settings();
        $categories = self::categories($settings);

        self::assertArrayNotHasKey('LOCATION', $categories, 'taken out');
        self::assertFalse($categories['CHAT']['onDemand'], 'declared by the site: listed');
        self::assertSame(['type' => 'cookie', 'name' => 'crisp-client*', 'purpose' => 'Les cookies que ce service dépose.'], $categories['CHAT']['storage'][0]);
        self::assertFalse($categories['ANALYTICS']['onDemand']);
        self::assertFalse($categories['ANALYTICS']['default'], 'a tracker waits for a yes');
        self::assertSame([
            ['type' => 'cookie', 'name' => '_pk_*', 'duration' => '13mo', 'purpose' => 'Matomo'],
            ['type' => 'cookie', 'name' => 'ANALYTICS/VISITOR_ID', 'duration' => '1y', 'purpose' => 'Un identifiant tiré au hasard, pour compter les visites.'],
        ], $categories['ANALYTICS']['storage'], 'the site\'s entry replaces the bundle\'s of the same name; words that are no key stay as written');
        self::assertTrue($categories['BASKET']['required']);
        self::assertSame('Basket', $categories['BASKET']['label'], 'no translation: its name');
        self::assertSame([['type' => 'local', 'name' => 'basket']], $categories['BASKET']['storage']);
        self::assertTrue($categories['MAP']['default']);
        self::assertSame(['MAP' => 'USER'], (array) $settings['consent']['inherits']);
        self::assertContains('legacy-*', array_column($categories['USER']['storage'], 'name'), 'erase_on_refuse goes with the site\'s own feature');

        self::assertFalse($settings['countdown']);
        self::assertSame('2026-10', $settings['version']);
        self::assertSame(['lifetime' => '13mo'], $settings['record']);
        self::assertSame('bottom-right', $settings['position']);
        self::assertSame('details', $settings['reopen']);
        self::assertSame(['gpc' => true, 'dnt' => ['ANALYTICS']], $settings['signals']);
        self::assertSame('13mo', array_column($categories['NECESSARY']['storage'], null, 'name')['cookie-consent']['duration']);
        self::assertSame('1 an', ((array) $settings['consent']['durations'])['1y']);
    }

    public function testWithoutTheBundlesFeaturesOnlyTheSitesAreListed(): void
    {
        $categories = self::categories($this->extension(['defaults' => false, 'groups' => ['SITE', 'USER'], 'features' => ['USER' => ['storage' => ['USER/*']]]])->settings());

        self::assertSame(['SITE', 'USER'], array_keys(array_filter($categories, static fn (array $category): bool => !$category['onDemand'])));
        self::assertTrue($categories['SITE']['default']);
        self::assertSame([['type' => 'cookie', 'name' => 'USER/*']], $categories['USER']['storage']);
        self::assertTrue($categories['NECESSARY']['onDemand'], 'the names the translations know keep their words, for a script that uses them');
        self::assertFalse($categories['NECESSARY']['required']);
    }

    public function testTheCountdownSaysWhatItAnswersAndFreeOptionsGoThrough(): void
    {
        $settings = $this->extension([
            'countdown' => 30,
            'countdown_action' => 'refuse',
            'options' => ['debug' => true, 'record' => ['store' => 'local', 'name' => 'answer'], 'position' => 'overridden', 'variables' => ['--consent-accent' => '#000']],
        ], 'en')->settings();

        self::assertSame(['duration' => 30, 'action' => 'refuse'], $settings['countdown']);
        self::assertSame('With no answer from you, it counts as refused in {count} s.', $settings['texts']['countdown']);
        self::assertTrue($settings['debug']);
        self::assertSame(['store' => 'local', 'name' => 'answer', 'lifetime' => '180d'], $settings['record']);
        self::assertSame('bottom-left', $settings['position'], 'what the configuration says by name wins');
        self::assertSame(['--consent-accent' => '#000'], $settings['variables']);
        $answer = array_column(self::categories($settings)['NECESSARY']['storage'], null, 'name')['answer'];
        self::assertSame('local', $answer['type'], 'the answer is declared where it is kept');
        self::assertSame('180 days', ((array) $settings['consent']['durations'])['180d']);
    }

    public function testThePrintedConfigurationCannotEndItsScript(): void
    {
        $json = $this->extension()->json();

        self::assertStringNotContainsString('<', $json);
        self::assertSame('Vos préférences', array_column(json_decode($json, true)['categories'], 'label', 'key')['USER']);
        self::assertStringContainsString('<a href="/confidentialite">', json_decode($json, true)['texts']['text']['html']);
    }

    public function testThePartialStillHandsWhatAPageFromBeforeTheLibraryReads(): void
    {
        $options = $this->extension()->options();

        self::assertSame(['USER'], $options['groups']);
        self::assertSame([['name' => 'USER', 'label' => 'Le fonctionnement du site', 'description' => 'Votre session, votre langue, votre thème, votre fuseau horaire.', 'implicit' => true, 'required' => false, 'erase' => []]], $options['features'], 'the bundle\'s own features are the library\'s: this list is the site\'s, with the words it had - USER is all of the site\'s cookies there');
        self::assertSame(['ANALYTICS', 'CHAT', 'SOCIAL', 'USER'], array_values(array_intersect(['ANALYTICS', 'CHAT', 'SOCIAL', 'USER', 'NECESSARY', 'LOCATION'], array_keys($options['labels']))), 'and the words it had for the features scripts declare');
        self::assertSame(10, $options['countdown']);
        self::assertSame('default', $options['action']);
        self::assertSame('/confidentialite', $options['privacy']);
        self::assertArrayHasKey('ANALYTICS', $options['labels']);

        $options = $this->extension(['features' => ['CHAT' => ['erase' => ['crisp-client']], 'USER' => false]])->options();
        self::assertSame(['CHAT'], $options['groups']);
        self::assertSame(['crisp-client'], $options['features'][0]['erase']);
        self::assertFalse($options['features'][0]['implicit']);
    }

    public function testEveryLanguageSaysEveryWord(): void
    {
        $keys = [];
        foreach (self::LOCALES as $locale) {
            $translator = new Translator($locale);
            $translator->addLoader('yaml', new YamlFileLoader());
            $translator->addResource('yaml', \dirname(__DIR__, 2).'/translations/consent+intl-icu.'.$locale.'.yaml', $locale, 'consent+intl-icu');
            $keys[$locale] = array_keys($translator->getCatalogue($locale)->all('consent+intl-icu'));
            sort($keys[$locale]);
        }
        foreach (self::LOCALES as $locale) {
            self::assertSame($keys['fr'], $keys[$locale], $locale);
            $settings = $this->extension([], $locale)->settings();
            foreach ($settings['categories'] as $category) {
                self::assertNotSame(ucfirst(strtolower($category['key'])), $category['label'], $locale.': '.$category['key'].' has its words');
                foreach ($category['storage'] as $entry) {
                    self::assertMatchesRegularExpression('/\s|[^\x00-\x7F]/', $entry['purpose'] ?? '', $locale.': '.$entry['name'].' says what it is for');
                    if (isset($entry['duration'])) {
                        self::assertArrayHasKey($entry['duration'], (array) $settings['consent']['durations'], $locale.': '.$entry['duration'].' has its words');
                    }
                }
            }
        }
    }
}
