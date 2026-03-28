<?php

declare(strict_types=1);

namespace Aicl\Filament\Resources\Users\Schemas;

use Aicl\Filament\Resources\Users\UserResource;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;

/**
 * Reusable user form schema for Filament create/edit pages.
 *
 * Provides the shared form layout for the User resource with three sections:
 * Personal Information (avatar, name, email, password), Roles & Permissions
 * (multi-select role assignment), and Security (MFA status and force-MFA toggle).
 * The Security section is hidden on the create operation since MFA only applies
 * to existing users.
 *
 * @see UserResource  Resource that uses this form
 *
 * @codeCoverageIgnore Filament Livewire rendering
 */
class UserForm
{
    /**
     * Configure the user form schema with personal info, roles, and security sections.
     *
     * @param  Schema  $schema  The Filament schema instance to configure
     * @return Schema The configured schema with all form components
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->schema([
                        Placeholder::make('avatar_display')
                            ->label('Avatar')
                            ->content(function (?Model $record): HtmlString {
                                if (! $record) {
                                    return new HtmlString('<span class="text-sm text-gray-500">Available after creation</span>');
                                }

                                $url = $record->getFilamentAvatarUrl();

                                if ($url) {
                                    return new HtmlString(
                                        '<img src="'.e($url).'" alt="'.e($record->name).'" class="h-16 w-16 rounded-full object-cover">'
                                    );
                                }

                                $initials = collect(explode(' ', $record->name))
                                    ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
                                    ->take(2)
                                    ->join('');

                                return new HtmlString(
                                    '<div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-800"><span class="text-lg font-semibold text-primary-600 dark:text-primary-400">'.$initials.'</span></div>'
                                );
                            }),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Roles & Permissions')
                    ->schema([
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ])
                    ->columnSpanFull(),

                Section::make('Security')
                    ->description('Multi-factor authentication settings')
                    ->schema([
                        Placeholder::make('mfa_status')
                            ->label('MFA Status')
                            ->content(function (?Model $record): HtmlString {
                                if (! $record) {
                                    return new HtmlString('<span class="text-sm text-gray-500">Available after creation</span>');
                                }

                                if ($record->hasConfirmedTwoFactor()) {
                                    return new HtmlString(
                                        '<span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-3 py-1 text-sm font-medium text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30">'
                                        .'<svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>'
                                        .'Enabled</span>'
                                    );
                                }

                                return new HtmlString(
                                    '<span class="inline-flex items-center gap-1.5 rounded-full bg-gray-50 px-3 py-1 text-sm font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20">'
                                    .'<svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>'
                                    .'Not set up</span>'
                                );
                            }),
                        Toggle::make('force_mfa')
                            ->label('Require MFA')
                            ->helperText(function (): string {
                                if (config('aicl.features.require_mfa', true)) {
                                    return 'All users are required to use MFA (global setting is enabled).';
                                }

                                return 'When enabled, this user must set up two-factor authentication on their next login.';
                            })
                            ->disabled(fn (): bool => (bool) config('aicl.features.require_mfa', true))
                            ->dehydrated()
                            ->hidden(fn (string $operation): bool => $operation === 'create'),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->hidden(fn (string $operation): bool => $operation === 'create'),
            ]);
    }
}
