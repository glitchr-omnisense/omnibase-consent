<?php

namespace Base\Consent\Tests\DependencyInjection;

use Base\Consent\DependencyInjection\ConsentConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConsentConfigurationTest extends TestCase
{
    /** @param array<string, mixed> $config */
    public static function process(array $config = []): array
    {
        return (new Processor())->processConfiguration(new ConsentConfiguration(), [$config]);
    }

    public function testASiteThatSaysNothingKeepsWhatItHad(): void
    {
        $config = self::process();

        self::assertSame(['USER'], $config['groups']);
        self::assertSame(10, $config['countdown']);
        self::assertSame('default', $config['countdown_action']);
        self::assertSame('none', $config['dismiss'], 'a click elsewhere answers nothing: the question waits for the visitor, or the countdown');
        self::assertSame([], $config['ignore']);
        self::assertSame('banner', $config['reopen']);
        self::assertSame('bottom-left', $config['position']);
        self::assertSame('180d', $config['lifetime']);
        self::assertNull($config['version']);
        self::assertTrue($config['defaults']);
        self::assertSame(['gpc' => false, 'dnt' => false], $config['signals']);
        self::assertSame([], $config['features']);
        self::assertSame([], $config['options']);
        self::assertSame('app_privacy', $config['privacy_route']);
    }

    public function testAFeatureIsDeclaredTakenOutOrGivenWhatItStores(): void
    {
        $config = self::process(['features' => [
            'CHAT' => null,
            'LOCATION' => false,
            'ANALYTICS' => ['storage' => ['_pk_*', ['name' => 'visits', 'type' => 'local', 'duration' => '13mo', 'purpose' => 'Matomo']]],
            'BASKET' => ['required' => true, 'erase' => ['basket-']],
        ]]);

        self::assertTrue($config['features']['CHAT']['enabled']);
        self::assertNull($config['features']['CHAT']['implicit'], 'what a site leaves unsaid is the bundle\'s to say');
        self::assertFalse($config['features']['LOCATION']['enabled']);
        self::assertSame(['name' => '_pk_*', 'type' => 'cookie', 'duration' => null, 'purpose' => null, 'path' => null, 'domain' => null], $config['features']['ANALYTICS']['storage'][0]);
        self::assertSame('local', $config['features']['ANALYTICS']['storage'][1]['type']);
        self::assertTrue($config['features']['BASKET']['required']);
        self::assertSame(['basket-'], $config['features']['BASKET']['erase']);
    }

    public function testTheCountdownAnswersOneOfThreeWays(): void
    {
        self::assertSame('refuse', self::process(['countdown_action' => 'refuse'])['countdown_action']);
        $this->expectException(InvalidConfigurationException::class);
        self::process(['countdown_action' => 'later']);
    }
}
