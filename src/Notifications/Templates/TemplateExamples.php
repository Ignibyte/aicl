<?php

namespace Aicl\Notifications\Templates;

use Aicl\Notifications\FailurePromotionCandidateNotification;
use Aicl\Notifications\FailureRegressionNotification;
use Aicl\Notifications\FailureReportAssignedNotification;
use Aicl\Notifications\RlmFailureAssignedNotification;
use Aicl\Notifications\RlmFailureStatusChangedNotification;

/**
 * Static example templates for all built-in AICL notification types.
 *
 * Useful for seeders, testing, and documentation. These templates are NOT
 * automatically applied -- they are provided for reference and opt-in use.
 */
class TemplateExamples
{
    /**
     * Get example templates for all built-in AICL notification types.
     *
     * @return array<string, array{title: string, body: string}>
     */
    public static function all(): array
    {
        return [
            RlmFailureAssignedNotification::class => [
                'title' => 'Failure {{ model.failure_code }} assigned to you',
                'body' => '{{ model.title }} was assigned to you by {{ user.name }}.',
            ],
            FailureReportAssignedNotification::class => [
                'title' => 'Report assigned: {{ model.entity_name }}',
                'body' => 'Report for {{ model.entity_name }} (project {{ model.project_hash | truncate:8 }}) was assigned to you by {{ user.name }}.',
            ],
            RlmFailureStatusChangedNotification::class => [
                'title' => 'Failure {{ model.failure_code }} status changed',
                'body' => 'Failure {{ model.title | truncate:40 }} status was updated.',
            ],
            FailureRegressionNotification::class => [
                'title' => 'Regression detected: {{ model.failure_code }}',
                'body' => 'Previously-fixed failure {{ model.title | truncate:40 }} has reappeared.',
            ],
            FailurePromotionCandidateNotification::class => [
                'title' => 'Failure {{ model.failure_code }} ready for promotion',
                'body' => 'Failure {{ model.title | truncate:40 }} has reached promotion criteria.',
            ],
            '_default' => [
                'title' => '{{ title }}',
                'body' => '{{ body }}',
            ],
        ];
    }

    /**
     * Get example templates for a single notification type.
     *
     * @return array{title: string, body: string}|null
     */
    public static function for(string $notificationClass): ?array
    {
        return static::all()[$notificationClass] ?? null;
    }
}
