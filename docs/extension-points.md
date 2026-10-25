# Extension Points

The bundle exposes four interfaces (`@api`) you can implement to plug into the request lifecycle, plus the `ApiProblemException` class for expressing RFC 7807 problems from your code. This page collects an example for each.

| Interface | What it lets you customize | Default implementation (`@internal`) |
|---|---|---|
| `Stixx\OpenApiCommandBundle\Validator\RequestValidatorInterface` | Cross-field / header / security checks on the raw `Request` before deserialization | `RequestValidator` (validates against the generated OpenAPI document) |
| `Stixx\OpenApiCommandBundle\Responder\ResponderInterface` | The shape and `Content-Type` of successful responses | `JsonResponder`, `JsonSerializedResponder`, `ScalarResponder`, `NullableResponder` (chained) |
| `Stixx\OpenApiCommandBundle\Responder\StatusResolverInterface` | The HTTP status code returned for a given request, command and handler result | `ResponseStatusResolver` (reads the OpenAPI operation's first 2xx, falls back to `201` for `POST`, `204` for `DELETE`, `200` otherwise) |
| `Stixx\OpenApiCommandBundle\Exception\ExceptionToApiProblemTransformerInterface` | How thrown exceptions become problem responses | `DefaultExceptionToApiProblemTransformer` |

Request validators and responders are auto-discovered via `autoconfigure`: implement the interface, register the class as a service, and the bundle picks it up. Tag-based registration (when you cannot use autoconfigure) is documented below. The status resolver and the exception transformer have one implementation each, which you replace by aliasing or decorating the interface's service, as shown per interface below.

---

## Custom request validators (`RequestValidatorInterface`)

Run before the command is deserialized. Use this for header checks, cross-field validation that can't be expressed as DTO constraints, or security gating. All tagged validators run in a chain on `kernel.request`, only for routes inside a Nelmio API area.

```php
namespace App\Validator;

use Stixx\OpenApiCommandBundle\Validator\RequestValidatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class RequireApiKeyValidator implements RequestValidatorInterface
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

Run after the handler returns a result. Use this for non-JSON responses (CSV, XML, binary), for custom serialization of specific result types, or to answer on the request's `Accept` header. Both methods receive a `ResponseContext` holding the request, the command and the handler's result. The chain checks `supports()` in priority order; the first responder that returns `true` wins.

```php
namespace App\Responder;

use Stixx\OpenApiCommandBundle\Responder\ResponderInterface;
use Stixx\OpenApiCommandBundle\Responder\ResponseContext;
use Symfony\Component\HttpFoundation\Response;

final class CsvResponder implements ResponderInterface
{
    public function supports(ResponseContext $context): bool
    {
        return $context->getResult() instanceof CsvPayload
            && in_array('text/csv', $context->getRequest()->getAcceptableContentTypes(), true);
    }

    public function respond(ResponseContext $context, int $status): Response
    {
        /** @var CsvPayload $payload */
        $payload = $context->getResult();

        return new Response($payload->csv, $status, ['Content-Type' => 'text/csv']);
    }
}
```

With autoconfiguration, implementing the interface is enough. Otherwise tag the service:

```yaml
services:
    App\Responder\CsvResponder:
        tags:
            - { name: 'stixx_openapi_command.response.responder' }
```

Higher priority runs first. The bundle's built-in responders, for `null`, scalars, `JsonSerializable` objects and, last, any other object or array, have negative priorities, so a responder at the default priority runs before them. A result no responder supports is a 500. To change every response, for example to add a header, listen to `kernel.response` instead. See [Command Routing & Request Handling](command-routing.md#customizing-responses-responders) for the chain ordering.

---

## Custom status resolvers (`StatusResolverInterface`)

Decides which HTTP status the response carries. Implement this if you want a project-wide override of the bundle's default. There is only one resolver active — register a service for the interface and the bundle's default is replaced.

```php
namespace App\Responder;

use Stixx\OpenApiCommandBundle\Responder\ResponseContext;
use Stixx\OpenApiCommandBundle\Responder\StatusResolverInterface;

final class AlwaysOkStatusResolver implements StatusResolverInterface
{
    public function resolve(ResponseContext $context): int
    {
        return 200;
    }
}
```

```yaml
# config/services.yaml
services:
    Stixx\OpenApiCommandBundle\Responder\StatusResolverInterface:
        alias: App\Responder\AlwaysOkStatusResolver
```

A more realistic implementation usually wraps the default and overrides only specific commands or results. The resolver runs after the handler, so it can see the result:

```php
final readonly class CustomStatusResolver implements StatusResolverInterface
{
    public function __construct(
        private StatusResolverInterface $inner,
    ) {}

    public function resolve(ResponseContext $context): int
    {
        if ($context->getCommand() instanceof BulkImportCommand) {
            return 202; // Accepted
        }

        if ($context->getResult() === null) {
            return 204;
        }

        return $this->inner->resolve($context);
    }
}
```

Wire it as a decorator:

```yaml
services:
    App\Responder\CustomStatusResolver:
        decorates: 'Stixx\OpenApiCommandBundle\Responder\StatusResolverInterface'
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
