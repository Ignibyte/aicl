<?php

declare(strict_types=1);

namespace Aicl\Support;

use Illuminate\Support\Collection;
use Rlm\EntityValidator;
use Rlm\PatternRegistry;
use Rlm\RlmServiceProvider;
use Rlm\Services\RecallService;

/**
 * Thin bridge between AICL and the optional RLM package.
 *
 * Every RLM touchpoint in the AICL framework goes through this class.
 * When ignibyte/rlm is not installed, all methods return null/false gracefully.
 * This class NEVER imports RLM classes directly — it uses class_exists()
 * and app() resolution to avoid autoload failures.
 */
class RlmBridge
{
    /**
     * Check whether the RLM Laravel package is installed and available.
     */
    public static function installed(): bool
    {
        return class_exists(RlmServiceProvider::class);
    }

    /**
     * Validate an entity against RLM patterns.
     *
     * @return array{score: float, total: int, passed: int, failed: int, results: array<int, mixed>}|null
     *
     * @codeCoverageIgnore Requires ignibyte/rlm package installation — installed() guard is tested
     */
    public static function validate(string $entityName): ?array
    {
        if (! static::installed()) {
            return null;
        }

        $validator = new EntityValidator($entityName);
        $results = $validator->validate();

        $passed = count(array_filter($results, fn ($r) => $r->passed));
        $total = count($results);

        return [
            'score' => $validator->score(),
            'total' => $total,
            'passed' => $passed,
            'failed' => $total - $passed,
            'results' => $results,
        ];
    }

    /**
     * Recall knowledge for an agent/phase context.
     *
     * @return array{failures: Collection<int, mixed>, lessons: Collection<int, mixed>, scores: Collection<int, mixed>, prevention_rules: Collection<int, mixed>, golden_annotations: Collection<int, mixed>, risk_briefing: array<string, mixed>, component_recommendations: array<string, mixed>}|null
     *
     * @codeCoverageIgnore Requires ignibyte/rlm package installation — installed() guard is tested
     */
    public static function recall(?string $agent = null, ?string $phase = null): ?array
    {
        if (! static::installed() || $agent === null) {
            return null;
        }

        /** @var RecallService $recallService */
        $recallService = app(RecallService::class);

        return $recallService->recall($agent, (int) ($phase ?? 0));
    }

    /**
     * Get the PatternRegistry instance, if available.
     *
     * @return PatternRegistry|null
     *
     * @codeCoverageIgnore Requires ignibyte/rlm package installation — installed() guard is tested
     */
    public static function patternRegistry(): ?object
    {
        if (! static::installed()) {
            return null;
        }

        return app(PatternRegistry::class);
    }
}
