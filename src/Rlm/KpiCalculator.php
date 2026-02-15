<?php

namespace Aicl\Rlm;

use Aicl\Models\DistilledLesson;
use Aicl\Models\GenerationTrace;
use Illuminate\Support\Collection;

class KpiCalculator
{
    /**
     * KPI 1: Fix Iteration Trend.
     *
     * Compares rolling average of recent entities vs baseline to detect
     * whether fix iterations are improving, stable, or declining.
     *
     * @return array{recent_avg: float, baseline_avg: float, percent_change: float, trend: string}
     */
    public function fixIterationTrend(): array
    {
        $minRuns = (int) config('aicl.kpi_thresholds.min_pipeline_runs', 5);
        $baselineWindow = (int) config('aicl.kpi_thresholds.baseline_window', 20);
        $recentWindow = (int) config('aicl.kpi_thresholds.recent_window', 5);

        $traces = GenerationTrace::query()
            ->whereNotNull('fix_iterations')
            ->orderByDesc('created_at')
            ->limit($baselineWindow)
            ->pluck('fix_iterations');

        if ($traces->count() < $minRuns) {
            return [
                'recent_avg' => 0.0,
                'baseline_avg' => 0.0,
                'percent_change' => 0.0,
                'trend' => 'INSUFFICIENT_DATA',
            ];
        }

        $recent = $traces->take($recentWindow);
        $baseline = $traces;

        $recentAvg = round($recent->avg(), 2);
        $baselineAvg = round($baseline->avg(), 2);

        if ($baselineAvg == 0.0) {
            return [
                'recent_avg' => $recentAvg,
                'baseline_avg' => $baselineAvg,
                'percent_change' => 0.0,
                'trend' => 'STABLE',
            ];
        }

        $percentChange = round((($recentAvg - $baselineAvg) / $baselineAvg) * 100, 1);
        $improvementThreshold = (float) config('aicl.kpi_thresholds.fix_trend_improvement_pct', -20.0);
        $declineThreshold = (float) config('aicl.kpi_thresholds.fix_trend_decline_pct', 20.0);

        $trend = match (true) {
            $percentChange <= $improvementThreshold => 'IMPROVING',
            $percentChange >= $declineThreshold => 'DECLINING',
            default => 'STABLE',
        };

        return [
            'recent_avg' => $recentAvg,
            'baseline_avg' => $baselineAvg,
            'percent_change' => $percentChange,
            'trend' => $trend,
        ];
    }

    /**
     * KPI 2: Known vs Novel Failure Ratio.
     *
     * Analyses recent pipeline runs for known (covered by lessons)
     * vs novel (uncovered) failure counts. Known failures should trend
     * toward 0 over time.
     *
     * @return array{known_total: int, novel_total: int, recurrence_rate: float, trend: string, runs_analyzed: int}
     */
    public function failureRatio(): array
    {
        $window = (int) config('aicl.kpi_thresholds.failure_analysis_window', 10);

        $traces = GenerationTrace::query()
            ->orderByDesc('created_at')
            ->limit($window)
            ->get();

        if ($traces->isEmpty()) {
            return [
                'known_total' => 0,
                'novel_total' => 0,
                'recurrence_rate' => 0.0,
                'trend' => 'INSUFFICIENT_DATA',
                'runs_analyzed' => 0,
            ];
        }

        $knownTotal = $traces->sum('known_failure_count');
        $novelTotal = $traces->sum('novel_failure_count');
        $totalFailures = $knownTotal + $novelTotal;

        $recurrenceRate = $totalFailures > 0
            ? round(($knownTotal / $totalFailures) * 100, 1)
            : 0.0;

        $healthyThreshold = (float) config('aicl.kpi_thresholds.recurrence_healthy_pct', 30.0);
        $moderateThreshold = (float) config('aicl.kpi_thresholds.recurrence_moderate_pct', 50.0);

        $trend = match (true) {
            $totalFailures === 0 => 'NO_FAILURES',
            $recurrenceRate < $healthyThreshold => 'HEALTHY',
            $recurrenceRate < $moderateThreshold => 'MODERATE',
            default => 'HIGH_RECURRENCE',
        };

        return [
            'known_total' => $knownTotal,
            'novel_total' => $novelTotal,
            'recurrence_rate' => $recurrenceRate,
            'trend' => $trend,
            'runs_analyzed' => $traces->count(),
        ];
    }

    /**
     * KPI 3: Lesson Effectiveness Rate.
     *
     * Per lesson: prevented_count / (prevented_count + ignored_count).
     * Returns top 5 performers, bottom 5 underperformers, and overall average.
     *
     * @return array{overall_avg: float, top_performers: Collection<int, array{lesson_code: string, title: string, effectiveness: float, prevented: int, total: int}>, underperformers: Collection<int, array{lesson_code: string, title: string, effectiveness: float, prevented: int, total: int}>, active_count: int}
     */
    public function lessonEffectiveness(): array
    {
        $lessons = DistilledLesson::query()
            ->where('is_active', true)
            ->get();

        $withActivity = $lessons->filter(
            fn (DistilledLesson $l): bool => ($l->prevented_count + $l->ignored_count) > 0
        );

        $effectiveness = $withActivity->map(function (DistilledLesson $l): array {
            $total = $l->prevented_count + $l->ignored_count;
            $rate = $total > 0 ? round(($l->prevented_count / $total) * 100, 1) : 0.0;

            return [
                'lesson_code' => $l->lesson_code,
                'title' => $l->title,
                'effectiveness' => $rate,
                'prevented' => $l->prevented_count,
                'total' => $total,
            ];
        });

        $overallAvg = $effectiveness->isNotEmpty()
            ? round($effectiveness->avg('effectiveness'), 1)
            : 0.0;

        $sorted = $effectiveness->sortByDesc('effectiveness')->values();

        return [
            'overall_avg' => $overallAvg,
            'top_performers' => $sorted->take(5),
            'underperformers' => $sorted->reverse()->take(5)->values(),
            'active_count' => $lessons->count(),
        ];
    }

    /**
     * Auto-retire underperforming lessons.
     *
     * Deactivates lessons where total interactions >= threshold
     * AND effectiveness < threshold%.
     *
     * @return array<int, string> Retired lesson codes
     */
    public function autoRetireLessons(): array
    {
        $minInteractions = (int) config('aicl.kpi_thresholds.auto_retire_min_interactions', 5);
        $effectivenessThreshold = (float) config('aicl.kpi_thresholds.auto_retire_effectiveness_pct', 30.0);

        $candidates = DistilledLesson::query()
            ->where('is_active', true)
            ->whereRaw('(prevented_count + ignored_count) >= ?', [$minInteractions])
            ->get();

        $retired = [];

        foreach ($candidates as $lesson) {
            $total = $lesson->prevented_count + $lesson->ignored_count;
            $effectiveness = $total > 0
                ? ($lesson->prevented_count / $total) * 100
                : 0.0;

            if ($effectiveness < $effectivenessThreshold) {
                $lesson->update(['is_active' => false]);
                $retired[] = $lesson->lesson_code;
            }
        }

        return $retired;
    }

    /**
     * Compute overall system verdict.
     *
     * @return array{verdict: string, fix_trend: array<string, mixed>, failure_ratio: array<string, mixed>, lesson_effectiveness: array<string, mixed>, metrics: array{fix_trend_pass: bool, recurrence_pass: bool, effectiveness_pass: bool}, total_runs: int}
     */
    public function computeVerdict(): array
    {
        $verdictMinRuns = (int) config('aicl.kpi_thresholds.verdict_min_runs', 20);

        $totalRuns = GenerationTrace::query()->count();

        $fixTrend = $this->fixIterationTrend();
        $failureRatio = $this->failureRatio();
        $effectiveness = $this->lessonEffectiveness();

        if ($totalRuns < $verdictMinRuns) {
            return [
                'verdict' => 'INSUFFICIENT_DATA',
                'fix_trend' => $fixTrend,
                'failure_ratio' => $failureRatio,
                'lesson_effectiveness' => $effectiveness,
                'metrics' => [
                    'fix_trend_pass' => false,
                    'recurrence_pass' => false,
                    'effectiveness_pass' => false,
                ],
                'total_runs' => $totalRuns,
            ];
        }

        $fixImprovementThreshold = (float) config('aicl.kpi_thresholds.fix_trend_improvement_pct', -20.0);
        $recurrenceTarget = (float) config('aicl.kpi_thresholds.recurrence_healthy_pct', 30.0);
        $effectivenessTarget = (float) config('aicl.kpi_thresholds.verdict_effectiveness_target', 60.0);

        $fixTrendPass = $fixTrend['percent_change'] <= $fixImprovementThreshold;
        $recurrencePass = $failureRatio['recurrence_rate'] < $recurrenceTarget;
        $effectivenessPass = $effectiveness['overall_avg'] > $effectivenessTarget;

        // Borderline checks
        $borderlineFix = (float) config('aicl.kpi_thresholds.verdict_borderline_fix_trend_pct', -10.0);
        $borderlineRecurrence = (float) config('aicl.kpi_thresholds.verdict_borderline_recurrence_pct', 40.0);
        $borderlineEffectiveness = (float) config('aicl.kpi_thresholds.verdict_borderline_effectiveness', 50.0);

        $fixTrendBorderline = ! $fixTrendPass && $fixTrend['percent_change'] <= $borderlineFix;
        $recurrenceBorderline = ! $recurrencePass && $failureRatio['recurrence_rate'] < $borderlineRecurrence;
        $effectivenessBorderline = ! $effectivenessPass && $effectiveness['overall_avg'] > $borderlineEffectiveness;

        $passCount = ($fixTrendPass ? 1 : 0) + ($recurrencePass ? 1 : 0) + ($effectivenessPass ? 1 : 0);
        $failCount = 3 - $passCount;
        $borderlineCount = ($fixTrendBorderline ? 1 : 0) + ($recurrenceBorderline ? 1 : 0) + ($effectivenessBorderline ? 1 : 0);

        $verdict = match (true) {
            $passCount === 3 => 'EARNING_ITS_KEEP',
            $borderlineCount > 0 && $failCount <= 1 => 'MARGINAL',
            $failCount >= 2 => 'OVERHEAD',
            default => 'MARGINAL',
        };

        return [
            'verdict' => $verdict,
            'fix_trend' => $fixTrend,
            'failure_ratio' => $failureRatio,
            'lesson_effectiveness' => $effectiveness,
            'metrics' => [
                'fix_trend_pass' => $fixTrendPass,
                'recurrence_pass' => $recurrencePass,
                'effectiveness_pass' => $effectivenessPass,
            ],
            'total_runs' => $totalRuns,
        ];
    }
}
