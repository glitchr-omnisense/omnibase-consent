<?php

/*
 * The features every omnibase site starts from (consent.defaults: true), in
 * the shape of consent.features: what the core keeps in the visitor's
 * browser, under the name the visitor answers for. A site adds its own,
 * rewrites one of these, or takes one out (`features: {LOCATION: false}`) in
 * its config/packages/consent.yaml.
 *
 * The words are the translations': consent.features.<name>.label and
 * .description, consent.storage.<purpose>.
 */
return [
    // What the site cannot work without: never asked, its switch locked on.
    'NECESSARY' => [
        'required' => true,
        'storage' => [
            ['name' => '{session}', 'duration' => 'session', 'purpose' => 'session'],
            ['name' => 'LANG', 'duration' => 'session', 'purpose' => 'language'],
            ['name' => '*csrf-token*', 'duration' => 'session', 'purpose' => 'csrf'],
            ['name' => 'REMEMBERME', 'purpose' => 'remember'],
            ['name' => '{answer}', 'duration' => '{lifetime}', 'purpose' => 'answer'],
        ],
    ],
    // The site's own group (consent.groups): the visitor's preferences.
    'USER' => [
        'implicit' => true,
        'storage' => [
            ['name' => 'USER/INFO', 'duration' => '30d', 'purpose' => 'info'],
            ['name' => 'USER/THEME/*', 'purpose' => 'theme'],
            ['name' => 'USER/LAST_VISIT', 'purpose' => 'visit'],
        ],
    ],
    // The time zone (a page that reads a position declares what it keeps of
    // it here too): split from USER, whose answer it takes for the visitors
    // who gave it before.
    'LOCATION' => [
        'implicit' => true,
        'inherits' => 'USER',
        'storage' => [
            ['name' => 'LOCATION/TIMEZONE', 'duration' => '30d', 'purpose' => 'timezone'],
        ],
    ],
    // Known, and listed only on the sites that use them (a script's
    // Consent.use(), an element of the page) or declare them.
    'ANALYTICS' => [
        'on_demand' => true,
        'storage' => [
            ['name' => 'ANALYTICS/VISITOR_ID', 'duration' => '13mo', 'purpose' => 'visitor'],
        ],
    ],
    'CHAT' => ['on_demand' => true],
    'SOCIAL' => ['on_demand' => true],
];
