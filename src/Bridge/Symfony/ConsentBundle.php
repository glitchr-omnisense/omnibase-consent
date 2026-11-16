<?php

namespace Base\Consent\Bridge\Symfony;

use Base\Bundle\AbstractBaseBundle;
use Base\Traits\SingletonTrait;

/**
 * The cookie answer shared by the omnibase sites, under Symfony: a Twig
 * partial (@Consent/_cookie.html.twig), its script and its stylesheet
 * (bundles/consent/), and the `consent:` configuration. The answer itself
 * (Base\Consent\ConsentPanel) and its Twig functions (Bridge\Twig) load
 * without the framework (tests/Fondation.php, plan "omnibase agnostique",
 * step C8).
 *
 * Sites register it as Base\Consent\ConsentBundle, the subclass that keeps
 * that name.
 */
class ConsentBundle extends AbstractBaseBundle
{
    use SingletonTrait;

    public function __construct()
    {
        parent::__construct();
    }

    /** Modern layout: the bundle root is the package root (templates/, public/, translations/). */
    public function getPath(): string
    {
        return \dirname(__DIR__, 3);
    }

    /** Beside this class, whichever name registered the bundle (Symfony looks beside the registered class). */
    protected function getContainerExtensionClass(): string
    {
        return DependencyInjection\ConsentExtension::class;
    }
}
