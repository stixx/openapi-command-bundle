# Validation & Error Handling

The bundle validates incoming requests in two distinct layers, and provides RFC 9457 Problem Details responses when either layer rejects the request.

| Layer | What it validates | Driven by | When it runs |
|---|---|---|---|
| **OpenAPI request validation** | Headers, query parameters, path parameters, body shape — anything described in the OpenAPI document | `league/openapi-psr7-validator` via the bundle's `RequestValidator` | `kernel.request` — *before* deserialization |
| **Symfony Validator** | Constraints declared on your command DTO properties (`#[Assert\NotBlank]`, `#[Assert\Email]`, …) | `symfony/validator` via the controller | After deserialization, *before* dispatch |

Both run automatically on every request to a Nelmio API area. Disabling one does not disable the other; see [Configuration](#configuration) below for the toggle.

You can also plug in your own request-level validators alongside the OpenAPI one — see [Extending Request Validation](#extending-request-validation) further down.

## Command Validation

By default, the bundle automatically validates every command deserialized from a request. This happens within the `CommandController` before the command is dispatched to the Messenger bus.

### Basic Usage

Simply use standard Symfony Validator constraints on your command properties:

```php
namespace App\Command;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Post(path: '/api/users', operationId: 'create_user')]
final class CreateUserCommand
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,

        #[Assert\NotBlank]
        #[Assert\Length(min: 8)]
        public string $password,
    ) {}
}
```

If a request fails validation, the bundle will interrupt the flow and return a `422 Unprocessable Content` response with violation details. Mapping/decode failures (malformed JSON, missing required fields the serializer cannot fill in) return `400 Bad Request` instead.

### Configuration

You can customize the validation behavior in `config/packages/stixx_openapi_command.yaml`:

```yaml
stixx_openapi_command:
    validation:
        enabled: true        # Enable or disable automatic validation (default: true)
        groups: ['Default']  # Specify validation groups to use (default: ['Default'])
```

### Validation Groups

If you need to use specific validation groups, you can configure them globally in the bundle configuration as shown above. 

> **Note**: Currently, validation groups are applied globally to all commands handled by the bundle.

---

## Error Handling & Problem Details

When an error occurs (validation fail, malformed JSON, mapping error, etc.), the bundle returns a response using **RFC 9457 (Problem Details for HTTP APIs)**. The `title` is the HTTP reason phrase of the status, such as `Not Found`; what went wrong is in `detail` and, for a rejected request, in `violations`.

### Content-Type

Problem responses use the `application/problem+json` media type.

### Example Validation Error Response

A command that fails validation returns a 422:

```json
{
  "type": "about:blank",
  "title": "Unprocessable Content",
  "status": 422,
  "detail": "Validation failed",
  "violations": [
    {
      "propertyPath": "email",
      "message": "This value is not a valid email address.",
      "code": "bd79c0ab-ddb3-4675-903c-8b6141c2f08b",
      "constraint": "Email",
      "error": "INVALID_FORMAT_ERROR"
    }
  ]
}
```

A request that does not match the OpenAPI document returns a 400, with a violation naming the invalid value. Its
`code` is the JSON Schema keyword that failed, and its `propertyPath` uses the JSON names of the request, such as
`authors[0].name`:

```json
{
  "type": "about:blank",
  "title": "Bad Request",
  "status": 400,
  "detail": "The request does not match the API specification.",
  "violations": [
    {
      "propertyPath": "email",
      "message": "Keyword validation failed: Value cannot be null",
      "code": "nullable",
      "constraint": "openapi_request_validation",
      "error": null
    }
  ]
}
```

For a header or cookie, `propertyPath` is its name. A violation always has
`propertyPath`, which is empty when it concerns the whole request or command, and `message`.
`error` is only set for Symfony constraints, and `code` is empty when an OpenAPI failure is not a JSON Schema
keyword.

> **Note**: the `detail` the bundle writes for its own problems, and any `detail` your code passes to
> `ApiProblemException`, is always sent. When the bundle turns any other exception into a problem, its message becomes
> the `detail` only when `kernel.debug` is `true`, because exception messages can reveal routes, class names and data.

### Mapping Errors

If the request body cannot be mapped to your command DTO (e.g., missing required properties in the JSON, type mismatch), the bundle throws a `BadRequestHttpException` which is transformed into a Problem Details response. In debug mode
it includes the exception message as `detail`:

```json
{
  "type": "about:blank",
  "title": "Bad Request",
  "status": 400,
  "detail": "Unable to map request to command: Required parameter \"name\" is missing"
}
```

---

## Documenting Problem Details in OpenAPI

By default the bundle prepends reusable response components to your NelmioApiDoc configuration, so your generated OpenAPI document describes the error shape:

| Component | Status |
|---|---|
| `InvalidRequestProblemDetailsResponse` | 400, a request that does not match the OpenAPI document |
| `UnauthorizedProblemDetailsResponse` | 401 |
| `ForbiddenProblemDetailsResponse` | 403 |
| `ResourceNotFoundProblemDetailsResponse` | 404 |
| `ConflictProblemDetailsResponse` | 409 |
| `UnprocessableEntityProblemDetailsResponse` | 422, a command that fails validation |
| `DefaultProblemDetailsResponse` | 500 |

Reference them from your operations, e.g. `#[OA\Response(ref: '#/components/responses/UnprocessableEntityProblemDetailsResponse', response: 422)]`. Their schemas are built from the same `ProblemDetails`, `ProblemDetailsInvalidRequestBody` and `Violation` classes the responses are written from. To opt out:

```yaml
stixx_openapi_command:
    openapi:
        problem_details: false
```

> **What this flag does and doesn't do.** Setting `problem_details: false` only affects the *generated OpenAPI documentation* — the response components stop being prepended, so your Swagger UI won't show problem-detail schemas. **The runtime behavior is unchanged**: `ApiExceptionSubscriber` still catches exceptions on API routes and returns `application/problem+json` responses. To replace the runtime behavior entirely, override the bundle's `ExceptionToApiProblemTransformerInterface` (see [Extension Points](extension-points.md#custom-exception-transformers-exceptiontoapiproblemtransformerinterface)) or register your own higher-priority `kernel.exception` listener.

---

## Extending Request Validation

In addition to standard DTO validation, you can extend the validation of the incoming HTTP request by implementing custom request validators. This is useful for cross-field validation, checking headers, or performing security checks before the command is even deserialized.

### 1. Implement `RequestValidatorInterface`

Create a class that implements `Stixx\OpenApiCommandBundle\Validator\RequestValidatorInterface`:

```php
namespace App\Validator;

use Stixx\OpenApiCommandBundle\Validator\RequestValidatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CustomHeaderValidator implements RequestValidatorInterface
{
    public function validate(Request $request): void
    {
        if (!$request->headers->has('X-Custom-Header')) {
            throw new BadRequestHttpException('Missing X-Custom-Header');
        }
    }
}
```

### 2. Tag your Service

Register your validator as a service and tag it with `stixx_openapi_command.request.validator`. If you have autoconfiguration enabled, the bundle will automatically detect and register your validator if it implements the interface.

```yaml
# config/services.yaml
services:
    App\Validator\CustomHeaderValidator:
        tags:
            - { name: 'stixx_openapi_command.request.validator' }
```

### How it works

All tagged validators are executed in a chain during the `kernel.request` event, but only for routes that are managed by this bundle (detected via `NelmioAreaRoutesChecker`). If any validator throws an exception, the request cycle is interrupted.

When several Nelmio areas are configured, each has its own OpenAPI document. `NelmioAreaRoutesChecker` resolves the area a request belongs to, and the request is validated against that area's document. Areas are checked in registration order and the first match wins.

A request is validated by its path within your application, its Symfony path info, against the document's `paths`. `servers` entries play no part, at any level and including `%env()%` values, so a server URL with a base path (`https://api.example.com/v1`) or a different URL per environment needs no rebuild.

Each area's document is generated by the first API request that needs it and cached as JSON in the build directory, or in the cache directory when the build directory is read-only. That copy is scoped to the container build; if the cache directory survives deploys and your builds pin `SOURCE_DATE_EPOCH`, clear its `stixx_openapi_command` folder on deploy, as you would Symfony's system cache. In debug mode it is regenerated when your routes, your container configuration, the classes it describes or your Composer dependencies change. Other `%env()%` values in Nelmio's `documentation` or security configuration are part of the cache key, so a different runtime value generates its own document once.

Generating a document describes every route and model in the area. In a large application that takes seconds and over 100 MB per area, paid once by the first request after a deploy, or by each of several requests that arrive at the same time; make sure the web server's `memory_limit` allows it.

### Warming up

To pay that cost at build time instead, enable warm-up:

```yaml
stixx_openapi_command:
    openapi:
        warm_up: true
```

`cache:warmup` and `cache:clear` then generate each area's document, and the map of which route belongs to which area, ahead of the first request. This costs build time and memory in those commands, and the documents use the env values present at build time. Define the env vars Nelmio's configuration uses during `cache:warmup`, for example with an `env(NAME)` parameter default. If one is missing, the area is logged as a warning on the `stixx_openapi_command` channel and generated on its first request instead.

A runtime value that differs from the one at warm-up still generates its own document once. If that document cannot be generated outside debug mode, for example because an env var set at warm-up is missing at runtime, the document cached at warm-up is used instead and kept for that runtime value, and a warning is logged once. In debug mode the error is thrown.

---

## Customizing Error Responses

The bundle uses an `ExceptionToApiProblemTransformer` to convert internal exceptions into `ApiProblemException`. You can decorate or override this service if you need to customize how specific exceptions are mapped to problem details — see [Extension Points → Custom exception transformers](extension-points.md#custom-exception-transformers-exceptiontoapiproblemtransformerinterface) for a worked example.
