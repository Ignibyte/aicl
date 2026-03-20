<?php

declare(strict_types=1);

namespace Aicl\Filament\Pages;

use Aicl\Mcp\AiclMcpServer;
use Aicl\Services\EntityRegistry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Facades\Mcp;
use UnitEnum;

/**
 * API tokens and integrations management page.
 *
 * Provides a Filament admin page for creating, viewing, and revoking
 * Laravel Passport personal access tokens. Also displays MCP server
 * connection details when MCP is enabled. Restricted to super_admin
 * and admin roles.
 *
 * Supports scope-based access control with presets (Full Access,
 * Read Only, MCP Client, MCP Read Only) and individual scope selection.
 *
 * @see AiclMcpServer  MCP server whose URL is displayed on this page
 */
class ApiTokens extends Page
{
    /** @var string Blade view for this page */
    protected string $view = 'aicl::filament.pages.api-tokens';

    protected static ?string $slug = 'api-tokens';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    /** @var string|null Name for the token being created */
    public ?string $newTokenName = '';

    /** @var string|null The newly created token value, shown once then cleared */
    public ?string $createdToken = null;

    /** @var string Currently active tab ('tokens' or 'mcp') */
    public string $activeTab = 'tokens';

    /** @var array<string> Selected OAuth scopes for the new token */
    public array $selectedScopes = ['*'];

    /**
     * Get the page title.
     */
    public function getTitle(): string|Htmlable
    {
        return __('API & Integrations');
    }

    /**
     * Get the sidebar navigation label.
     */
    public static function getNavigationLabel(): string
    {
        return __('API & Integrations');
    }

    /**
     * Determine if the current user can access this page.
     *
     * Only super_admin and admin roles may manage API tokens.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Mount the page, aborting with 404 if the API feature is disabled.
     */
    public function mount(): void
    {
        abort_unless(config('aicl.features.api', true), 404);
    }

    /**
     * Get the current user's non-revoked Passport tokens.
     *
     * @return array<int, array{id: string, name: string, scopes: array<string>, created_at: string, expires_at: string}>
     */
    public function getTokens(): array
    {
        $user = Auth::user();

        if (! $user) {
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
                'created_at' => $token->created_at?->diffForHumans() ?? '',
                'expires_at' => $token->expires_at?->format('M j, Y') ?? 'Never',
            ])
            ->toArray();
    }

    /**
     * Create a new Passport personal access token with the selected scopes.
     *
     * Sets $createdToken to the plaintext token value for one-time display,
     * then resets the form fields.
     */
    public function createToken(): void
    {
        $this->validate([
            'newTokenName' => 'required|string|max:255',
        ]);

        $user = Auth::user();

        if (! $user) {
            Notification::make()
                ->title('Token creation not available')
                ->danger()
                ->send();

            return;
        }

        // Validate scopes against allowed list to prevent arbitrary scope injection
        $allowedScopes = array_keys($this->getAvailableScopes());
        $scopes = array_values(array_intersect($this->selectedScopes, $allowedScopes));

        if (empty($scopes)) {
            $scopes = ['*'];
        }

        $token = $user->createToken((string) $this->newTokenName, $scopes);

        $this->createdToken = $token->accessToken;
        $this->newTokenName = '';
        $this->selectedScopes = ['*'];

        Notification::make()
            ->title('Token created successfully')
            ->body('Copy your token now. It won\'t be shown again.')
            ->success()
            ->send();
    }

    /**
     * Revoke an existing Passport token by ID.
     *
     * @param  string  $tokenId  The Passport token UUID
     */
    public function revokeToken(string $tokenId): void
    {
        $user = Auth::user();

        if (! $user) {
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

    /**
     * Clear the displayed token value after the user has copied it.
     */
    public function clearCreatedToken(): void
    {
        $this->createdToken = null;
    }

    /**
     * Check whether the MCP server feature is enabled and the package is installed.
     */
    public function isMcpAvailable(): bool
    {
        return (bool) config('aicl.features.mcp', false)
            && class_exists(Mcp::class);
    }

    /**
     * Get the full MCP server endpoint URL.
     */
    public function getMcpUrl(): string
    {
        return rtrim(config('app.url', ''), '/').config('aicl.mcp.path', '/mcp');
    }

    /**
     * Get the approximate number of MCP tools available (6 per entity).
     */
    public function getMcpToolCount(): int
    {
        if (! $this->isMcpAvailable()) {
            return 0;
        }

        try {
            $registry = app(EntityRegistry::class);

            return $registry->allTypes()->count() * 6; // 6 tools per entity (approx)
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Get the available OAuth scopes for token creation.
     *
     * @return array<string, string> Map of scope key to human-readable description
     */
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

    /**
     * Get predefined scope presets for common use cases.
     *
     * @return array<string, array<string>> Map of preset name to scope array
     */
    public function getScopePresets(): array
    {
        return [
            'Full Access' => ['*'],
            'Read Only' => ['read'],
            'MCP Client' => ['mcp', 'read', 'write'],
            'MCP Read Only' => ['mcp', 'read'],
        ];
    }

    /**
     * Apply a predefined scope preset to the selected scopes.
     *
     * @param  string  $preset  The preset name (e.g. 'Full Access', 'Read Only')
     */
    public function applyScopePreset(string $preset): void
    {
        $presets = $this->getScopePresets();

        if (isset($presets[$preset])) {
            $this->selectedScopes = $presets[$preset];
        }
    }
}
