<?php

namespace Base\Consent\Tests;

use Base\Consent\Bridge\Symfony\ConsentBundle as BridgeConsentBundle;
use Base\Consent\Bridge\Symfony\DependencyInjection\ConsentExtension as BridgeDiExtension;
use Base\Consent\Bridge\Symfony\Twig\ConsentExtension as SymfonyTwigExtension;
use Base\Consent\Bridge\Twig\ConsentExtension as TwigExtension;
use Base\Consent\ConsentBundle;
use Base\Consent\ConsentPanel;
use Base\Consent\Twig\ConsentExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Since step C8 of the plan "omnibase agnostique" the bundle's Symfony glue is under src/Bridge/Symfony;
 * the names the sites, their tests and lesringostars' Laravel twin knew still answer (D6).
 */
class KeptNamesTest extends TestCase
{
    public function testTheTwigExtensionKeepsItsNameAndItsConstructor(): void
    {
        self::assertSame(SymfonyTwigExtension::class, (new \ReflectionClass(ConsentExtension::class))->getName());
        self::assertTrue(is_subclass_of(ConsentExtension::class, TwigExtension::class));
        self::assertSame(['config', 'urls', 'translator', 'defaults', 'requests'], array_map(static fn (\ReflectionParameter $p): string => $p->getName(), (new \ReflectionMethod(ConsentExtension::class, '__construct'))->getParameters()));
        self::assertSame(ConsentPanel::IGNORE, ConsentExtension::IGNORE);
    }

    public function testTheBundleKeepsItsNameItsFolderAndItsExtension(): void
    {
        $bundle = new ConsentBundle();
        self::assertInstanceOf(BridgeConsentBundle::class, $bundle);
        self::assertSame('ConsentBundle', $bundle->getName());
        self::assertSame(\dirname(__DIR__), $bundle->getPath(), 'templates/, public/ and translations/ at the package root');
        self::assertSame(\dirname(__DIR__).'/src', \dirname((string) (new \ReflectionClass($bundle))->getFileName()), 'omnibase\'s bundle scan reads src/');
        self::assertInstanceOf(BridgeDiExtension::class, $bundle->getContainerExtension());
        self::assertSame('consent', $bundle->getContainerExtension()->getAlias());
        self::assertSame(BridgeDiExtension::class, (new \ReflectionClass(\Base\Consent\DependencyInjection\ConsentExtension::class))->getName());
        self::assertSame(\Base\Consent\Bridge\Symfony\DependencyInjection\ConsentConfiguration::class, (new \ReflectionClass(\Base\Consent\DependencyInjection\ConsentConfiguration::class))->getName());
    }

    public function testTheContainerHasTheExtensionUnderBothIds(): void
    {
        $container = new ContainerBuilder();
        (new BridgeDiExtension())->load([[]], $container);

        self::assertTrue($container->hasDefinition(SymfonyTwigExtension::class));
        self::assertTrue($container->getDefinition(SymfonyTwigExtension::class)->isAutowired());
        self::assertSame(SymfonyTwigExtension::class, (string) $container->getAlias(ConsentExtension::class));
        self::assertSame(ConsentPanel::features(), $container->getParameter('consent.defaults'));
        self::assertSame(require \dirname(__DIR__).'/config/features.php', ConsentPanel::features());
    }
}
