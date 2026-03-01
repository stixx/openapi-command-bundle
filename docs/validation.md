# Validation & Error Handling

The **OpenAPI Command Bundle** integrates with the Symfony Validator component to ensure your command DTOs are valid before they reach your message handlers. It also provides a robust error handling mechanism that returns RFC 7807 compliant Problem Details responses.

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

If a request fails validation, the bundle will interrupt the flow and return a `400 Bad Request` response with violation details.

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
  "title": "Validation failed",
  "status": 400,
  "detail": "Validation failed",
  "violations": [
    {
      "propertyPath": "email",
      "title": "This value is not a valid email address.",
      "parameters": {
        "{{ value }}": "\"invalid-email\""
      },
      "type": "urn:uuid:bd79c0ab-ddb3-4675-903c-8b6141c2f08b"
    }
  ]
}
```

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

## Disabling Problem Details

If you prefer to handle errors yourself or don't want to use the Problem Details format, you can disable it:

```yaml
stixx_openapi_command:
    openapi:
        problem_details: false
```

When disabled, the bundle will not prepend Problem Details models to your NelmioApiDoc configuration, but it will still throw exceptions that you can catch in your own event listeners.

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

The bundle uses an `ExceptionToApiProblemTransformer` to convert internal exceptions into `ApiProblemException`. You can decorate or override this service if you need to customize how specific exceptions are mapped to problem details.
