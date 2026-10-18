<?php

namespace Base\Consent\Bridge\Symfony\Twig;

use Base\Consent\Bridge\Twig\ConsentExtension as TwigConsentExtension;
use Base\Consent\ConsentPanel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The partial's functions under Symfony (the service the bundle registers,
 * known to the sites as Base\Consent\Twig\ConsentExtension): the panel built
 * from the container - the site's `consent:` configuration and the bundle's
 * features, the translator, omnibase's router for the privacy page, the main
 * request's session for its cookie's name.
 */
class ConsentExtension extends TwigConsentExtension
{
    /**
     * @param array<string, mixed>                $config   the site's consent: configuration
     * @param array<string, array<string, mixed>> $defaults the bundle's own features (config/features.php)
     */
    public function __construct(
        #[Autowire('%consent.config%')] array $config,
        // omnibase's router: the URL in the visitor's language (/datenschutz, not /confidentialite). Any URL
        // generator will do: a framework without omnibase (Laravel, plan 0 rule 13) hands its own.
        #[Autowire(service: 'Base\Routing\AdvancedRouterInterface')] UrlGeneratorInterface $urls,
        TranslatorInterface $translator,
        #[Autowire('%consent.defaults%')] array $defaults = [],
        ?RequestStack $requests = null,
    ) {
        parent::__construct(new ConsentPanel(
            $config,
            $translator,
            $defaults,
            static fn (string $route, array $parameters): string => $urls->generate($route, $parameters),
            static function () use ($requests): ?string {
                $request = $requests?->getMainRequest();

                return $request?->hasSession() ? $request->getSession()->getName() : null;
            },
        ));
    }
}
