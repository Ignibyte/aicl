<?php

declare(strict_types=1);

namespace Aicl\Horizon\Livewire;

use Aicl\Horizon\Contracts\TagRepository;
use Aicl\Horizon\Jobs\MonitorTag;
use Aicl\Horizon\Jobs\StopMonitoringTag;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * MonitoredTagsTable.
 */
class MonitoredTagsTable extends Component
{
    public string $newTag = '';

    public function monitor(): void
    {
        // @codeCoverageIgnoreStart — Horizon process management
        if ($this->newTag === '') {
            return;
        }

        dispatch(new MonitorTag($this->newTag));

        Notification::make()
            ->success()
            ->title('Tag Monitoring Started')
            ->body("Now monitoring tag: {$this->newTag}")
            ->send();

        $this->newTag = '';
        // @codeCoverageIgnoreEnd
    }

    public function stopMonitoring(string $tag): void
    {
        // @codeCoverageIgnoreStart — Horizon process management
        dispatch(new StopMonitoringTag($tag));

        Notification::make()
            ->success()
            ->title('Tag Monitoring Stopped')
            ->body("Stopped monitoring tag: {$tag}")
            ->send();
        // @codeCoverageIgnoreEnd
    }

    public function render(): View
    {
        $tags = app(TagRepository::class)->monitoring();

        return view('aicl::horizon.livewire.monitored-tags-table', [
            'tags' => $tags,
        ]);
    }
}
