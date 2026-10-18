<?php

namespace Base\Consent\DependencyInjection;

// The name sites and tests knew: since step C8 of the plan "omnibase agnostique" the class is
// Base\Consent\Bridge\Symfony\DependencyInjection\ConsentConfiguration, with the bundle's other Symfony glue under src/Bridge/Symfony.
if (!class_exists(ConsentConfiguration::class, false)) {
    class_alias(\Base\Consent\Bridge\Symfony\DependencyInjection\ConsentConfiguration::class, ConsentConfiguration::class);
}

if (false) {
    /** @deprecated use \Base\Consent\Bridge\Symfony\DependencyInjection\ConsentConfiguration */
    class ConsentConfiguration extends \Base\Consent\Bridge\Symfony\DependencyInjection\ConsentConfiguration
    {
    }
}
