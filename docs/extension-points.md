# Extension Points

The bundle exposes four interfaces (`@api`) you can implement to plug into the request lifecycle, plus the `ApiProblemException` class for expressing RFC 7807 problems from your code. This page collects an example for each.

| Interface | What it lets you customize | Default implementation (`@internal`) |
|---|---|---|
| `Stixx\OpenApiCommandBundle\Validator\ValidatorInterface` | Cross-field / header / security checks on the raw `Request` before deserialization | `RequestValidator` (validates against the generated OpenAPI document) |
| `Stixx\OpenApiCommandBundle\Responder\ResponderInterface` | The shape and `Content-Type` of successful responses | `JsonResponder`, `JsonSerializedResponder`, `ScalarResponder`, `NullableResponder` (chained) |
| `Stixx\OpenApiCommandBundle\Response\StatusResolverInterface` | The HTTP status code returned for a given command + request | `ResponseStatusResolver` (reads the OpenAPI operation's first 2xx, falls back to `201` for `POST`, `204` for `DELETE`, `200` otherwise) |
| `Stixx\OpenApiCommandBundle\Exception\ExceptionToApiProblemTransformerInterface` | How thrown exceptions become problem responses | `DefaultExceptionToApiProblemTransformer` |

All four are auto-discovered via `autoconfigure` — implement the interface, register the class as a service, and the bundle picks it up. Tag-based registration (when you cannot use autoconfigure) is documented per interface below.

---

## Custom request validators (`ValidatorInterface`)

Run before the command is deserialized. Use this for header checks, cross-field validation that can't be expressed as DTO constraints, or security gating. All tagged validators run in a chain on `kernel.request`, only for routes inside a Nelmio API area.

```php
namespace App\Validator;

use Stixx\OpenApiCommandBundle\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class RequireApiKeyValidator implements ValidatorInterface
{
    public function validate(Request $request): void
    {
        if (!$request->headers->has('X-Api-Key')) {
            throw new BadRequestHttpException('X-Api-Key header is required.');
        }
    }
}
```

```yaml
# config/services.yaml — only needed if autoconfigure is off
services:
    App\Validator\RequireApiKeyValidator:
        tags:
            - 'stixx_openapi_command.request.validator'
```

The bundle also runs its built-in `RequestValidator` in the same chain; it validates each request against the generated OpenAPI document via `league/openapi-psr7-validator`. Your custom validators run alongside it. See [Validation & Error Handling](validation.md) for more.

---

## Custom responders (`ResponderInterface`)

Run after the handler returns a result. Use this for non-JSON responses (CSV, XML, binary) or for custom serialization of specific result types. The chain checks `supports($result)` in priority order; the first responder that returns `true` wins.

```php
namespace App\Responder;

use Stixx\OpenApiCommandBundle\Responder\ResponderInterface;
use Symfony\Component\HttpFoundation\Response;

final class CsvResponder implements ResponderInterface
{
    public function supports(mixed $result): bool
    {
        return $result instanceof CsvPayload;
    }

    public function respond(mixed $result, int $status): Response
    {
        return new Response($result->csv, $status, ['Content-Type' => 'text/csv']);
    }
}
```

```yaml
services:
    App\Responder\CsvResponder:
        tags:
            - { name: 'stixx_openapi_command.response.responder', priority: 10 }
```

Higher priority runs first. The bundle's built-in responders cover JSON-serializable objects, arrays/Traversable, scalars, and `null`. See [Command Routing & Request Handling](command-routing.md#customizing-responses-responders) for the chain ordering.

---

## Custom status resolvers (`StatusResolverInterface`)

Decides which HTTP status the response carries. Implement this if you want a project-wide override of the bundle's default. There is only one resolver active — register a service for the interface and the bundle's default is replaced.

```php
namespace App\Response;

use Stixx\OpenApiCommandBundle\Response\StatusResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final class AlwaysOkStatusResolver implements StatusResolverInterface
{
    public function resolve(Request $request, object $command): int
    {
        return 200;
    }
}
```

```yaml
# config/services.yaml
services:
    Stixx\OpenApiCommandBundle\Response\StatusResolverInterface:
        alias: App\Response\AlwaysOkStatusResolver
```

A more realistic implementation usually wraps the default and overrides only specific commands:

```php
final readonly class CustomStatusResolver implements StatusResolverInterface
{
    public function __construct(
        private StatusResolverInterface $inner,
    ) {}

    public function resolve(Request $request, object $command): int
    {
        if ($command instanceof BulkImportCommand) {
            return 202; // Accepted
        }

        return $this->inner->resolve($request, $command);
    }
}
```

Wire it as a decorator:

```yaml
services:
    App\Response\CustomStatusResolver:
        decorates: 'Stixx\OpenApiCommandBundle\Response\StatusResolverInterface'
        arguments:
            $inner: '@.inner'
```

---

## Custom exception transformers (`ExceptionToApiProblemTransformerInterface`)

Maps thrown exceptions into `ApiProblemException` instances, which the bundle then renders as RFC 7807 problem responses. Replace this when your application has domain-specific exceptions that should produce specific HTTP statuses or extra payload fields.

```php
namespace App\Exception;

use App\Domain\OutOfStockException;
use Stixx\OpenApiCommandBundle\Exception\ApiProblemException;
use Stixx\OpenApiCommandBundle\Exception\ExceptionToApiProblemTransformerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class DomainAwareExceptionTransformer implements ExceptionToApiProblemTransformerInterface
{
    public function __construct(
        private ExceptionToApiProblemTransformerInterface $inner,
    ) {}

    public function transform(Throwable $throwable): ApiProblemException
    {
        if ($throwable instanceof OutOfStockException) {
            return new ApiProblemException(
                statusCode: Response::HTTP_CONFLICT,
                title: 'Out of stock',
                detail: $throwable->getMessage(),
            );
        }

        return $this->inner->transform($throwable);
    }
}
```

Wire as a decorator so the bundle's default still handles everything else:

```yaml
services:
    App\Exception\DomainAwareExceptionTransformer:
        decorates: 'Stixx\OpenApiCommandBundle\Exception\ExceptionToApiProblemTransformerInterface'
        arguments:
            $inner: '@.inner'
```

> **Architectural note:** `ApiProblemException` lives at the HTTP layer. Domain handlers should throw their own domain exceptions (`OutOfStockException`, `DomainException`, ...) and let a transformer (or a high-priority `kernel.exception` subscriber) translate them. Don't `use Stixx\…\ApiProblemException` from inside a Messenger handler.

---

## Throwing problem responses directly: `ApiProblemException`

For the cases where the surrounding code is *already* HTTP-aware (controllers, kernel listeners), throw `ApiProblemException` directly. The bundle's `ApiExceptionSubscriber` renders it as `application/problem+json` with the right status, headers, and violation list.

```php
use Stixx\OpenApiCommandBundle\Exception\ApiProblemException;

throw ApiProblemException::forbidden('You cannot perform this action on archived projects.');

// Or with a custom status + violations:
throw ApiProblemException::unprocessableEntity(
    detail: 'The request body is malformed.',
    violations: $violationList,
);
```

Available named constructors: `unauthenticated`, `forbidden`, `notFound`, `badRequest`, `unprocessableEntity`, `serverError`. Pass `previous: $someThrowable` to preserve the cause in the exception chain.
