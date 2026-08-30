# Command routing (OpenAPI-driven, no Symfony #[Route] needed on commands)

This bundle lets you build HTTP APIs around Command Bus messages (command DTOs) without writing controllers for each endpoint and without using Symfony’s #[Route] on the command classes. Instead, you declare OpenAPI operation attributes (from swagger-php) directly on your command DTOs and the bundle generates Symfony routes at compile time. A single CommandController handles the request lifecycle (deserialization, validation, dispatch, response) by default, with optional per-route overrides.

Declaring routes on commands is supported via:

- OpenAPI operation attributes on your command classes (non-controllers), such as #[OA\Post], #[OA\Get], #[OA\Put], #[OA\Patch], #[OA\Delete], etc. The path and HTTP method(s) are taken from these attributes, and the optional operationId becomes the route name.

This coexists with classic Symfony route configuration (YAML/PHP/XML). Choose what fits your project.


## Prerequisites

- Symfony 7.3+
- This bundle installed and enabled
- Your command classes are registered as services (typical with autowire/autoconfigure)

Important: Commands must NOT be controllers
- Do not extend Symfony\Bundle\FrameworkBundle\Controller\AbstractController in your command classes.
- Do not use #[Symfony\Bundle\FrameworkBundle\Controller\Attribute\AsController] on your command classes.
- The bundle excludes real controllers determined by: inheritance from AbstractController, presence of #[AsController], or having method‑level #[Route] attributes (controllers typically define #[Route] on methods). Command DTOs use only OpenAPI attributes at class level.


## Request lifecycle

A successful request flows through the bundle in two phases: a `kernel.request` validation phase, then controller execution. Anything that throws — at any step — is caught by `ApiExceptionSubscriber` and rendered as an RFC 7807 problem response (the second diagram below).

### Happy path

```mermaid
sequenceDiagram
    participant Client
    participant Kernel as Symfony Kernel
    participant Areas as NelmioAreaRoutesChecker
    participant ReqSub as RequestValidatorSubscriber
    participant Chain as RequestValidatorChain
    participant ApiV as RequestValidator (OpenAPI)
    participant Resolver as CommandValueResolver
    participant Controller as CommandController
    participant Bus as MessageBusInterface
    participant Status as StatusResolverInterface
    participant Responder as ResponderChain

    Client->>Kernel: HTTP Request
    Kernel->>ReqSub: kernel.request, priority 7
    ReqSub->>Areas: isApiRoute?
    alt route inside a Nelmio API area
        ReqSub->>Chain: validate
        Chain->>ApiV: validate
        Note over ApiV: Validates against the generated OpenAPI document<br/>via league/openapi-psr7-validator —<br/>headers, query, path, body shape.<br/>Throws ValidationFailed on mismatch.
        Chain->>Chain: Run user-tagged ValidatorInterface services
    else non-API route
        ReqSub-->>Kernel: skip
    end

    Kernel->>Resolver: resolve command argument
    Resolver->>Resolver: Decode JSON body when present
    Resolver->>Resolver: Collect scalar route + query params,<br/>route attributes win on collision
    Resolver->>Resolver: Denormalize into Command DTO via Symfony Serializer
    Note over Resolver: BadRequestHttpException on JSON decode<br/>or denormalization failure

    Kernel->>Controller: __invoke
    Controller->>Controller: Symfony Validator on the DTO
    Note over Controller: ApiProblemException::unprocessableEntity, 422,<br/>on constraint violations

    Controller->>Bus: dispatch
    Bus->>Bus: Execute message handler
    Bus-->>Controller: Envelope with HandledStamp::getResult
    Note over Controller: HandlerFailedException is caught and<br/>WrappedExceptionUnwrapper unwraps to the leaf cause —<br/>recursive, covers nested handlers<br/>and DelayedMessageHandlingException.

    Controller->>Status: resolve
    Note over Status: Reads first 2xx from the OpenAPI operation,<br/>falls back to 201 for POST, 204 for DELETE, 200 otherwise.
    Status-->>Controller: status code
    Controller->>Responder: respond with result, status
    Responder->>Responder: First responder whose supports returns true
    Note over Responder: Built-in chain:<br/>JsonResponder for JsonSerializable →<br/>JsonSerializedResponder for objects, arrays →<br/>ScalarResponder for string, int, float, bool →<br/>NullableResponder for null only.
    Responder-->>Controller: Response
    Controller-->>Kernel: Response
    Kernel-->>Client: HTTP Response
```

### Exception path

```mermaid
sequenceDiagram
    participant Source as Throwable raised anywhere —<br/>validator, resolver, controller, handler, ...
    participant Kernel as Symfony Kernel
    participant ExSub as ApiExceptionSubscriber
    participant Areas as NelmioAreaRoutesChecker
    participant Unwrap as WrappedExceptionUnwrapper
    participant Trans as ExceptionToApiProblemTransformer
    participant Norm as ApiProblemNormalizer
    participant Client

    Source->>Kernel: throw
    Kernel->>ExSub: kernel.exception, priority -10
    ExSub->>Areas: isApiRoute?
    Note over Areas: Match _route in any area's RouteCollection,<br/>OR fall back to path_patterns regex —<br/>covers 404, 405 where _route is unset.
    alt non-API route
        ExSub-->>Kernel: skip → framework default error page
    end
    ExSub->>Unwrap: unwrap throwable
    Unwrap-->>ExSub: leaf cause
    alt leaf is already ApiProblemException
        Note over ExSub: Use as-is — preserves status, title, detail, violations.
    else any other Throwable
        ExSub->>Trans: transform cause
        Trans-->>ExSub: ApiProblemException
    end
    ExSub->>Norm: normalize problem
    Norm-->>ExSub: payload
    ExSub->>ExSub: Build JsonResponse with status from the problem
    Note over ExSub: Throwable headers — Allow on 405, Retry-After on 503, ... —<br/>are preserved, but Content-Type is locked to<br/>application/problem+json per RFC 7807.<br/>If anything above throws, a static problem+json 500<br/>is emitted instead.
    ExSub-->>Client: HTTP Response
```

## No extra routes configuration required

Starting with this version, you do not need to add any custom route import for command DTOs.

How it works
- The bundle decorates `routing.loader`, the loader the router asks for when it builds its route collection. It runs once per router build, for the root routing resource, so command routes are added no matter how your application declares its own routes — or whether it declares any at all.
- During that build, the bundle scans the configured `command_paths` and adds routes for command classes that meet the criteria: class-level OpenAPI operation attributes (e.g., `#[OA\Post]`, `#[OA\Get]`, …) and not a controller.
- Discovered routes coexist with your existing controller routes and any manually configured routes.

Notes
- No additional routing import is necessary.
- The scan is recursive and covers the directories listed under `command_paths`, which defaults to `%kernel.project_dir%/src`:

  ```yaml
  stixx_openapi_command:
      command_paths:
          - '%kernel.project_dir%/src/Command'
          - '%kernel.project_dir%/lib/Billing/Command'
  ```

  Configured paths that do not exist are skipped, so listing a directory that only some environments have is safe.
- Only classes that are annotated with OpenAPI operation attributes (e.g., `#[OA\Post]`) at class level and are not recognized controllers (`AbstractController`, `#[AsController]`, or having method-level `#[Route]`) will produce routes.
  - Because of this, ensure your commands are plain DTOs and do not extend `AbstractController`, do not use `#[AsController]`, and do not declare method-level `#[Route]` attributes.
- If a route name is already present in the collection — because you imported the command explicitly — the bundle leaves your route alone rather than replacing it.

### Declaring command routes explicitly

Discovery is optional. To control exactly what gets routed, set `command_paths: []` and import commands from your routing config, either one class at a time:

```php
// config/routes/commands.php
use App\Command\CreateProjectCommand;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import(CreateProjectCommand::class, 'attribute');
};
```

or a directory at a time, using the bundle's routing type:

```yaml
# config/routes/commands.yaml
commands:
    resource: '../../src/Command'
    type: stixx_openapi_command.command_attributes
```


## Use OpenAPI attributes on command classes (no Symfony #[Route])

Place OpenAPI operation attributes on your command DTOs. They do not become controllers; the bundle still routes to CommandController by default. You can optionally override the controller per operation using an OpenAPI vendor extension or by annotating the command class with #[CommandObject(controller: ...)].

```php
use OpenApi\Attributes as OA;
use Stixx\OpenApiCommandBundle\Attribute\CommandObject;

#[CommandObject] // optional – you can also set a custom controller here
#[OA\Post(path: '/api/employees', operationId: 'add_employee', summary: 'Add employee')]
final class AddEmployeeCommand
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
    ) {}
}

#[OA\Delete(path: '/api/employees/{uuid}', operationId: 'remove_employee', summary: 'Remove employee')]
final class RemoveEmployeeCommand
{
    public function __construct(public string $uuid) {}
}
```

Override the controller via OpenAPI vendor extension:

```php
#[OA\Post(path: '/api/import', operationId: 'import_data', x: ['controller' => App\\Controller\\CustomCommandController::class])]
final class ImportDataCommand {}
```

Or via a class-level CommandObject attribute (not visible in the generated OpenAPI):

```php
#[CommandObject(controller: App\\Controller\\CustomCommandController::class)]
#[OA\Post(path: '/api/import', operationId: 'import_data')]
final class ImportDataCommand {}
```

Notes
- Real controllers are not affected. The bundle uses a precompiled list of controller classes based on: AbstractController inheritance, #[AsController], or method-level #[Route].
- Only classes that declare class-level OpenAPI operations are considered for routing discovery.


## Coexistence with classic route config

You can keep writing routes manually in YAML/PHP. The bundle’s attribute-based routes will happily coexist. If the same route name is declared multiple times, Symfony will apply its usual conflict rules; the bundle also ensures uniqueness when it autogenerates names.


## Handling the HTTP request

All generated routes point to a single controller: Stixx\OpenApiCommandBundle\Controller\CommandController.

Request-to-command mapping
- Body: If the request contains a non-empty body, it must be JSON (`application/json` or `+json`). The body is denormalized into your command DTO via Symfony Serializer.
- No body / Merging: The `CommandValueResolver` collects scalar values from route placeholders and query parameters and merges them with the body data (if any) to build the command. If nothing mappable is found, a 400 Bad Request might be thrown depending on the command's requirements.

Validation
- By default, the bundle validates the deserialized command via Symfony Validator before dispatching it.
- Configure validation via bundle options (validation groups, toggle HTTP validation).

Dispatch and response
- The command is dispatched on the Messenger bus. The response status code is resolved by `Stixx\OpenApiCommandBundle\Response\StatusResolverInterface` based on request/command; the response is handled by a chain of responders implementing `Stixx\OpenApiCommandBundle\Responder\ResponderInterface`.

---

## Customizing Responses (Responders)

By default, the bundle includes responders for JSON serialization, but you can extend this by adding your own custom responders. This is useful if you need to return different formats (e.g., CSV, XML) or handle specific return types from your message handlers.

### 1. Implement `ResponderInterface`

Create a class that implements `Stixx\OpenApiCommandBundle\Responder\ResponderInterface`:

```php
namespace App\Responder;

use Stixx\OpenApiCommandBundle\Responder\ResponderInterface;
use Symfony\Component\HttpFoundation\Response;

final class CsvResponder implements ResponderInterface
{
    public function supports(mixed $result): bool
    {
        // Return true if this responder can handle the result
        return is_array($result) && isset($result['format']) && $result['format'] === 'csv';
    }

    public function respond(mixed $result, int $status): Response
    {
        $csvData = $this->convertToCsv($result['data']);
        
        return new Response($csvData, $status, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function convertToCsv(array $data): string
    {
        // CSV conversion logic...
        return "column1,column2\nvalue1,value2";
    }
}
```

### 2. Tag your Service

Register your responder as a service and tag it with `stixx_openapi_command.response.responder`. With autoconfiguration enabled, the bundle automatically detects services implementing `ResponderInterface`.

```yaml
# config/services.yaml
services:
    App\Responder\CsvResponder:
        tags:
            - { name: 'stixx_openapi_command.response.responder', priority: 10 }
```

### How it works

The `ResponderChain` iterates through all registered responders and calls `supports($result)` on each. The first responder that returns `true` will be used to generate the `Response`. You can use the `priority` attribute in the tag to control the order of the responders.

---

## End-to-end example

Command
```php
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Post(path: '/api/projects', operationId: 'project_create', summary: 'Create project')]
final class CreateProjectCommand
{
    public function __construct(
        #[Assert\NotBlank]
        public string $name,

        #[Assert\Length(max: 200)]
        public ?string $description = null,
    ) {}
}
```

Handler
```php
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateProjectHandler
{
    public function __invoke(CreateProjectCommand $command): array
    {
        // ... create and persist
        return ['id' => '123', 'name' => $command->name];
    }
}
```

Call
```
POST /api/projects
Content-Type: application/json

{"name":"Acme CMS","description":"Headless"}
```

Response
```
HTTP/1.1 201 Created
Content-Type: application/json

{"id":"123","name":"Acme CMS"}
```


## OpenAPI / NelmioApiDoc

The bundle provides a `CommandRouteDescriber` so your routes appear in Nelmio ApiDoc automatically. If you use Nelmio areas, the bundle also exposes a helper (`NelmioAreaRoutesChecker`) that can recognize whether a request targets a documented API route. Routes generated from OpenAPI attributes are compiled into Symfony’s router, so they are visible in `bin/console debug:router`.


## Troubleshooting

- My routes don’t show up
  - Verify your command classes are registered as services (autowire/autoconfigure setups usually cover this).
  - Ensure the command class declares a class-level OpenAPI operation attribute (e.g., #[OA\Post(path: ...)]).
  - Ensure the class is NOT detected as a real controller by the bundle’s rules: it must not extend `AbstractController`, must not use `#[AsController]`, and must not declare method-level `#[Route]` attributes.

- Symfony auto-tagged my command with controller.service_arguments
  - This is less likely now since commands no longer use `#[Route]`. If you still observe it due to your own service config, it won’t exclude the class unless it matches the refined rules (`AbstractController`, `#[AsController]`, or method-level `#[Route]`).

- 400 Bad Request: Unsupported Content-Type
  - When sending a body, set `Content-Type: application/json` (or a `+json` media type).

- Validation errors
  - The bundle validates command DTOs prior to dispatch; you’ll get a 400 with violation details when constraints fail.
