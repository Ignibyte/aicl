# MCP Server

## Purpose

Exposes AICL application entities to external AI agents (Claude Desktop, Cursor, Windsurf, custom) via the Model Context Protocol. Any AICL-powered app becomes an MCP server with a single feature flag — AI agents auto-discover tools, resources, and prompts for every registered entity.

Built on `laravel/mcp` (official first-party package by Taylor Otwell).

## Dependencies

- **`laravel/mcp` ^0.6** — MCP protocol, streamable HTTP transport, JSON-RPC handling
- **`laravel/passport`** — OAuth2 Bearer token auth with scopes
- **`spatie/laravel-settings`** — McpSettings for runtime configuration
- **`spatie/laravel-model-states`** — State transition introspection for TransitionEntityTool
- **`Aicl\Services\EntityRegistry`** — Entity discovery (scans `app/Models/` for `HasEntityLifecycle`)

## Quick Start

### 1. Enable the feature flag

```php
// config/local.php
'aicl.features.mcp' => true,
```

### 2. Create a Passport personal access client (if not already done)

```bash
php artisan passport:client --personal --name="Personal Access Client"
```

### 3. Create a token with MCP scope

In the admin panel: **System → API & Integrations → Access Tokens**

Select the "MCP Client" preset (grants `mcp`, `read`, `write` scopes) and create.

Or via code:

```php
$token = $user->createToken('my-agent', ['mcp', 'read', 'write']);
echo $token->accessToken;
```

### 4. Configure your AI client

**Claude Desktop** (`claude_desktop_config.json`):
```json
{
  "mcpServers": {
    "my-app": {
      "url": "https://my-app.com/mcp",
      "headers": {
        "Authorization": "Bearer eyJ0eX..."
      }
    }
  }
}
```

**Project `.mcp.json`:**
```json
{
  "mcpServers": {
    "my-app": {
      "type": "url",
      "url": "https://my-app.com/mcp",
      "headers": {
        "Authorization": "Bearer eyJ0eX..."
      }
    }
  }
}
```

The AI agent will auto-discover all available tools, resources, and prompts.

## What Gets Exposed Automatically

For each entity registered in the `EntityRegistry` (any model implementing `HasEntityLifecycle`):

### Tools (6 per entity)

| Tool | Scope | Auth | Description |
|------|-------|------|-------------|
| `list_{entities}` | `read` | `viewAny` policy | Paginated list with search, sort, filters |
| `show_{entity}` | `read` | `view` policy | Single record by ID |
| `create_{entity}` | `write` | `create` policy | Create from fillable fields |
| `update_{entity}` | `write` | `update` policy | Update by ID |
| `delete_{entity}` | `delete` | `delete` policy | Delete by ID (respects soft deletes) |
| `transition_{entity}` | `transitions` | `update` policy | State machine transition (stateful entities only) |

### Resources (per entity + 1 catalog)

| Resource | URI | Description |
|----------|-----|-------------|
| Entity Schema | `entity://{name}/schema` | Fields, types, casts, relationships, states |
| Entity Catalog | `entity://{type}` (template) | Lists all available entity types with metadata |

### Prompts (2 built-in)

| Prompt | Arguments | Description |
|--------|-----------|-------------|
| `crud_workflow` | `entity_type` (required), `operation` (optional: create/update/list/delete) | Step-by-step instructions for CRUD operations |
| `inspect_entity` | `entity_type` (required), `entity_id` (required) | Load and display entity data, state, relationships |

## Authorization — Double Layer

Every MCP request goes through two authorization checks:

### Layer 1: Token Scope

The Passport token must have the correct scope for the operation:

| Operation | Required Scope |
|-----------|---------------|
| Access `/mcp` endpoint at all | `mcp` |
| list, show | `read` |
| create, update | `write` |
| delete | `delete` |
| state transitions | `transitions` |
| everything | `*` (wildcard) |

The `scopes:mcp` middleware on the route rejects tokens without the `mcp` scope before any tool executes. Each tool then checks its specific scope via the `ChecksTokenScope` trait.

### Layer 2: Entity Policy

After the scope check, the tool calls `$user->can('action', $model)` which goes through Spatie Permission. A user with the `viewer` role who lacks `Create:Project` permission will be denied on `create_project` even if their token has the `write` scope.

### Scope Presets (Admin UI)

| Preset | Scopes | Use Case |
|--------|--------|----------|
| Full Access | `*` | Admin tokens |
| Read Only | `read` | Monitoring agents |
| MCP Client | `mcp`, `read`, `write` | General AI agent access |
| MCP Read Only | `mcp`, `read` | Read-only AI agents |

## Configuration

### Feature Flag

```php
// config/aicl.php
'features' => [
    'mcp' => env('AICL_MCP_ENABLED', false),  // default: OFF
],
```

### MCP Config

```php
// config/aicl.php
'mcp' => [
    'path' => env('AICL_MCP_PATH', '/mcp'),
    'middleware' => ['api', 'auth:api', 'throttle:api'],
    'server_info' => [
        'name' => env('AICL_MCP_SERVER_NAME'),  // defaults to app name
        'version' => '1.0.0',
    ],
],
```

### McpSettings (Spatie Settings — runtime config)

Managed via the admin UI (**System → API & Integrations → MCP Server** tab) or programmatically:

```php
$settings = app(McpSettings::class);

// Toggle MCP on/off
$settings->is_enabled = true;
$settings->save();

// Expose all entities with all operations (default)
$settings->exposed_entities = ['*'];

// Or restrict to specific entities and operations
$settings->exposed_entities = [
    'App\Models\Project' => ['list', 'show', 'create', 'update'],
    'App\Models\Task' => ['list', 'show'],
    // Entities not listed here are hidden from MCP
];

// Disable custom tool discovery
$settings->custom_tools_enabled = false;

// Set rate limit and session cap
$settings->rate_limit_per_minute = 120;
$settings->max_sessions = 20;

// Custom server description
$settings->server_description = 'Acme CRM';

$settings->save();
```

## Extending — Custom Tools

### Project-Level (auto-discovered)

Drop a tool class in `app/Mcp/Tools/`. It's auto-discovered on the next `tools/list` request.

```php
// app/Mcp/Tools/GenerateInvoiceTool.php
namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GenerateInvoiceTool extends Tool
{
    protected string $name = 'generate_invoice';
    protected string $description = 'Generate a PDF invoice for a project';

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ID')->required(),
            'include_tax' => $schema->boolean()->description('Include tax calculations'),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'include_tax' => 'boolean',
        ]);

        $invoice = InvoiceService::generate(
            $validated['project_id'],
            $validated['include_tax'] ?? true,
        );

        return Response::json([
            'invoice_id' => $invoice->id,
            'total' => $invoice->total,
            'pdf_url' => $invoice->pdf_url,
        ]);
    }
}
```

Generate with Artisan: `php artisan make:mcp-tool GenerateInvoice`

Same pattern for custom resources (`app/Mcp/Resources/`) and prompts (`app/Mcp/Prompts/`).

### Package-Level (McpRegistry)

Packages register MCP primitives via the `McpRegistry` singleton in their service provider:

```php
// In your package's ServiceProvider::register()
use Aicl\Mcp\McpRegistry;

public function register(): void
{
    $this->app->afterResolving(McpRegistry::class, function (McpRegistry $registry): void {
        $registry->registerMany([
            'tools' => [
                \MyPackage\Mcp\Tools\PublishPageTool::class,
                \MyPackage\Mcp\Tools\ReorderBlocksTool::class,
            ],
            'resources' => [
                \MyPackage\Mcp\Resources\PageTreeResource::class,
            ],
            'prompts' => [
                \MyPackage\Mcp\Prompts\ContentWorkflowPrompt::class,
            ],
        ]);
    });
}
```

Individual registration also available:

```php
$registry->registerTool(MyCustomTool::class);
$registry->registerResource(MyCustomResource::class);
$registry->registerPrompt(MyCustomPrompt::class);
```

Duplicates are automatically prevented.

### Custom Resources

Resources provide read-only context to AI agents:

```php
// app/Mcp/Resources/DashboardStatsResource.php
namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

class DashboardStatsResource extends Resource
{
    protected string $name = 'dashboard_stats';
    protected string $uri = 'app://dashboard/stats';
    protected string $mimeType = 'application/json';
    protected string $description = 'Current dashboard statistics and KPIs';

    public function handle(Request $request): Response
    {
        return Response::json([
            'active_projects' => Project::where('status', 'active')->count(),
            'pending_tasks' => Task::where('status', 'pending')->count(),
            'revenue_mtd' => Invoice::currentMonth()->sum('total'),
        ]);
    }
}
```

### Custom Prompts

Prompts are reusable workflow templates:

```php
// app/Mcp/Prompts/OnboardClientPrompt.php
namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

class OnboardClientPrompt extends Prompt
{
    protected string $name = 'onboard_client';
    protected string $description = 'Walk through the complete client onboarding workflow';

    public function arguments(): array
    {
        return [
            new Argument(name: 'client_name', description: 'Client company name', required: true),
            new Argument(name: 'plan', description: 'Subscription plan', required: false),
        ];
    }

    public function handle(Request $request): Response
    {
        $name = $request->get('client_name');

        return Response::text(<<<MD
        ## Client Onboarding: {$name}

        ### Steps
        1. Create the client organization using `create_organization`
        2. Create the primary contact using `create_user` (assign 'client-admin' role)
        3. Create the initial project using `create_project`
        4. Send welcome email notification
        5. Verify setup by inspecting the organization

        ### Required Information
        - Organization name, billing email, address
        - Primary contact: name, email, phone
        - Project: name, description, start date
        MD);
    }
}
```

## Key Decisions

| Decision | Choice | Why |
|----------|--------|-----|
| Package | `laravel/mcp` (official) | First-party, 9.5M+ installs, Passport-native |
| Tool generation | Dynamic from EntityRegistry | Zero maintenance — new entities auto-appear |
| Auth | Token scopes + entity policies | Defense in depth |
| Config | Spatie Settings (McpSettings) | Dynamic toggles from admin UI, no new migration |
| Endpoint | Single `/mcp` | Simple client config |
| Default | Off (`aicl.features.mcp = false`) | Zero overhead unless opted in |
| Extensibility | Auto-discovery + McpRegistry | Projects drop files, packages register via DI |

## File Map

```
packages/aicl/
├── config/aicl.php                              # features.mcp flag + mcp.* config
├── database/migrations/
│   └── 2026_03_14_100000_create_mcp_settings.php
├── routes/mcp.php                               # MCP route + OAuth well-known
└── src/
    ├── Mcp/
    │   ├── AiclMcpServer.php                    # Main server — orchestrates registration
    │   ├── McpRegistry.php                      # Package extensibility registry
    │   ├── Concerns/
    │   │   └── ChecksTokenScope.php             # Token scope enforcement trait
    │   ├── Tools/
    │   │   ├── ListEntityTool.php               # list_{entities} — paginated search/sort
    │   │   ├── ShowEntityTool.php                # show_{entity} — single record
    │   │   ├── CreateEntityTool.php              # create_{entity} — uses Form Request
    │   │   ├── UpdateEntityTool.php              # update_{entity} — partial updates
    │   │   ├── DeleteEntityTool.php              # delete_{entity} — soft delete aware
    │   │   └── TransitionEntityTool.php          # transition_{entity} — state machine
    │   ├── Resources/
    │   │   ├── EntitySchemaResource.php          # entity://{name}/schema
    │   │   └── EntityListResource.php            # entity://{type} (template)
    │   └── Prompts/
    │       ├── CrudWorkflowPrompt.php            # crud_workflow
    │       └── InspectEntityPrompt.php           # inspect_entity
    ├── Settings/
    │   └── McpSettings.php                      # Spatie Settings (group: mcp)
    └── Filament/Pages/
        └── ApiTokens.php                        # Enhanced "API & Integrations" page

app/Mcp/                                         # Project-level custom primitives
├── Tools/                                       # Auto-discovered custom tools
├── Resources/                                   # Auto-discovered custom resources
└── Prompts/                                     # Auto-discovered custom prompts
```

## Admin UI

**System → API & Integrations** (replaces the old "API Tokens" page)

### Tab 1: Access Tokens
- Create tokens with scope selection (presets + individual checkboxes)
- Token list shows scope badges
- Revoke button per token

### Tab 2: MCP Server
- Enable/disable toggle
- Server URL and tool count
- Server description input
- Client configuration JSON snippets (Claude Desktop, .mcp.json)
- Step-by-step connection instructions

## Swoole/Octane Compatibility

Fully compatible. The MCP streamable HTTP transport uses standard request/response cycles. SSE streaming for long-running tool calls is response streaming within a single request (not persistent connections), so no worker exhaustion risk (unlike the SSE approach removed in Sprint H).

## Related

- [Auth & RBAC](auth-rbac.md) — Passport OAuth2, Spatie Permission policies
- [Entity System](entity-system.md) — HasEntityLifecycle, EntityRegistry
- [laravel/mcp Documentation](https://laravel.com/docs/mcp)
- [MCP Specification](https://modelcontextprotocol.io/specification/2025-06-18)
