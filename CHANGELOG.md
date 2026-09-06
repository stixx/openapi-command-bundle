# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html). While the version is below `1.0.0`, minor releases
may contain breaking changes; read the **Upgrading** notes before bumping a minor.

## [Unreleased]

## [0.13.1] - 2026-09-06

### Fixed

- Requests are validated against the OpenAPI document of **their own** Nelmio area. Previously every
  request was validated against the `default` area's document, while route detection accepted a route
  belonging to any area — so in a multi-area application a valid request to a non-default area was
  rejected with a `400` `openapi_request_validation`. Single-area applications are unaffected.
- An area whose name is numeric (`2024`) no longer raises a `TypeError` during route lookup. PHP stores
  such a key as an `int`, which `ServiceLocator::get(string $id)` rejects under `strict_types`.

## [0.12.4] - 2026-08-30

Documentation only — no code changes since `0.12.3`.

### Added

- This changelog. Released so that it ships with the package rather than only appearing on the default
  branch; `0.12.3` and earlier tags do not contain it.

## [0.12.3] - 2026-08-30

Identical in content to `0.12.2` — same commit, no code difference. `0.12.2` was published to Packagist from a
mis-tagged commit and could not be corrected in place, because Packagist will not re-point an existing version
at a different commit. `0.12.3` re-releases the same code under a fresh number. If you are on `0.12.2` from
GitHub you already have this code; Composer users should take `0.12.3`.

The `0.12.2...0.12.3` comparison below is empty, as the two tags point at the same commit. Coming from
Packagist, where `0.12.2` does not exist, the changes you are picking up are those in
[`0.12.1...0.12.3`](https://github.com/stixx/openapi-command-bundle/compare/0.12.1...0.12.3) — the `0.12.2`
entry below.

## [0.12.2] - 2026-08-30

Available as a git tag and GitHub release only — see `0.12.3` above.

### Fixed

- `NelmioAreaRoutesChecker` no longer stops checking areas when a route locator entry is not a
  `RouteCollection`. It previously abandoned every remaining area, so routes in later areas could be treated
  as non-API — silently skipping request validation and RFC 7807 error responses for them.

## [0.12.1] - 2026-08-30

### Added

- `command_paths` configuration key listing the directories scanned for command DTOs. Defaults to
  `['%kernel.project_dir%/src']`, matching the path that was previously hardcoded. Set it to `[]` to disable
  discovery and declare command routes explicitly.
- `CommandRouteDirectoryLoader` is now registered as a service, so
  `type: stixx_openapi_command.command_attributes` can be used to import a directory of commands from an
  application's routing configuration.

### Changed

- **Two command classes resolving to the same route name now fail the container build**, naming both classes.
  Previously one silently overwrote the other and lost its endpoint. See **Upgrading** below.
- An unconfigured `NelmioApiDocBundle` now fails with an actionable message naming the missing half of the
  setup — the `config/bundles.php` entry or the package configuration — instead of an opaque
  "service does not exist" error for `stixx_openapi_command.nelmio.routes_locator`.

### Fixed

- Command routes are registered regardless of how the application declares its own routes. Discovery
  previously hung off a decorator on `routing.loader.attribute.directory`, so it only ran if the application
  happened to load routes through that loader. Applications using the current Symfony skeleton's
  `config/routes.yaml` — which sets a `namespace` and therefore loads via `Psr4DirectoryLoader` — got no
  command routes at all, with no error to explain why.
- Discovered command routes now carry their cache resources, so adding or editing a command DTO invalidates
  the router cache. Previously the routes went stale in `debug` mode until a manual `cache:clear`.
- Route priorities are preserved when discovered routes are added to the application's collection.

### Removed

- `AttributeDirectoryLoaderDecorator`, replaced by `RouterLoaderDecorator` and `CommandRouteDiscovery`. It was
  marked `@internal`, as all route loaders are.

### Upgrading

If two of your command classes resolve to the same route name, the container will now fail to compile instead
of silently dropping one endpoint. Without an `operationId` the name is derived from the class short name, so
`Billing\CreateInvoiceCommand` and `Sales\CreateInvoiceCommand` both resolve to `command_createinvoicecommand`.
The error names both classes; give at least one an explicit `operationId`. A build that goes red here was
already losing an endpoint silently before the upgrade.

`NelmioApiDocBundle` must be registered in `config/bundles.php` **and** configured with at least one area.
Installing it with Composer is not enough. See the README's installation section.

## [0.12.0] - 2026-07-11

### Added

- Configurable `Cache-Control` header on command responses via the `cache_control` key.

## [0.11.2] - 2026-06-15

### Fixed

- Command routes are ordered most-specific-first, so literal paths win over placeholder paths.

## [0.11.1] - 2026-05-22

### Fixed

- String query and route scalars are coerced against constructor scalar types.
- CI: disabled `composer audit` block-insecure so the dependency solver resolves.

## [0.11.0] - 2026-05-10

### Changed

- Updated the request-lifecycle diagram in the documentation.

## [0.10.0] - 2026-05-09

### Changed

- Classes reshaped to surface the public (`@api`) and internal (`@internal`) contracts.
- Expanded bundle documentation.

## [0.9.0] - 2026-05-09

### Changed

- Centralized unwrapping of Messenger exceptions.

## [0.8.0] - 2026-05-09

### Added

- `problem+json` responses for unmatched paths and wrong HTTP verbs under API areas.

## [0.7.0] - 2026-05-09

### Changed

- Audit fixes and tactical polish.

## [0.6.0] - 2026-05-09

### Added

- CI coverage for PHP 8.5 and Symfony 8.

### Changed

- `RequestValidator` is cached and `ApiExceptionSubscriber` is exception-safe.

## [0.5.4] - 2026-05-08

### Added

- Symfony Validator constraint violations map to HTTP 422 following RFC 7807.

## [0.5.3] - 2026-04-11

### Fixed

- `HandlerFailedException` is unwrapped, and `AccessDeniedException` is caught.

## [0.5.2] - 2026-03-02

### Changed

- `ApiExceptionSubscriber` is less aggressive about which exceptions it handles.

## [0.5.1] - 2026-03-01

### Added

- Project documentation.

## [0.5.0] - 2026-03-01

### Added

- Bundle configuration to include `ProblemDetails` in the generated OpenAPI schema automatically.

## [0.4.0] - 2026-03-01

### Added

- Functional test suite.
- CodeRabbit configuration.

## [0.3.0] - 2026-01-25

### Added

- Test coverage for the bundle's classes.

## [0.2.1] - 2025-12-12

### Fixed

- `JsonSerializedResponder` handles arrays and iterables.

## [0.2.0] - 2025-12-12

### Added

- Responder layer in `CommandController`, aligning with the ADR pattern.

## [0.1.0] - 2025-12-11

### Added

- Initial release: bundle, service and `CommandController` configuration, `ApiProblemNormalizer` that strips
  details outside debug mode, and a `CommandValueResolver` that supports list endpoints and the combination of
  parameters with a request body.

[Unreleased]: https://github.com/stixx/openapi-command-bundle/compare/0.13.1...HEAD
[0.13.1]: https://github.com/stixx/openapi-command-bundle/compare/0.13.0...0.13.1
[0.12.4]: https://github.com/stixx/openapi-command-bundle/compare/0.12.3...0.12.4
[0.12.3]: https://github.com/stixx/openapi-command-bundle/compare/0.12.2...0.12.3
[0.12.2]: https://github.com/stixx/openapi-command-bundle/compare/0.12.1...0.12.2
[0.12.1]: https://github.com/stixx/openapi-command-bundle/compare/0.12.0...0.12.1
[0.12.0]: https://github.com/stixx/openapi-command-bundle/compare/0.11.2...0.12.0
[0.11.2]: https://github.com/stixx/openapi-command-bundle/compare/0.11.1...0.11.2
[0.11.1]: https://github.com/stixx/openapi-command-bundle/compare/0.11.0...0.11.1
[0.11.0]: https://github.com/stixx/openapi-command-bundle/compare/0.10.0...0.11.0
[0.10.0]: https://github.com/stixx/openapi-command-bundle/compare/0.9.0...0.10.0
[0.9.0]: https://github.com/stixx/openapi-command-bundle/compare/0.8.0...0.9.0
[0.8.0]: https://github.com/stixx/openapi-command-bundle/compare/0.7.0...0.8.0
[0.7.0]: https://github.com/stixx/openapi-command-bundle/compare/0.6.0...0.7.0
[0.6.0]: https://github.com/stixx/openapi-command-bundle/compare/0.5.4...0.6.0
[0.5.4]: https://github.com/stixx/openapi-command-bundle/compare/0.5.3...0.5.4
[0.5.3]: https://github.com/stixx/openapi-command-bundle/compare/0.5.2...0.5.3
[0.5.2]: https://github.com/stixx/openapi-command-bundle/compare/0.5.1...0.5.2
[0.5.1]: https://github.com/stixx/openapi-command-bundle/compare/0.5.0...0.5.1
[0.5.0]: https://github.com/stixx/openapi-command-bundle/compare/0.4.0...0.5.0
[0.4.0]: https://github.com/stixx/openapi-command-bundle/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/stixx/openapi-command-bundle/compare/0.2.1...0.3.0
[0.2.1]: https://github.com/stixx/openapi-command-bundle/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/stixx/openapi-command-bundle/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/stixx/openapi-command-bundle/releases/tag/0.1.0
