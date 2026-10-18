<?php

namespace Base\Consent\Bridge\Twig;

use Base\Consent\ConsentPanel;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The partial's two functions over a ConsentPanel, in any Twig environment:
 * consent_options(), what the partial prints (the features and their
 * switches, the countdown, the privacy page's URL), and consent_settings(),
 * the same as the configuration @glitchr/flowjs/cookie reads (JSON, safe in a
 * <script type="application/json">). Twig and the panel, nothing else: a
 * framework other than Symfony builds the panel and adds this extension.
 */
class ConsentExtension extends AbstractExtension
{
    /** What is not the page's (ConsentPanel::IGNORE). */
    public const IGNORE = ConsentPanel::IGNORE;

    public function __construct(protected readonly ConsentPanel $panel)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('consent_options', $this->options(...)),
            new TwigFunction('consent_settings', $this->json(...), ['is_safe' => ['html']]),
        ];
    }

    public function panel(): ConsentPanel
    {
        return $this->panel;
    }

    /** @return array<string, mixed> see ConsentPanel::options() */
    public function options(): array
    {
        return $this->panel->options();
    }

    /** @return array<string, mixed> see ConsentPanel::settings() */
    public function settings(): array
    {
        return $this->panel->settings();
    }

    public function json(): string
    {
        return $this->panel->json();
    }
}
