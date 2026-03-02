<?php

namespace Aicl\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
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

    public function getTitle(): string|Htmlable
    {
        return __('API Tokens');
    }

    public static function getNavigationLabel(): string
    {
        return __('API Tokens');
    }

    public function mount(): void
    {
        abort_unless(config('aicl.features.api', true), 404);
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

        $token = $user->createToken($this->newTokenName);

        $this->createdToken = $token->accessToken;
        $this->newTokenName = '';

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
}
