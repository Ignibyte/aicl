<?php

namespace Aicl\Filament\Pages;

use Aicl\Services\LogParser;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use UnitEnum;

/**
 * @property Schema $form
 * @property Collection $logEntries
 */
class LogViewer extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Log Viewer';

    protected static ?string $title = 'Log Viewer';

    protected static ?string $slug = 'log-viewer';

    protected string $view = 'aicl::filament.pages.log-viewer';

    public ?string $selectedFile = null;

    public ?string $levelFilter = null;

    public ?string $search = null;

    public bool $liveMode = false;

    public int $limit = 100;

    public function mount(): void
    {
        $logParser = app(LogParser::class);
        $files = $logParser->getLogFiles();

        if (! empty($files)) {
            $this->selectedFile = $files[0]['path'];
        }
    }

    public function form(Schema $schema): Schema
    {
        $logParser = app(LogParser::class);

        return $schema
            ->schema([
                Select::make('selectedFile')
                    ->label('Log File')
                    ->options(function () use ($logParser): array {
                        $files = $logParser->getLogFiles();

                        return collect($files)
                            ->mapWithKeys(fn ($file) => [
                                $file['path'] => $file['name'].' ('.$logParser->formatSize($file['size']).')',
                            ])
                            ->toArray();
                    })
                    ->live()
                    ->columnSpan(2),
                Select::make('levelFilter')
                    ->label('Level')
                    ->options($logParser->getAvailableLevels())
                    ->placeholder('All Levels')
                    ->live(),
                TextInput::make('search')
                    ->label('Search')
                    ->placeholder('Search in messages...')
                    ->live(debounce: 500),
                Select::make('limit')
                    ->label('Show')
                    ->options([
                        50 => '50 entries',
                        100 => '100 entries',
                        250 => '250 entries',
                        500 => '500 entries',
                    ])
                    ->default(100)
                    ->live(),
                Toggle::make('liveMode')
                    ->label('Live Stream')
                    ->live(),
            ])
            ->columns(7);
    }

    #[Computed]
    public function logEntries(): Collection
    {
        if (! $this->selectedFile) {
            return collect();
        }

        $logParser = app(LogParser::class);

        return $logParser->parseLogFile(
            $this->selectedFile,
            $this->limit,
            $this->levelFilter,
            $this->search
        );
    }

    #[Computed]
    public function logFiles(): array
    {
        return app(LogParser::class)->getLogFiles();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    if (! $this->selectedFile) {
                        return;
                    }

                    if (! app(LogParser::class)->isValidLogPath($this->selectedFile)) {
                        return;
                    }

                    return response()->download($this->selectedFile);
                })
                ->disabled(fn () => ! $this->selectedFile),
            Action::make('clear')
                ->label('Clear Log')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Clear Log File')
                ->modalDescription('Are you sure you want to clear this log file? The file will remain but all entries will be deleted.')
                ->action(function (): void {
                    if (! $this->selectedFile) {
                        return;
                    }

                    $logParser = app(LogParser::class);

                    if ($logParser->clearFile($this->selectedFile)) {
                        Notification::make()
                            ->success()
                            ->title('Log Cleared')
                            ->body('The log file has been cleared.')
                            ->send();

                        unset($this->logEntries);
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body('Failed to clear the log file.')
                            ->send();
                    }
                })
                ->disabled(fn () => ! $this->selectedFile),
            Action::make('delete')
                ->label('Delete File')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete Log File')
                ->modalDescription('Are you sure you want to delete this log file? This action cannot be undone.')
                ->action(function (): void {
                    if (! $this->selectedFile) {
                        return;
                    }

                    $logParser = app(LogParser::class);

                    if ($logParser->deleteFile($this->selectedFile)) {
                        Notification::make()
                            ->success()
                            ->title('Log Deleted')
                            ->body('The log file has been deleted.')
                            ->send();

                        $files = $logParser->getLogFiles();
                        $this->selectedFile = ! empty($files) ? $files[0]['path'] : null;
                        unset($this->logEntries);
                        unset($this->logFiles);
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body('Failed to delete the log file.')
                            ->send();
                    }
                })
                ->disabled(fn () => ! $this->selectedFile),
        ];
    }

    public function getLevelColor(string $level): string
    {
        return app(LogParser::class)->getLevelColor($level);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }

    public function getPollingInterval(): ?string
    {
        return $this->liveMode ? '2s' : null;
    }
}
