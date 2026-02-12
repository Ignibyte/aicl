<?php

namespace Aicl\Notifications;

use Aicl\Models\FailureReport;
use Aicl\Models\RlmFailure;

class FailureRegressionNotification extends BaseNotification
{
    public function __construct(
        public RlmFailure $failure,
        public FailureReport $report,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Regression detected',
            'body' => "Previously-fixed failure \"{$this->failure->failure_code}: {$this->failure->title}\" has reappeared in entity \"{$this->report->entity_name}\".",
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'action_url' => $this->failureUrl(),
            'action_text' => 'Investigate Regression',
        ];
    }

    public function getIcon(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public function getColor(): string
    {
        return 'danger';
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
