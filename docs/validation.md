# Validation & Error Handling

The bundle validates incoming requests in two distinct layers, and provides RFC 7807-compliant Problem Details responses when either layer rejects the request.

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

If a request fails validation, the bundle will interrupt the flow and return a `422 Unprocessable Entity` response with violation details. Mapping/decode failures (malformed JSON, missing required fields the serializer cannot fill in) return `400 Bad Request` instead.

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

When an error occurs (validation fail, malformed JSON, mapping error, etc.), the bundle returns a response using the **RFC 7807 (Problem Details for HTTP APIs)** standard.

### Content-Type

Problem responses use the `application/problem+json` media type.

### Example Validation Error Response

```json
{
  "type": "about:blank",
  "title": "The request body could not be processed",
  "status": 422,
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

> **Note**: `detail` is only emitted when `kernel.debug` is `true` (development environment). In production, `detail` is suppressed to avoid leaking exception messages to clients. Public-facing context belongs in `title`; the per-property reason is in each violation's `message`.

### Mapping Errors

If the request body cannot be mapped to your command DTO (e.g., missing required properties in the JSON, type mismatch), the bundle throws a `BadRequestHttpException` which is transformed into a Problem Details response:

```json
{
  "type": "about:blank",
  "title": "Unable to map request to command",
  "status": 400,
  "detail": "Unable to map request to command: Required parameter \"name\" is missing"
}
```

---

## Documenting Problem Details in OpenAPI

By default the bundle prepends a small set of reusable response components — `DefaultProblemDetailsResponse`, `ResourceNotFoundProblemDetailsResponse`, etc. — to your NelmioApiDoc configuration so your generated OpenAPI document describes the error shape. To opt out:

```yaml
stixx_openapi_command:
    openapi:
        problem_details: false
```

> **What this flag does and doesn't do.** Setting `problem_details: false` only affects the *generated OpenAPI documentation* — the response components stop being prepended, so your Swagger UI won't show problem-detail schemas. **The runtime behavior is unchanged**: `ApiExceptionSubscriber` still catches exceptions on API routes and returns `application/problem+json` responses. To replace the runtime behavior entirely, override the bundle's `ExceptionToApiProblemTransformerInterface` (see [Extension Points](extension-points.md#custom-exception-transformers-exceptiontoapiproblemtransformerinterface)) or register your own higher-priority `kernel.exception` listener.

---

## Extending Request Validation

In addition to standard DTO validation, you can extend the validation of the incoming HTTP request by implementing custom request validators. This is useful for cross-field validation, checking headers, or performing security checks before the command is even deserialized.

### 1. Implement `ValidatorInterface`

Create a class that implements `Stixx\OpenApiCommandBundle\Validator\ValidatorInterface`:

```php
namespace App\Validator;

use Stixx\OpenApiCommandBundle\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CustomHeaderValidator implements ValidatorInterface
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

---

## Customizing Error Responses

The bundle uses an `ExceptionToApiProblemTransformer` to convert internal exceptions into `ApiProblemException`. You can decorate or override this service if you need to customize how specific exceptions are mapped to problem details — see [Extension Points → Custom exception transformers](extension-points.md#custom-exception-transformers-exceptiontoapiproblemtransformerinterface) for a worked example.
