<?php

namespace Aicl\Filament\Pages;

use Aicl\Settings\FeatureSettings;
use Aicl\Settings\GeneralSettings;
use Aicl\Settings\MailSettings;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property Schema $form
 */
class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Application Settings';

    protected static ?string $slug = 'settings';

    protected string $view = 'aicl::filament.pages.manage-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $general = app(GeneralSettings::class);
        $mail = app(MailSettings::class);
        $features = app(FeatureSettings::class);

        $this->form->fill([
            'site_name' => $general->site_name,
            'site_description' => $general->site_description,
            'timezone' => $general->timezone,
            'date_format' => $general->date_format,
            'items_per_page' => $general->items_per_page,
            'maintenance_mode' => $general->maintenance_mode,
            'from_address' => $mail->from_address,
            'from_name' => $mail->from_name,
            'reply_to' => $mail->reply_to,
            'enable_registration' => $features->enable_registration,
            'enable_social_login' => $features->enable_social_login,
            'enable_saml' => $features->enable_saml,
            'enable_mfa' => $features->enable_mfa,
            'enable_api' => $features->enable_api,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('General')
                    ->description('Basic application settings')
                    ->schema([
                        TextInput::make('site_name')
                            ->label('Site Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('site_description')
                            ->label('Site Description')
                            ->maxLength(500),
                        Select::make('timezone')
                            ->label('Timezone')
                            ->options(fn () => collect(timezone_identifiers_list())
                                ->mapWithKeys(fn ($tz) => [$tz => $tz])
                                ->toArray())
                            ->searchable()
                            ->required(),
                        Select::make('date_format')
                            ->label('Date Format')
                            ->options([
                                'Y-m-d' => now()->format('Y-m-d').' (Y-m-d)',
                                'd/m/Y' => now()->format('d/m/Y').' (d/m/Y)',
                                'm/d/Y' => now()->format('m/d/Y').' (m/d/Y)',
                                'F j, Y' => now()->format('F j, Y').' (F j, Y)',
                                'j F Y' => now()->format('j F Y').' (j F Y)',
                            ])
                            ->required(),
                        Select::make('items_per_page')
                            ->label('Items Per Page')
                            ->options([
                                10 => '10',
                                25 => '25',
                                50 => '50',
                                100 => '100',
                            ])
                            ->required(),
                        Toggle::make('maintenance_mode')
                            ->label('Maintenance Mode')
                            ->helperText('When enabled, only administrators can access the application.'),
                    ])
                    ->columns(2),

                Section::make('Mail')
                    ->description('Email sending configuration')
                    ->schema([
                        TextInput::make('from_address')
                            ->label('From Address')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('from_name')
                            ->label('From Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('reply_to')
                            ->label('Reply To')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Leave empty to use the from address'),
                    ])
                    ->columns(3),

                Section::make('Features')
                    ->description('Enable or disable application features')
                    ->schema([
                        Toggle::make('enable_registration')
                            ->label('User Registration')
                            ->helperText('Allow new users to register accounts'),
                        Toggle::make('enable_social_login')
                            ->label('Social Login')
                            ->helperText('Allow users to login with social providers'),
                        Toggle::make('enable_saml')
                            ->label('SAML SSO')
                            ->helperText('Enable SAML 2.0 single sign-on with an identity provider'),
                        Toggle::make('enable_mfa')
                            ->label('Multi-Factor Authentication')
                            ->helperText('Enable two-factor authentication for users'),
                        Toggle::make('enable_api')
                            ->label('API Access')
                            ->helperText('Enable API endpoints and token authentication'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $general = app(GeneralSettings::class);
        $general->site_name = $data['site_name'];
        $general->site_description = $data['site_description'];
        $general->timezone = $data['timezone'];
        $general->date_format = $data['date_format'];
        $general->items_per_page = $data['items_per_page'];
        $general->maintenance_mode = $data['maintenance_mode'];
        $general->save();

        $mail = app(MailSettings::class);
        $mail->from_address = $data['from_address'];
        $mail->from_name = $data['from_name'];
        $mail->reply_to = $data['reply_to'];
        $mail->save();

        $features = app(FeatureSettings::class);
        $features->enable_registration = $data['enable_registration'];
        $features->enable_social_login = $data['enable_social_login'];
        $features->enable_saml = $data['enable_saml'];
        $features->enable_mfa = $data['enable_mfa'];
        $features->enable_api = $data['enable_api'];
        $features->save();

        Notification::make()
            ->success()
            ->title('Settings Saved')
            ->body('Application settings have been updated successfully.')
            ->send();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin');
    }
}
