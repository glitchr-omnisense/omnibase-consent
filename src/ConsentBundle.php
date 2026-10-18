<?php

namespace Base\Consent;

use Base\Traits\SingletonTrait;

/**
 * The name the sites register (config/bundles.php) and omnibase's bundle scan
 * reads (a class Base\<Name>\<Name>Bundle, its folder src/): the bundle
 * itself is Base\Consent\Bridge\Symfony\ConsentBundle, since step C8 of the
 * plan "omnibase agnostique".
 */
class ConsentBundle extends Bridge\Symfony\ConsentBundle
{
    // Its own singleton slot, as every concrete bundle (see AbstractBaseBundle's constructor).
    use SingletonTrait;

    public function __construct()
    {
        parent::__construct();
    }
}
