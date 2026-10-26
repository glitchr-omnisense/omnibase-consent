<?php

/*
 * omnibase/consent without the framework: the classes of the bundle that load
 * with Composer's autoloader alone - no kernel, no container, no bundle booted
 * (plan "omnibase agnostique", step C8). tests/BareTest.php loads each of them
 * through omnisense/omnibase's docker/bin/bare, in a PHP process apart from
 * PHPUnit's, and fails if a class or a file of the framework is loaded on the
 * way (the Symfony bundles and bridges, dependency-injection, config,
 * http-kernel, http-foundation, routing, security-*, form, console; not the
 * standalone Symfony components - translation and its contracts -, nor Twig).
 *
 * The answer itself (ConsentPanel: the features, what each keeps, the
 * countdown, the words) and its Twig functions (Bridge\Twig). What needs
 * Symfony is under src/Bridge/Symfony; the classes left at their former names
 * for the sites (BareTest::KEPT) are the bridge's.
 *
 * The list only grows; a class leaves it only by a decision written down in
 * the plan. Sorted. 2 classes on 2026-10-18; 3 on 2026-10-24 (ConsentConfig, the
 * configuration made whole without the tree: audit R24).
 */
return [
    'Base\Consent\Bridge\Twig\ConsentExtension',
    'Base\Consent\ConsentConfig',
    'Base\Consent\ConsentPanel',
];
