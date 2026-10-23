<?php

namespace Base\Consent\Tests;

use Base\Consent\ConsentConfig;
use Base\Consent\Tests\DependencyInjection\ConsentConfigurationTest;
use PHPUnit\Framework\TestCase;

/**
 * The configuration made whole without Symfony (ConsentConfig::normalize(),
 * what a framework other than Symfony builds the panel from) is the one the
 * configuration tree gives Symfony's panel: the same keys, values and order.
 */
final class ConsentConfigTest extends TestCase
{
    /** @return iterable<string, array{array<string, mixed>}> */
    public static function configurations(): iterable
    {
        yield 'nothing said' => [[]];
        yield 'lesringostars' => [[
            'groups' => ['USER'],
            'countdown' => 10,
            'privacy_route' => 'widget_page',
            'privacy_route_parameters' => ['slug' => 'politique-de-confidentialite'],
            'features' => [
                'NECESSARY' => ['storage' => [
                    ['name' => 'trusted_device', 'duration' => '30d', 'purpose' => 'trusted_device'],
                    ['name' => 'PHPUSERID', 'duration' => 'session', 'purpose' => 'user_tracker'],
                ]],
                'ANALYTICS' => ['storage' => [['name' => 'ANALYTICS/VISITOR_ID', 'duration' => '13mo', 'purpose' => 'visitor']]],
                'CHAT' => null,
            ],
        ]];
        yield 'everything' => [[
            'groups' => ['USER', 'SITE'],
            'defaults' => false,
            'version' => '2',
            'lifetime' => '13mo',
            'countdown' => 0,
            'countdown_action' => 'refuse',
            'dismiss' => 'accept',
            'ignore' => ['.chat'],
            'reopen' => 'banner',
            'position' => 'top-right',
            'signals' => ['gpc' => true],
            'privacy_route' => null,
            'features' => [
                'LOCATION' => false,
                'SOCIAL' => true,
                'ANALYTICS' => ['implicit' => false, 'on_demand' => true, 'inherits' => 'USER', 'erase' => ['_pk_'], 'storage' => ['_pk_*', ['name' => 'visits', 'type' => 'local', 'domain' => '.matomo.example']]],
                'BASKET' => ['enabled' => false, 'required' => true],
            ],
            'erase_on_refuse' => ['crisp-client'],
            'options' => ['debug' => true],
        ]];
    }

    /**
     * @dataProvider configurations
     *
     * @param array<string, mixed> $site
     */
    public function testTheSameAsTheTreeGives(array $site): void
    {
        self::assertSame(ConsentConfigurationTest::process($site), ConsentConfig::normalize($site));
    }
}
