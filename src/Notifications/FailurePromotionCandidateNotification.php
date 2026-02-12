<?php

namespace Aicl\Notifications;

use Aicl\Models\RlmFailure;

class FailurePromotionCandidateNotification extends BaseNotification
{
    public function __construct(
        public RlmFailure $failure,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Failure ready for promotion',
            'body' => "Failure \"{$this->failure->failure_code}: {$this->failure->title}\" has reached promotion criteria ({$this->failure->report_count} reports across {$this->failure->project_count} projects).",
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'action_url' => $this->failureUrl(),
            'action_text' => 'Review for Promotion',
        ];
    }

    public function getIcon(): string
    {
        return 'heroicon-o-arrow-trending-up';
    }

    public function getColor(): string
    {
        return 'success';
    }

    protected function failureUrl(): string
    {
        try {
            return route('filament.admin.resources.rlm_failures.view', ['record' => $this->failure]);
        } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException) {
            return '/admin/rlm-failures/'.$this->failure->getKey();
        }
    }
}
