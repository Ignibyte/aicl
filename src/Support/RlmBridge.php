<?php

namespace Aicl\Support;

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
        return class_exists(\Rlm\RlmServiceProvider::class);
    }

    /**
     * Validate an entity against RLM patterns.
     *
     * @return array{score: float, total: int, passed: int, failed: int, results: array}|null
     */
    public static function validate(string $entityName): ?array
    {
        if (! static::installed()) {
            return null;
        }

        $validator = new \Rlm\EntityValidator($entityName);
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
     * @return array{failures: \Illuminate\Support\Collection, lessons: \Illuminate\Support\Collection, scores: \Illuminate\Support\Collection, prevention_rules: \Illuminate\Support\Collection, golden_annotations: \Illuminate\Support\Collection, risk_briefing: array, component_recommendations: array}|null
     */
    public static function recall(?string $agent = null, ?string $phase = null): ?array
    {
        if (! static::installed() || $agent === null) {
            return null;
        }

        /** @var \Rlm\Services\RecallService $recallService */
        $recallService = app(\Rlm\Services\RecallService::class);

        return $recallService->recall($agent, (int) ($phase ?? 0));
    }

    /**
     * Get the PatternRegistry instance, if available.
     *
     * @return \Rlm\PatternRegistry|null
     */
    public static function patternRegistry(): ?object
    {
        if (! static::installed()) {
            return null;
        }

        return app(\Rlm\PatternRegistry::class);
    }
}
