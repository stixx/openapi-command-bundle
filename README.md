# OpenAPI Command Bundle

[![Latest Version on Packagist](https://img.shields.io/packagist/v/stixx/openapi-command-bundle.svg?style=flat-square)](https://packagist.org/packages/stixx/openapi-command-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/stixx/openapi-command-bundle.svg?style=flat-square)](https://packagist.org/packages/stixx/openapi-command-bundle)
[![License](https://img.shields.io/packagist/l/stixx/openapi-command-bundle.svg?style=flat-square)](https://packagist.org/packages/stixx/openapi-command-bundle)

The **OpenAPI Command Bundle** is a Symfony bundle that allows you to build HTTP APIs around Command Bus messages (DTOs) without the need for manual controller creation or Symfony `#[Route]` attributes on your commands.

By using standard OpenAPI operation attributes (from `zircote/swagger-php`) directly on your command DTOs, this bundle automatically generates Symfony routes and handles the entire request-to-command lifecycle: deserialization, validation, dispatching to the messenger bus, and responding.

## Key Features

- **OpenAPI-Driven Routing**: Define your API endpoints directly on your command DTOs using `#[OA\Post]`, `#[OA\Get]`, `#[OA\Put]`, etc.
- **No Manual Controllers**: A single `CommandController` handles all generated routes by default.
- **Automatic Deserialization**: Automatically maps JSON request bodies, route parameters, and query parameters to your command DTOs.
- **Two-Layer Validation**: Each request is checked against the OpenAPI schema (headers, query parameters, body shape via `league/openapi-psr7-validator`) *and* the deserialized command is validated with Symfony Validator constraints before it reaches your handler.
- **Messenger Integration**: Dispatches your commands directly to the Symfony Messenger bus.
- **Auto-Generated Documentation**: Seamlessly integrates with `NelmioApiDocBundle` to include your command-based routes in your OpenAPI/Swagger documentation.
- **Problem Details Support**: Returns RFC 7807 compliant error responses for validation and mapping errors.

## Installation

### 1. Install via Composer

```bash
composer require stixx/openapi-command-bundle
```

### 2. Enable the Bundle

If you are using Symfony Flex, the bundle will be automatically enabled. Otherwise, add it to your `config/bundles.php`:

```php
return [
    // ...
    Stixx\OpenApiCommandBundle\StixxOpenApiCommandBundle::class => ['all' => true],
];
```

## Usage

### 1. Create a Command DTO

Annotate your command with OpenAPI attributes. No Symfony `#[Route]` is needed.

```php
namespace App\Command;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Post(
    path: '/api/projects',
    operationId: 'create_project',
    summary: 'Create a new project'
)]
final class CreateProjectCommand
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 50)]
        public string $name,

        #[Assert\Length(max: 255)]
        public ?string $description = null,
    ) {}
}
```

### 2. Create a Message Handler

Implement a standard Symfony Messenger handler for your command.

```php
namespace App\Handler;

use App\Command\CreateProjectCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateProjectHandler
{
    public function __invoke(CreateProjectCommand $command): array
    {
        // Your business logic here (e.g., persist to database)
        
        return [
            'id' => '123',
            'name' => $command->name,
        ];
    }
}
```

### 3. Call the API

The bundle automatically registers the route `/api/projects` (POST).

```bash
curl -X POST http://localhost:3000/api/projects \
     -H "Content-Type: application/json" \
     -d '{"name": "New Project", "description": "This is a project description"}'
```

The bundle will:
1. Detect the route and map it to `CreateProjectCommand`.
2. Deserialize the JSON body into the command object.
3. Validate the command using Symfony Validator.
4. Dispatch the command to the Messenger bus.
5. Return the handler's result as a JSON response with an appropriate status code (e.g., `201 Created`).

## Configuration

You can customize the bundle's behavior in `config/packages/stixx_openapi_command.yaml`:

```yaml
stixx_openapi_command:
    validation:
        enabled: true
        groups: ['Default']
    openapi:
        problem_details: true # Enable RFC 7807 problem details for errors
```

## Documentation

For more detailed information, please refer to the following documentation:

- [Command Routing & Request Handling](docs/command-routing.md)
- [Validation & Error Handling](docs/validation.md)
- [OpenAPI Integration](docs/openapi.md)
- [Extension Points](docs/extension-points.md)

## Stability & supported API

The bundle distinguishes a small public API surface from its internal implementation. Public surface is the contract you can safely depend on; internals can change in any release.

**Public (`@api`)** — guaranteed BC across minor releases:

| Type | What it is |
|---|---|
| `Stixx\OpenApiCommandBundle\StixxOpenApiCommandBundle` | Bundle entry point |
| `Stixx\OpenApiCommandBundle\Attribute\CommandObject` | Marker attribute for command arguments |
| `Stixx\OpenApiCommandBundle\Exception\ApiProblemException` | Throw this from your code to express RFC 7807 problems |
| `Stixx\OpenApiCommandBundle\Exception\ExceptionToApiProblemTransformerInterface` | Replace to customize how exceptions become problem responses |
| `Stixx\OpenApiCommandBundle\Responder\ResponderInterface` | Implement to handle custom result shapes (CSV, XML, …) |
| `Stixx\OpenApiCommandBundle\Response\StatusResolverInterface` | Replace to customize status code resolution |
| `Stixx\OpenApiCommandBundle\Validator\ValidatorInterface` | Implement to add custom request-level validation |
| `Stixx\OpenApiCommandBundle\Model\ProblemDetails` | OpenAPI schema model — reference from your annotations |
| `Stixx\OpenApiCommandBundle\Model\ProblemDetailsInvalidRequestBody` | OpenAPI schema model — reference from your annotations |
| `Stixx\OpenApiCommandBundle\Model\Violation` | OpenAPI schema model — reference from your annotations |

**Internal (`@internal`)** — everything else, including default implementations like `JsonResponder`, `DefaultExceptionToApiProblemTransformer`, `RequestValidator`, the controllers, DI extension, compiler passes, route loaders, and event subscribers. Do not extend or rely on these directly; replace them through the `@api` interfaces above.

The bundle's configuration schema (the keys under `stixx_openapi_command:`) is also part of the supported API.

See [Extension Points](docs/extension-points.md) for a worked example of each extension interface.

## Requirements

- PHP 8.4+
- Symfony 7.3+ or 8.0+

## License

This bundle is released under the [MIT License](LICENSE).
