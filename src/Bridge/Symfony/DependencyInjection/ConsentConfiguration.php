<?php

namespace Base\Consent\Bridge\Symfony\DependencyInjection;

use Base\Bundle\AbstractBaseConfiguration;
use Base\Consent\ConsentConfig as D;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * The site's `consent:` configuration under Symfony; its defaults are
 * Base\Consent\ConsentConfig's, which makes the same configuration whole
 * without Symfony (a framework other than Symfony reads the site's file).
 */
class ConsentConfiguration extends AbstractBaseConfiguration
{
    private bool $childrenDeclared = false;

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = $this->getTreeBuilder();
        if ($this->childrenDeclared) {
            return $treeBuilder;
        }
        $this->childrenDeclared = true;

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('groups')
                    ->info('The site\'s own cookie groups (omnibase writes USER/*): on by default, on when the visitor does not answer. Shorthand for features with implicit: true. The first one is the site\'s own answer (Consent.refused(), the circle\'s colour).')
                    ->scalarPrototype()->end()
                    ->defaultValue(D::DEFAULTS['groups'])
                ->end()
                ->booleanNode('defaults')->defaultValue(D::DEFAULTS['defaults'])
                    ->info('Start from the bundle\'s own features (config/features.php: NECESSARY, USER, LOCATION and the on-demand ones). false: only what is declared here. One of them is taken out with `features: {NAME: false}`.')->end()
                ->scalarNode('version')->defaultValue(D::DEFAULTS['version'])
                    ->info('The version of the features: changed, every visitor is asked again (a feature added that does more than the answer covered).')->end()
                ->scalarNode('lifetime')->defaultValue(D::DEFAULTS['lifetime'])
                    ->info('How long an answer counts before the visitor is asked again: "180d", "13mo", "1y", seconds.')->end()
                ->integerNode('countdown')->min(0)->defaultValue(D::DEFAULTS['countdown'])
                    ->info('Seconds before a visitor who has not answered gets the answer of countdown_action (counted down in the panel). 0: no countdown, the panel waits for an answer.')->end()
                ->enumNode('countdown_action')->values(['default', 'accept', 'refuse'])->defaultValue(D::DEFAULTS['countdown_action'])
                    ->info('What the countdown answers when the time is up - default: each feature takes its default (the implicit ones on, the rest off); accept: every one on; refuse: every one off but the required ones.')->end()
                ->enumNode('dismiss')->values(['default', 'accept', 'refuse', 'none'])->defaultValue(D::DEFAULTS['dismiss'])
                    ->info('What a click elsewhere answers while the question floats - none: nothing, the question stays until the visitor answers or the countdown runs out; default, accept, refuse: that answer, given without a word the visitor sees.')->end()
                ->arrayNode('ignore')
                    ->info('Selectors of elements whose clicks are not on the page, added to the bundle\'s own (Symfony\'s web debug toolbar, a dump(), [data-consent-ignore]): a click there never answers by dismiss and never closes the panel.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->enumNode('reopen')->values(['banner', 'details'])->defaultValue(D::DEFAULTS['reopen'])
                    ->info('What the circle and the "Cookies" links show once answered - details: the detailed section, each switch as the answer was recorded (the countdown\'s included), with "Tout accepter", "Tout refuser" and "Fermer"; banner: the question again, the switches one click further.')->end()
                ->scalarNode('position')->defaultValue(D::DEFAULTS['position'])
                    ->info('Where the question floats: bottom-left, bottom-right, bottom-center, top-left, top-right, top-center - or any word of the site\'s own stylesheet (data-consent-position).')->end()
                ->arrayNode('signals')->addDefaultsIfNotSet()
                    ->info('The privacy signals of the browser the site honours: true (every feature that is not required is off without asking), or the list of the features it covers.')
                    ->children()
                        ->variableNode('gpc')->defaultValue(D::SIGNALS['gpc'])->info('Global Privacy Control.')->end()
                        ->variableNode('dnt')->defaultValue(D::SIGNALS['dnt'])->info('Do Not Track.')->end()
                    ->end()
                ->end()
                ->scalarNode('privacy_route')->defaultValue(D::DEFAULTS['privacy_route'])
                    ->info('The route of the privacy page linked from the panel; null, or a route that does not exist, leaves the link out.')->end()
                ->arrayNode('features')
                    ->info('What the visitor has a say over, by name (the cookie group: CHAT, ANALYTICS, SOCIAL...), each with its own switch in the detailed section. Scripts may also declare theirs at run time (window.Consent.use()). Labels: translations consent.features.<name>.label / .description.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->canBeDisabled()
                        ->children()
                            ->booleanNode('implicit')->defaultValue(D::FEATURE['implicit'])->info('On until refused, and on when the visitor does not answer (the countdown). Keep it for what the site needs to work; a tracker must wait for a yes.')->end()
                            ->booleanNode('required')->defaultValue(D::FEATURE['required'])->info('Always on, its switch locked: the session, a basket.')->end()
                            ->booleanNode('on_demand')->defaultValue(D::FEATURE['on_demand'])->info('Known (its words and what it stores are ready) but listed only once a script or an element of the page uses it, and never a reason to ask again.')->end()
                            ->scalarNode('inherits')->defaultValue(D::FEATURE['inherits'])->info('The feature it was split from: a visitor who answered for that one is not asked again for this one, which takes the same answer.')->end()
                            ->arrayNode('erase')->info('Name prefixes of cookies deleted when it is turned off (e.g. crisp-client). Each is a line of the detailed section unless one of its storage entries takes it in; said to be the site\'s own when it is a group\'s (ANALYTICS/) or another feature\'s entry, a third party\'s otherwise.')->scalarPrototype()->end()->defaultValue([])->end()
                            ->arrayNode('storage')
                                ->info('What it keeps in the browser: listed in the detailed section, erased when it is refused, and written through the answer (Cookie.cookies / .local / .session).')
                                ->arrayPrototype()
                                    ->beforeNormalization()->ifString()->then(static fn (string $name): array => ['name' => $name])->end()
                                    ->children()
                                        ->scalarNode('name')->isRequired()->cannotBeEmpty()->info('The name, or a pattern with * (USER/*, _pk_*). {session} is the session cookie\'s name, {answer} the one the answer is kept under.')->end()
                                        ->enumNode('type')->values(['cookie', 'local', 'session'])->defaultValue(D::STORAGE['type'])->info('cookie, local (localStorage) or session (sessionStorage).')->end()
                                        ->scalarNode('duration')->defaultValue(D::STORAGE['duration'])->info('How long it is kept: "30d", "12h", "13mo", "1y", "session", seconds; {lifetime} is the answer\'s.')->end()
                                        ->scalarNode('purpose')->defaultValue(D::STORAGE['purpose'])->info('What it is for: a key of the translations (consent.storage.<purpose>), else the words themselves.')->end()
                                        ->scalarNode('path')->defaultValue(D::STORAGE['path'])->end()
                                        ->scalarNode('domain')->defaultValue(D::STORAGE['domain'])->info('Of a third party\'s cookie, needed to erase it.')->end()
                                    ->end()
                                ->end()
                                ->defaultValue([])
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('privacy_route_parameters')
                    ->info('Parameters of privacy_route, e.g. {slug: vie-privee} for a page served by a generic route.')
                    ->useAttributeAsKey('name')->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('erase_on_refuse')
                    ->info('Name prefixes of cookies also deleted when the site\'s own feature is refused, e.g. a chat\'s ("crisp-client"). The groups\' own cookies always are.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->variableNode('options')->defaultValue(D::DEFAULTS['options'])
                    ->info('Anything else @glitchr/flowjs/cookie takes, handed as it is: record: {store, name}, cookie: {sameSite, domain}, undeclared, debug, variables...')->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
