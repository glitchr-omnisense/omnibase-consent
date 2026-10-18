<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Base\Consent\Bridge\Symfony\Twig\ConsentExtension;

/*
 * One service: the partial's Twig functions, built from the container (the
 * site's consent: configuration, the translator, omnibase's router, the
 * request stack). Base\Consent\ConsentPanel, the answer itself, is plain PHP
 * that the extension builds (plan "omnibase agnostique", step C8). The id the
 * sites knew, Base\Consent\Twig\ConsentExtension, is an alias of it.
 */
return function (ContainerConfigurator $configurator) {
    $services = $configurator->services();
    $services->defaults()
        ->autowire(true)
        ->autoconfigure(true)
        ->public(false);

    $services->set(ConsentExtension::class);
    $services->alias('Base\\Consent\\Twig\\ConsentExtension', ConsentExtension::class);
};
