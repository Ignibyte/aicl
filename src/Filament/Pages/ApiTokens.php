<?php

namespace Aicl\Filament\Pages;

use Aicl\Services\EntityRegistry;
use Aicl\Settings\McpSettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Facades\Mcp;
use UnitEnum;

class ApiTokens extends Page
{
    protected string $view = 'aicl::filament.pages.api-tokens';

    protected static ?string $slug = 'api-tokens';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    public ?string $newTokenName = '';

    public ?string $createdToken = null;

    public string $activeTab = 'tokens';

    /** @var array<string> */
    public array $selectedScopes = ['*'];

    // MCP settings
    public bool $mcpEnabled = false;

    public ?string $mcpServerDescription = null;

    public function getTitle(): string|Htmlable
    {
        return __('API & Integrations');
    }

    public static function getNavigationLabel(): string
    {
        return __('API & Integrations');
    }

    public function mount(): void
    {
        abort_unless(config('aicl.features.api', true), 404);

        if ($this->isMcpAvailable()) {
            $settings = app(McpSettings::class);
            $this->mcpEnabled = $settings->is_enabled;
            $this->mcpServerDescription = $settings->server_description;
        }
    }

    public function getTokens(): array
    {
        $user = Auth::user();

        if (! method_exists($user, 'tokens')) {
            return [];
        }

        return $user->tokens()
            ->where('revoked', false)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'scopes' => $token->scopes ?? [],
                'created_at' => $token->created_at->diffForHumans(),
                'expires_at' => $token->expires_at?->format('M j, Y') ?? 'Never',
            ])
            ->toArray();
    }

    public function createToken(): void
    {
        $this->validate([
            'newTokenName' => 'required|string|max:255',
        ]);

        $user = Auth::user();

        if (! method_exists($user, 'createToken')) {
            Notification::make()
                ->title('Token creation not available')
                ->danger()
                ->send();

            return;
        }

        $scopes = $this->selectedScopes;

        $token = $user->createToken($this->newTokenName, $scopes);

        $this->createdToken = $token->accessToken;
        $this->newTokenName = '';
        $this->selectedScopes = ['*'];

        Notification::make()
            ->title('Token created successfully')
            ->body('Copy your token now. It won\'t be shown again.')
            ->success()
            ->send();
    }

    public function revokeToken(string $tokenId): void
    {
        $user = Auth::user();

        if (! method_exists($user, 'tokens')) {
            return;
        }

        $token = $user->tokens()->find($tokenId);

        if ($token) {
            $token->revoke();

            Notification::make()
                ->title('Token revoked')
                ->success()
                ->send();
        }
    }

    public function clearCreatedToken(): void
    {
        $this->createdToken = null;
    }

    public function toggleMcp(): void
    {
        if (! $this->isMcpAvailable()) {
            return;
        }

        $settings = app(McpSettings::class);
        $settings->is_enabled = ! $settings->is_enabled;
        $settings->save();

        $this->mcpEnabled = $settings->is_enabled;

        Notification::make()
            ->title($this->mcpEnabled ? 'MCP Server enabled' : 'MCP Server disabled')
            ->success()
            ->send();
    }

    public function updateMcpDescription(): void
    {
        if (! $this->isMcpAvailable()) {
            return;
        }

        $this->validate([
            'mcpServerDescription' => 'nullable|string|max:255',
        ]);

        $settings = app(McpSettings::class);
        $settings->server_description = $this->mcpServerDescription ?: null;
        $settings->save();

        Notification::make()
            ->title('Server description updated')
            ->success()
            ->send();
    }

    public function isMcpAvailable(): bool
    {
        return (bool) config('aicl.features.mcp', false)
            && class_exists(Mcp::class);
    }

    public function getMcpUrl(): string
    {
        return rtrim(config('app.url', ''), '/').config('aicl.mcp.path', '/mcp');
    }

    public function getMcpToolCount(): int
    {
        if (! $this->isMcpAvailable() || ! $this->mcpEnabled) {
            return 0;
        }

        try {
            $registry = app(EntityRegistry::class);

            return $registry->allTypes()->count() * 6; // 6 tools per entity (approx)
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<string, string> */
    public function getAvailableScopes(): array
    {
        return [
            '*' => 'Full Access',
            'read' => 'Read — List and view entities',
            'write' => 'Write — Create and update entities',
            'delete' => 'Delete — Remove entities',
            'mcp' => 'MCP — Access MCP server endpoint',
            'transitions' => 'Transitions — Change entity states',
        ];
    }

    /** @return array<string, array<string>> */
    public function getScopePresets(): array
    {
        return [
            'Full Access' => ['*'],
            'Read Only' => ['read'],
            'MCP Client' => ['mcp', 'read', 'write'],
            'MCP Read Only' => ['mcp', 'read'],
        ];
    }

    public function applyScopePreset(string $preset): void
    {
        $presets = $this->getScopePresets();

        if (isset($presets[$preset])) {
            $this->selectedScopes = $presets[$preset];
        }
    }
}
