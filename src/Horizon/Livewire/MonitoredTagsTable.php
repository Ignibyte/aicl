<?php

namespace Aicl\Horizon\Livewire;

use Aicl\Horizon\Contracts\TagRepository;
use Aicl\Horizon\Jobs\MonitorTag;
use Aicl\Horizon\Jobs\StopMonitoringTag;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MonitoredTagsTable extends Component
{
    public string $newTag = '';

    public function monitor(): void
    {
        if (empty($this->newTag)) {
            return;
        }

        dispatch(new MonitorTag($this->newTag));

        Notification::make()
            ->success()
            ->title('Tag Monitoring Started')
            ->body("Now monitoring tag: {$this->newTag}")
            ->send();

        $this->newTag = '';
    }

    public function stopMonitoring(string $tag): void
    {
        dispatch(new StopMonitoringTag($tag));

        Notification::make()
            ->success()
            ->title('Tag Monitoring Stopped')
            ->body("Stopped monitoring tag: {$tag}")
            ->send();
    }

    public function render(): View
    {
        $tags = app(TagRepository::class)->monitoring();

        return view('aicl::horizon.livewire.monitored-tags-table', [
            'tags' => $tags,
        ]);
    }
}
