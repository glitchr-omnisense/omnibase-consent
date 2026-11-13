# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

First stable release.

### Changed

- Installs with the default minimum-stability: stable requirements (`omnisense/omnibase` ^5.1), a branch alias,
  no `minimum-stability: dev` left to the sites.
- CI: glitchr-studio/ci's package workflow (the bundle installed alone, composer validate, PHPUnit,
  PHPStan against its baseline, a consumer install without require-dev) and its release workflow.
- require-dev: PHPUnit and omnibridge/symfony (the Symfony bridge's tests and PHPStan);
  a data provider declared as an attribute for PHPUnit 12.
