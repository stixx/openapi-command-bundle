# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html). While the version is below `1.0.0`, minor releases
may contain breaking changes; read the **Upgrading** notes before bumping a minor.

## [Unreleased]

## [0.14.2] - 2026-10-03

### Added

- `cache:warmup` logs a warning on the `stixx_openapi_command` channel when it cannot cache an area's OpenAPI document,
  for example because an env var Nelmio's configuration uses is not set. It used to skip the area silently.

### Fixed

- Request validation no longer depends on `servers`: the request's path within the application is matched against
  the documented paths. 0.14.1 cached `%env()%` server URLs at warm-up time, so a server URL set at runtime with a path,
  such as `https://api.example.com/v1`, made valid requests fail with "no such operation". A server URL whose path
  differed from the application's base URL made every request fail, also before 0.14.1. Server path prefixes no longer
  restrict which requests are validated; the router has already matched the route by then.
- Other `%env()%` values in Nelmio's `documentation` or security configuration are part of the cached document's key,
  so a runtime value that differs from the one at warm-up no longer leaves the build-time document in use. Its document
  is generated once, and cached in the cache directory when the build directory is read-only; if it cannot be
  generated, the document cached at warm-up is used and a warning is logged. The cache file name changed, so documents
  cached by 0.14.1 are not reused.

## [0.14.1] - 2026-10-03

### Added

- In debug mode, a `command_paths` entry that matches no PHP files is logged as a warning on the
  `stixx_openapi_command` channel, so a typo or a changed directory layout shows up instead of silently adding no
  routes. A glob that matches nothing still isn't an error.

### Fixed

- Finding which Nelmio area a request belongs to no longer loads the application's routes. It rebuilt the whole
  route collection, including a scan of every `command_paths` entry, on every request that matched a route, API
  routes or not: around 115 ms per request in a benchmark with 700 routes. The route-to-area map is now cached like
  the router's matcher: written once by an optional cache warmer, or on first use, and refreshed in debug mode when
  the routes change.
- Validating an API request no longer generates the area's whole OpenAPI document, and loads every route, on each
  request: around 3.5 s per API request in a benchmark with 500 commands, against about 45 ms now. Each area's
  document is cached as JSON in the build directory, written by an optional cache warmer or by the first API request,
  and regenerated in debug mode when routes, container configuration, described classes or Composer dependencies
  change. Values Nelmio resolves at runtime, such as `%env()%` in `documentation.servers`, are now captured when the
  document is generated.
- With more than one Nelmio area, the second area's OpenAPI document generated in the same process was incomplete, or
  failed with "Unexpected @OA\JsonContent()" when warnings are converted to exceptions: the command route describer
  reused the OpenAPI attributes of the first area's document, which swagger-php had already altered. This affected
  long-running workers before, and now also warming the cache.

## [0.14.0] - 2026-10-03

### Added

- `command_paths` entries can be glob patterns, as in Symfony's resource imports: `*` matches one directory level
  and `**` any depth, so `'%kernel.project_dir%/src/**/Application/Command'` covers nested DDD contexts.
- Routing imports of type `stixx_openapi_command.command_attributes` accept single files, so a glob import such as
  `../src/**/Application/Command/**/*.php` works.
- CI checks `CHANGELOG.md` against the tags: every tag needs an entry and a compare link, an entry that shipped in its
  own tag may not change afterwards, and a pushed tag must be the newest entry, dated like the tag in UTC (for a
  lightweight tag, the commit it points at).
- Static analysis also runs against Symfony 7.4, the lowest supported version.
- The README documents the backward-compatibility and deprecation policy that applies from 1.0.0.

### Changed

- **`Validator\ValidatorInterface` is renamed to `Validator\RequestValidatorInterface`**, matching its service tag
  (`stixx_openapi_command.request.validator`) and `RequestValidatorChain`. The old name clashed with Symfony's
  `Validator\Validator\ValidatorInterface`, which is often imported in the same file. See **Upgrading** below.
- **`command_paths` is required** and no longer defaults to `%kernel.project_dir%/src`. See **Upgrading** below.
- A configured path that does not exist is an error naming the entry, instead of being skipped. A glob that matches
  nothing still yields no routes. Entries must match directories: one ending in `.php` is rejected.
- With `command_paths: []`, the discovery services are not registered at all.
- Routes from a `stixx_openapi_command.command_attributes` directory import are sorted most-specific-first, as
  discovered routes already were.

### Fixed

- In debug mode, routes refresh when a command is edited or added anywhere under a configured path. 0.12.1 claimed
  this, but the router cache only tracked command files directly inside each path: edits went unnoticed, and so did
  commands added in subdirectories, unless the command also happened to be a registered service. Directory imports
  of type `stixx_openapi_command.command_attributes` now track nested files too.

### Removed

- Support for Symfony 7.3, which reached end of life in January 2026. The bundle now requires Symfony 7.4 (LTS) or
  8.x, and CI tests 7.4, 8.0 and 8.1.

### Upgrading

Upgrade to Symfony 7.4 or later before installing 0.14.0; Composer will not resolve it against 7.3.

Custom request validators implement `Stixx\OpenApiCommandBundle\Validator\RequestValidatorInterface` instead of
`Stixx\OpenApiCommandBundle\Validator\ValidatorInterface`; only the name changed, so update type hints and service
ids that referred to the old one as well. Autoconfiguration and the `stixx_openapi_command.request.validator` tag work
as before.

`command_paths` must now be set; until it is, the container fails to compile with a message saying so. List the
directories holding your command DTOs in `config/packages/stixx_openapi_command.yaml`:

```yaml
stixx_openapi_command:
    command_paths:
        - '%kernel.project_dir%/src/*/Application/Command'
```

`['%kernel.project_dir%/src']` finds the same routes as 0.13, but scans your whole codebase, and in debug mode now
walks it on every request to notice new commands. `[]` turns discovery off in favour of routing imports. Remove any
entry pointing at a directory that does not exist, or move it under `when@<env>`.

## [0.13.3] - 2026-10-02

### Added

- CI coverage for Symfony 8.1.

### Changed

- The Symfony components the bundle uses directly (`config`, `event-dispatcher`, `http-foundation`,
  `http-kernel`, `routing`, `yaml`) are now declared as dependencies instead of arriving through other
  packages, with the same `^7.3 || ^8.0` constraint as the rest.

## [0.13.2] - 2026-10-02

### Changed

- Route discovery uses less memory while scanning `command_paths` (all of `src` by default). The file list
  holds path strings instead of `SplFileInfo` objects, about 1.3KB less per scanned file, and the cycle
  collector runs every 50 files to free the cyclic garbage swagger-php attributes leave behind. Which commands
  are discovered is unchanged.

## [0.13.1] - 2026-09-06

### Fixed

- Requests are validated against the OpenAPI document of **their own** Nelmio area. Previously every
  request was validated against the `default` area's document, while route detection accepted a route
  belonging to any area — so in a multi-area application a valid request to a non-default area was
  rejected with a `400` `openapi_request_validation`. Single-area applications are unaffected.
- An area whose name is numeric (`2024`) no longer raises a `TypeError` during route lookup. PHP stores
  such a key as an `int`, which `ServiceLocator::get(string $id)` rejects under `strict_types`.

## [0.13.0] - 2026-08-30

Maintenance only; no change in behaviour. This entry was added later: the `0.13.0` tag ships without it.

### Added

- Dependabot updates the GitHub Actions weekly; for Composer dependencies it opens security updates only.

### Changed

- `composer.lock` is no longer committed, so CI resolves the newest dependency versions the constraints allow, as
  applications installing the bundle do.
- Removed an unreachable `Traversable` check from `JsonSerializedResponder::supports()`, and code comments that
  restated the code.

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

[Unreleased]: https://github.com/stixx/openapi-command-bundle/compare/0.14.2...HEAD
[0.14.2]: https://github.com/stixx/openapi-command-bundle/compare/0.14.1...0.14.2
[0.14.1]: https://github.com/stixx/openapi-command-bundle/compare/0.14.0...0.14.1
[0.14.0]: https://github.com/stixx/openapi-command-bundle/compare/0.13.3...0.14.0
[0.13.3]: https://github.com/stixx/openapi-command-bundle/compare/0.13.2...0.13.3
[0.13.2]: https://github.com/stixx/openapi-command-bundle/compare/0.13.1...0.13.2
[0.13.1]: https://github.com/stixx/openapi-command-bundle/compare/0.13.0...0.13.1
[0.13.0]: https://github.com/stixx/openapi-command-bundle/compare/0.12.4...0.13.0
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
