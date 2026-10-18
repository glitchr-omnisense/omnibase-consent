<?php

namespace Base\Consent\Twig;

// The name sites and their Laravel twin knew (new ConsentExtension($config, $urls, $translator, $features)):
// since step C8 of the plan "omnibase agnostique" the class is Base\Consent\Bridge\Symfony\Twig\ConsentExtension,
// the panel itself Base\Consent\ConsentPanel and the Twig functions Base\Consent\Bridge\Twig\ConsentExtension.
if (!class_exists(ConsentExtension::class, false)) {
    class_alias(\Base\Consent\Bridge\Symfony\Twig\ConsentExtension::class, ConsentExtension::class);
}

if (false) {
    /** @deprecated use \Base\Consent\Bridge\Symfony\Twig\ConsentExtension */
    class ConsentExtension extends \Base\Consent\Bridge\Symfony\Twig\ConsentExtension
    {
    }
}
