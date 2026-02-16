<?php

namespace Aicl\Console\Commands;

use Aicl\Models\DistilledLesson;
use Aicl\Models\FailureReport;
use Aicl\Models\GenerationTrace;
use Aicl\Models\GoldenAnnotation;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Aicl\Models\RlmScore;
use Aicl\Rlm\DistillationService;
use Aicl\Rlm\EmbeddingService;
use Aicl\Rlm\KnowledgeService;
use Aicl\Rlm\KpiCalculator;
use Aicl\Traits\HasEmbeddings;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class RlmCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'aicl:rlm
        {action : The action to perform (search, recall, learn, failures, scores, stats, export, trace-save, sync, embed, index, aar, cleanup, distill, feedback, health)}
        {query? : Search query or lesson summary}
        {--agent= : Agent role for recall (architect, rlm, tester, solutions, designer, pm)}
        {--phase= : Pipeline phase number for recall}
        {--entity-context= : JSON entity characteristics for recall/failures}
        {--entity= : Entity name for scores/recall}
        {--type= : Filter type for search (lesson, failure, pattern, prevention_rule, golden_annotation, all)}
        {--topic= : Topic for learn action}
        {--subtopic= : Subtopic for learn action}
        {--detail= : Detail text for learn action}
        {--tags= : Comma-separated tags for learn action}
        {--source= : Source reference for learn action}
        {--confidence=1.0 : Confidence level for learn action (0.0-1.0)}
        {--context= : Context filter for failures (has_states,has_enum,...)}
        {--severity= : Severity filter for failures (critical,high,...)}
        {--tier= : Tier filter for failures (base, project, all)}
        {--status= : Status filter for failures (active, fixed_in_scaffolding, all)}
        {--limit=10 : Max results}
        {--format=markdown : Export format (markdown, json)}
        {--output= : Export output directory}
        {--scaffolder-args= : Original scaffolder command for trace-save}
        {--file-manifest= : JSON of files created for trace-save}
        {--structural-score= : Final structural score for trace-save}
        {--semantic-score= : Final semantic score for trace-save}
        {--fixes= : JSON array of fixes applied for trace-save}
        {--fix-iterations=0 : Number of fix rounds for trace-save}
        {--pipeline-duration= : Pipeline duration in seconds for trace-save}
        {--push : Push local data to hub (sync action)}
        {--pull : Pull hub data to local (sync action)}
        {--dry-run : Show what would be synced without executing}
        {--backfill : Generate embeddings for all records missing them (embed action)}
        {--model= : Specific model class for embed/index (e.g., RlmFailure)}
        {--stats : Show embedding/index stats instead of running (embed/index action)}
        {--all : Process all models (index action)}
        {--recreate : Delete and recreate ES indices (index action)}
        {--remove-faker-records : Remove faker-generated records from RLM tables (cleanup action)}
        {--surfaced= : Comma-separated DL codes of lessons surfaced during generation (feedback action)}
        {--failures= : Comma-separated BF/F codes of failures that actually occurred (feedback action)}
        {--verdict : Include system verdict in health output (health action)}';

    /**
     * @var string
     */
    protected $description = 'RLM knowledge base management — search, recall, learn, and manage pattern knowledge.';

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'search' => $this->handleSearch(),
            'recall' => $this->handleRecall(),
            'learn' => $this->handleLearn(),
            'failures' => $this->handleFailures(),
            'scores' => $this->handleScores(),
            'stats' => $this->handleStats(),
            'export' => $this->handleExport(),
            'trace-save' => $this->handleTraceSave(),
            'sync' => $this->handleSync(),
            'embed' => $this->handleEmbed(),
            'index' => $this->handleIndex(),
            'aar' => $this->handleAar(),
            'cleanup' => $this->handleCleanup(),
            'distill' => $this->handleDistill(),
            'feedback' => $this->handleFeedback(),
            'health' => $this->handleHealth(),
            default => $this->showUsage(),
        };
    }

    private function getKnowledgeService(): KnowledgeService
    {
        return app(KnowledgeService::class);
    }

    // ─── Search ─────────────────────────────────────────────────

    private function handleSearch(): int
    {
        $query = $this->argument('query');
        if (! $query) {
            $this->components->error('Search requires a query argument.');

            return self::FAILURE;
        }

        $ks = $this->getKnowledgeService();
        $type = $this->option('type') ?? 'all';
        $limit = (int) $this->option('limit');

        $results = $ks->search($query, $type === 'all' ? null : $type, $limit);

        if ($results->isEmpty()) {
            $this->components->info("No results for \"{$query}\"");

            return self::SUCCESS;
        }

        $searchMode = $ks->isElasticsearchAvailable()
            ? ($ks->isSearchAvailable() ? 'hybrid (kNN+BM25)' : 'BM25')
            : 'deterministic';

        $this->line("Results ({$results->count()} matches for \"{$query}\", mode: {$searchMode}):");
        $this->newLine();

        foreach ($results as $result) {
            $this->renderSearchResult($result);
        }

        return self::SUCCESS;
    }

    // ─── Recall ─────────────────────────────────────────────────

    private function handleRecall(): int
    {
        $agent = $this->option('agent');
        $phase = $this->option('phase');

        if (! $agent || ! $phase) {
            $this->components->error('Recall requires --agent and --phase options.');

            return self::FAILURE;
        }

        $format = $this->option('format') ?? 'markdown';

        return match ($format) {
            'json' => $this->handleRecallJson($agent, (int) $phase),
            'full' => $this->handleRecallFull($agent, (int) $phase),
            default => $this->handleRecallCheatsheet($agent, (int) $phase),
        };
    }

    /**
     * Cheat sheet format: focused ~30 line output for agent context windows.
     */
    private function handleRecallCheatsheet(string $agent, int $phase): int
    {
        $distillation = app(DistillationService::class);
        $entityName = $this->option('entity');
        $entityContext = $this->option('entity-context')
            ? json_decode($this->option('entity-context'), true)
            : null;

        // Use explicit --limit if provided, otherwise default to 5 for cheatsheet
        $limit = $this->option('limit') !== '10' ? (int) $this->option('limit') : 5;

        $lessons = $distillation->getTopLessons($agent, $phase, $limit, $entityContext);

        $entityLabel = $entityName ?? 'any';
        $this->line("=== CHEAT SHEET: {$agent} / phase {$phase} (entity: {$entityLabel}) ===");
        $this->newLine();

        if ($lessons->isEmpty()) {
            $this->line('No distilled lessons yet. Run: aicl:rlm distill');
            $this->newLine();
            $this->line('Falling back to full recall...');
            $this->newLine();

            return $this->handleRecallFull($agent, $phase);
        }

        // TOP N lessons
        $this->line("TOP {$lessons->count()} LESSONS:");
        foreach ($lessons as $i => $lesson) {
            $num = $i + 1;
            $codes = implode(',', $lesson->source_failure_codes ?? []);
            $this->line("  {$num}. [{$lesson->lesson_code}] {$lesson->title}");
            $this->line("     {$lesson->guidance}");
            if ($codes) {
                $this->line("     Sources: {$codes}");
            }
        }
        $this->newLine();

        // When-Then rules
        $rules = $distillation->generateWhenThenRules($agent, $phase);
        if ($rules->isNotEmpty()) {
            $this->line('WHEN-THEN RULES:');
            foreach ($rules->take(5) as $rule) {
                $this->line("  WHEN {$rule['when']}:");
                foreach (array_slice($rule['then'], 0, 3) as $then) {
                    $this->line("    THEN {$then}");
                }
            }
            $this->newLine();
        }

        // Recent outcomes from KnowledgeService
        $ks = $this->getKnowledgeService();
        $result = $ks->recall($agent, $phase, $entityContext, $entityName);
        $briefing = $result['risk_briefing'];

        if (! empty($briefing['recent_outcomes'])) {
            $this->line('RECENT OUTCOMES:');
            foreach (array_slice($briefing['recent_outcomes'], 0, 3) as $outcome) {
                $structural = $outcome['structural_score'] ?? '?';
                $semantic = $outcome['semantic_score'] ?? '?';
                $this->line("  {$outcome['entity_name']}: {$structural}% structural, {$semantic}% semantic, {$outcome['fix_iterations']} fixes");
            }
            $this->newLine();
        }

        // Component recommendations (compact for cheatsheet)
        $componentRecs = $result['component_recommendations'] ?? [];
        if (! empty($componentRecs['field_recommendations'])) {
            $total = $componentRecs['total_components'] ?? 0;
            $this->line("COMPONENTS ({$total} available):");
            foreach (array_slice($componentRecs['field_recommendations'], 0, 5) as $rec) {
                $confidence = sprintf('%.0f%%', $rec['confidence'] * 100);
                $this->line("  {$rec['component']} [{$confidence}] {$rec['reason']}");
            }
            $this->line('  Run: aicl:components recommend {fields} | aicl:pipeline-context {Entity} --components');
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * JSON format: structured output for programmatic consumption.
     */
    private function handleRecallJson(string $agent, int $phase): int
    {
        $distillation = app(DistillationService::class);
        $entityContext = $this->option('entity-context')
            ? json_decode($this->option('entity-context'), true)
            : null;
        $limit = (int) $this->option('limit');

        $lessons = $distillation->getTopLessons($agent, $phase, $limit, $entityContext);
        $rules = $distillation->generateWhenThenRules($agent, $phase);

        $output = [
            'agent' => $agent,
            'phase' => $phase,
            'entity' => $this->option('entity'),
            'lessons' => $lessons->map(fn (DistilledLesson $l) => [
                'lesson_code' => $l->lesson_code,
                'title' => $l->title,
                'guidance' => $l->guidance,
                'impact_score' => $l->impact_score,
                'source_failure_codes' => $l->source_failure_codes,
            ])->values()->all(),
            'when_then_rules' => $rules->all(),
        ];

        $this->line(json_encode($output, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    /**
     * Full format: legacy verbose output with all sections.
     */
    private function handleRecallFull(string $agent, int $phase): int
    {
        $ks = $this->getKnowledgeService();

        $entityContext = $this->option('entity-context')
            ? json_decode($this->option('entity-context'), true)
            : null;

        $entityName = $this->option('entity');
        $result = $ks->recall($agent, $phase, $entityContext, $entityName);

        // Render risk briefing
        $briefing = $result['risk_briefing'];
        if (! empty($briefing['high_risk'])) {
            $features = $entityContext !== null
                ? implode(', ', array_keys(array_filter($entityContext)))
                : 'none';
            $this->line("=== RISK BRIEFING (entity: {$entityName}, features: {$features}) ===");
            $this->newLine();

            $this->line('HIGH RISK (matched '.count($briefing['high_risk']).' failures):');
            foreach ($briefing['high_risk'] as $risk) {
                $relevance = $risk['relevance'] !== null ? sprintf('[%.2f]', $risk['relevance']) : '';
                $this->line("  {$risk['failure_code']} {$relevance} {$risk['title']}");
                if ($risk['mitigation']) {
                    $this->line("    → MITIGATION: {$risk['mitigation']}");
                }
            }
            $this->newLine();
        }

        if (! empty($briefing['prevention_rules'])) {
            $this->line('PREVENTION RULES ('.count($briefing['prevention_rules']).' active):');
            foreach ($briefing['prevention_rules'] as $rule) {
                $confidence = sprintf('[%.2f]', $rule['confidence']);
                $this->line("  {$confidence} {$rule['rule_text']}");
            }
            $this->newLine();
        }

        if (! empty($briefing['recent_outcomes'])) {
            $this->line('RECENT OUTCOMES (similar entities):');
            foreach ($briefing['recent_outcomes'] as $outcome) {
                $structural = $outcome['structural_score'] ?? '?';
                $semantic = $outcome['semantic_score'] ?? '?';
                $fixes = $outcome['fix_iterations'];
                $this->line("  {$outcome['entity_name']}: structural {$structural}%, semantic {$semantic}%, {$fixes} fix iterations");
            }
            $this->newLine();
        }

        // Render failures
        $totalFailures = $result['failures']->count();
        $this->line("=== RELEVANT FAILURES ({$totalFailures}) ===");
        $this->newLine();

        foreach ($result['failures'] as $failure) {
            $severity = strtoupper($failure->severity->value);
            $this->line("{$failure->failure_code} [{$severity}] {$failure->title}");
            if ($failure->preventive_rule) {
                $this->line("  -> {$failure->preventive_rule}");
            }
        }

        $this->newLine();

        // Render lessons
        $totalLessons = $result['lessons']->count();
        $this->line("=== RELEVANT LESSONS ({$totalLessons}) ===");
        $this->newLine();

        foreach ($result['lessons'] as $lesson) {
            $subtopic = $lesson->subtopic ? " > {$lesson->subtopic}" : '';
            $this->line("[{$lesson->topic}{$subtopic}] {$lesson->summary}");
        }

        $this->newLine();

        // Render golden annotations
        $totalAnnotations = $result['golden_annotations']->count();
        if ($totalAnnotations > 0) {
            $this->line("=== GOLDEN ANNOTATIONS ({$totalAnnotations}) ===");
            $this->newLine();

            foreach ($result['golden_annotations'] as $annotation) {
                $category = $annotation->category instanceof \BackedEnum
                    ? $annotation->category->value
                    : (string) $annotation->category;
                $this->line("[{$category}] {$annotation->annotation_key}: {$annotation->annotation_text}");
                if ($annotation->file_path) {
                    $line = $annotation->line_number ? ":{$annotation->line_number}" : '';
                    $this->line("  → {$annotation->file_path}{$line}");
                }
            }

            $this->newLine();
        }

        // Render component recommendations
        $componentRecs = $result['component_recommendations'] ?? [];
        if (! empty($componentRecs['field_recommendations'])) {
            $total = $componentRecs['total_components'] ?? 0;
            $this->line("=== COMPONENT RECOMMENDATIONS ({$total} registered) ===");
            $this->newLine();

            $this->line('CONTEXT RULES:');
            foreach ($componentRecs['context_rules'] as $ctx => $rule) {
                $this->line("  {$ctx}: {$rule}");
            }
            $this->newLine();

            $this->line('FIELD RECOMMENDATIONS:');
            foreach ($componentRecs['field_recommendations'] as $rec) {
                $confidence = sprintf('%.0f%%', $rec['confidence'] * 100);
                $this->line("  {$rec['component']} [{$confidence}] — {$rec['reason']}");
                if (! empty($rec['filament_alternative'])) {
                    $this->line("    Filament: {$rec['filament_alternative']}");
                }
            }
            $this->newLine();

            $categories = implode(', ', $componentRecs['categories'] ?? []);
            $this->line("Available categories: {$categories}");
            $this->newLine();
        }

        // Render scores
        $entityLabel = $entityName ?? 'N/A';
        $this->line("=== RECENT SCORES (entity: {$entityLabel}) ===");
        $this->newLine();

        if ($result['scores']->isEmpty()) {
            $this->line('No previous scores for this entity.');
        } else {
            foreach ($result['scores']->take(5) as $score) {
                $this->line("{$score->score_type->value}: {$score->percentage}% ({$score->passed}/{$score->total}) — {$score->created_at}");
            }
        }

        return self::SUCCESS;
    }

    // ─── Learn ──────────────────────────────────────────────────

    private function handleLearn(): int
    {
        $summary = $this->argument('query');
        $topic = $this->option('topic');

        if (! $summary) {
            $this->components->error('Learn requires a summary as the query argument.');

            return self::FAILURE;
        }

        if (! $topic) {
            $this->components->error('Learn requires --topic option.');

            return self::FAILURE;
        }

        $ks = $this->getKnowledgeService();
        $detail = $this->option('detail') ?? $summary;
        $confidence = (float) $this->option('confidence');

        $lesson = $ks->addLesson(
            topic: $topic,
            summary: $summary,
            detail: $detail,
            subtopic: $this->option('subtopic'),
            tags: $this->option('tags'),
            source: $this->option('source'),
            confidence: $confidence,
        );

        $subtopicDisplay = $this->option('subtopic') ? " > {$this->option('subtopic')}" : '';
        $this->line("Lesson {$lesson->id} recorded: {$summary}");
        $this->line("Topic: {$topic}{$subtopicDisplay}");
        if ($this->option('tags')) {
            $this->line("Tags: {$this->option('tags')}");
        }

        return self::SUCCESS;
    }

    // ─── Failures ───────────────────────────────────────────────

    private function handleFailures(): int
    {
        $ks = $this->getKnowledgeService();

        $context = [];
        if ($this->option('context')) {
            foreach (explode(',', $this->option('context')) as $item) {
                $item = trim($item);
                if (str_contains($item, ':')) {
                    [$key, $value] = explode(':', $item, 2);
                    $context[$key] = $value;
                } else {
                    $context[$item] = true;
                }
            }
        }

        $severities = null;
        if ($this->option('severity')) {
            $severities = array_map('trim', explode(',', $this->option('severity')));
        }

        $results = $ks->getFailuresByContext($context, $severities);

        // Additional tier/status filters on Eloquent collection
        $tier = $this->option('tier') ?? 'all';
        $status = $this->option('status') ?? 'all';

        if ($tier !== 'all') {
            $results = $results->filter(fn (RlmFailure $f): bool => $f->promoted_to_base === ($tier === 'base'));
        }
        if ($status !== 'all') {
            $results = $results->filter(fn (RlmFailure $f): bool => ((string) $f->status) === $status);
        }

        $this->line('=== FAILURES ('.$results->count().') ===');
        $this->newLine();

        foreach ($results as $failure) {
            $severity = strtoupper($failure->severity->value);
            $this->line("{$failure->failure_code} [{$severity}] {$failure->title}");
            if ($failure->preventive_rule) {
                $this->line("  -> {$failure->preventive_rule}");
            }
        }

        return self::SUCCESS;
    }

    // ─── Scores ─────────────────────────────────────────────────

    private function handleScores(): int
    {
        $entity = $this->option('entity') ?? $this->argument('query');

        if ($entity) {
            $scores = RlmScore::query()
                ->forEntity($entity)
                ->latest()
                ->get();
            $this->line("=== SCORES: {$entity} ===");
        } else {
            $scores = RlmScore::query()
                ->latest()
                ->limit(50)
                ->get();
            $this->line('=== ALL SCORES ===');
        }

        $this->newLine();

        if ($scores->isEmpty()) {
            $this->line('No scores recorded.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($scores as $score) {
            $rows[] = [
                $score->entity_name,
                $score->score_type->value,
                "{$score->passed}/{$score->total}",
                "{$score->percentage}%",
                $score->errors,
                $score->warnings,
                $score->created_at,
            ];
        }

        $this->table(['Entity', 'Type', 'Passed/Total', 'Score', 'Errors', 'Warnings', 'Date'], $rows);

        return self::SUCCESS;
    }

    // ─── Stats ──────────────────────────────────────────────────

    private function handleStats(): int
    {
        $ks = $this->getKnowledgeService();
        $stats = $ks->stats();

        $this->line('Knowledge System Summary:');
        $this->line("  Storage:      {$stats['storage']}");
        $this->line("  Search:       {$stats['search_engine']}");
        $this->line("  Embeddings:   {$stats['embeddings']}");
        $this->newLine();
        $this->line("  Patterns:          {$stats['patterns']} active");
        $this->line("  Failures:          {$stats['failures']['total']} total — {$stats['failures']['active']} active");
        $this->line("  Lessons:           {$stats['lessons']['total']} recorded ({$stats['lessons']['verified']} verified, {$stats['lessons']['unverified']} unverified)");
        $this->line("  Scores:            {$stats['scores']} entities tracked");
        $this->line("  Traces:            {$stats['traces']} recorded");
        $this->line("  Prevention Rules:  {$stats['prevention_rules']} active");
        $this->line("  Golden Annotations: {$stats['golden_annotations']} active");

        if (! empty($stats['top_failing'])) {
            $this->newLine();
            $this->line('Top failing patterns (by report count):');
            foreach ($stats['top_failing'] as $i => $f) {
                $num = $i + 1;
                $failureCode = is_array($f) ? ($f['failure_code'] ?? '') : ($f->failure_code ?? '');
                $title = is_array($f) ? ($f['title'] ?? '') : ($f->title ?? '');
                $reportCount = is_array($f) ? ($f['report_count'] ?? 0) : ($f->report_count ?? 0);
                $this->line("  {$num}. {$failureCode} {$title} (report_count: {$reportCount})");
            }
        }

        if (! empty($stats['lessons']['by_topic'])) {
            $this->newLine();
            $topicParts = [];
            foreach ($stats['lessons']['by_topic'] as $topic => $count) {
                $topicParts[] = "{$topic}: {$count}";
            }
            $this->line('Lessons by topic:');
            $this->line('  '.implode(' | ', $topicParts));
        }

        return self::SUCCESS;
    }

    // ─── Export ─────────────────────────────────────────────────

    private function handleExport(): int
    {
        $format = $this->option('format') ?? 'markdown';
        $outputDir = $this->option('output') ?? storage_path('aicl/export');

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        if ($format === 'json') {
            $ks = $this->getKnowledgeService();
            $stats = $ks->stats();
            $outputPath = $outputDir.'/rlm-export.json';
            file_put_contents($outputPath, json_encode($stats, JSON_PRETTY_PRINT));
            $this->components->info("Exported stats to: {$outputPath}");

            return self::SUCCESS;
        }

        $files = [];

        // Export failures
        $failures = RlmFailure::query()->orderBy('failure_code')->get();
        $md = "# RLM Failures Export\n\n";
        $md .= "| # | Category | Title | Severity | Status |\n";
        $md .= "|---|----------|-------|----------|--------|\n";
        foreach ($failures as $f) {
            $category = $f->category->value;
            $severity = $f->severity->value;
            $md .= "| {$f->failure_code} | {$category} | {$f->title} | {$severity} | {$f->status} |\n";
        }
        $path = $outputDir.'/failures.md';
        file_put_contents($path, $md);
        $files[] = $path;

        // Export lessons
        $lessons = RlmLesson::query()->orderBy('topic')->orderBy('id')->get();
        $md = "# RLM Lessons Export\n\n";
        $currentTopic = '';
        foreach ($lessons as $l) {
            if ($l->topic !== $currentTopic) {
                $currentTopic = $l->topic;
                $md .= "\n## {$currentTopic}\n\n";
            }
            $subtopic = $l->subtopic ? " > {$l->subtopic}" : '';
            $md .= "- **{$l->summary}**{$subtopic}\n";
            if ($l->detail !== $l->summary) {
                $md .= "  {$l->detail}\n";
            }
        }
        $path = $outputDir.'/lessons.md';
        file_put_contents($path, $md);
        $files[] = $path;

        // Export scores
        $scores = RlmScore::query()->orderBy('entity_name')->orderByDesc('created_at')->get();
        $md = "# RLM Scores Export\n\n";
        $md .= "| Entity | Type | Passed | Total | Score | Errors | Warnings | Date |\n";
        $md .= "|--------|------|--------|-------|-------|--------|----------|------|\n";
        foreach ($scores as $s) {
            $scoreType = $s->score_type->value;
            $md .= "| {$s->entity_name} | {$scoreType} | {$s->passed} | {$s->total} | {$s->percentage}% | {$s->errors} | {$s->warnings} | {$s->created_at} |\n";
        }
        $path = $outputDir.'/scores.md';
        file_put_contents($path, $md);
        $files[] = $path;

        $this->components->info('Exported '.count($files).' markdown files to: '.$outputDir);
        foreach ($files as $file) {
            $this->line("  {$file}");
        }

        return self::SUCCESS;
    }

    // ─── Trace Save ─────────────────────────────────────────────

    private function handleTraceSave(): int
    {
        $entity = $this->option('entity');

        if (! $entity) {
            $this->components->error('trace-save requires --entity option.');

            return self::FAILURE;
        }

        $ks = $this->getKnowledgeService();

        $data = [
            'scaffolder_args' => $this->option('scaffolder-args') ?? '',
            'file_manifest' => $this->option('file-manifest') ? json_decode($this->option('file-manifest'), true) : null,
            'structural_score' => $this->option('structural-score') ? (float) $this->option('structural-score') : null,
            'semantic_score' => $this->option('semantic-score') ? (float) $this->option('semantic-score') : null,
            'fixes_applied' => $this->option('fixes') ? json_decode($this->option('fixes'), true) : null,
            'fix_iterations' => (int) $this->option('fix-iterations'),
            'pipeline_duration' => $this->option('pipeline-duration') ? (int) $this->option('pipeline-duration') : null,
        ];

        $trace = $ks->recordTrace($entity, $data);

        $this->line("Trace {$trace->id} saved for entity: {$entity}");
        if ($data['structural_score'] !== null) {
            $this->line("  Structural score: {$data['structural_score']}%");
        }
        if ($data['semantic_score'] !== null) {
            $this->line("  Semantic score: {$data['semantic_score']}%");
        }
        if ($data['fix_iterations'] > 0) {
            $this->line("  Fix iterations: {$data['fix_iterations']}");
        }

        return self::SUCCESS;
    }

    // ─── Sync (Hub) ─────────────────────────────────────────────

    private function handleSync(): int
    {
        $push = $this->option('push');
        $pull = $this->option('pull');

        if (! $push && ! $pull) {
            $this->components->error('Sync requires --push or --pull flag.');

            return self::FAILURE;
        }

        if (! app(\Aicl\Rlm\ProjectIdentity::class)->isHubEnabled()) {
            $this->components->error('Hub is not enabled. Set AICL_RLM_HUB_ENABLED=true, AICL_RLM_HUB_URL, and AICL_RLM_HUB_TOKEN in your .env.');

            return self::FAILURE;
        }

        $hubClient = app(\Aicl\Rlm\HubClient::class);

        if (! $hubClient->isReachable()) {
            $this->components->error('Hub is not reachable. Check AICL_RLM_HUB_URL and AICL_RLM_HUB_TOKEN.');

            return self::FAILURE;
        }

        if ($push) {
            $this->components->info('Pushing local data to hub...');

            $failureResult = $hubClient->pushFailures();
            $this->line("  Failures: {$failureResult['pushed']} pushed, {$failureResult['errors']} errors, {$failureResult['queued']} queued");

            $lessonResult = $hubClient->pushLessons();
            $this->line("  Lessons: {$lessonResult['pushed']} pushed, {$lessonResult['errors']} errors, {$lessonResult['queued']} queued");

            $traceResult = $hubClient->pushTraces();
            $this->line("  Traces: {$traceResult['pushed']} pushed, {$traceResult['errors']} errors, {$traceResult['queued']} queued");

            $totalPushed = $failureResult['pushed'] + $lessonResult['pushed'] + $traceResult['pushed'];
            $totalQueued = $failureResult['queued'] + $lessonResult['queued'] + $traceResult['queued'];

            $this->newLine();
            $this->components->info("Push complete: {$totalPushed} pushed".($totalQueued > 0 ? ", {$totalQueued} queued for retry" : ''));
        }

        if ($pull) {
            $this->components->info('Pulling hub data to local...');

            $patternResult = $hubClient->pullPatterns();
            $this->line("  Patterns: {$patternResult['received']} received, {$patternResult['merged']} merged");

            $failureResult = $hubClient->pullFailures();
            $this->line("  Failures: {$failureResult['received']} received, {$failureResult['merged']} merged");

            $ruleResult = $hubClient->pullPreventionRules();
            $this->line("  Prevention Rules: {$ruleResult['received']} received, {$ruleResult['cached']} cached");

            $this->newLine();
            $this->components->info('Pull complete.');
        }

        // Drain any queued items
        $queueSize = $hubClient->getQueueSize();
        if ($queueSize > 0) {
            $this->line("  Draining {$queueSize} queued items...");
            $drainResult = $hubClient->drainQueue();
            $this->line("  Queue drain: {$drainResult['pushed']} pushed, {$drainResult['remaining']} remaining");
        }

        return self::SUCCESS;
    }

    // ─── Embed (NEW) ────────────────────────────────────────────

    private function handleEmbed(): int
    {
        $embeddingService = app(EmbeddingService::class);

        if (! $embeddingService->isAvailable()) {
            $this->components->error('No embedding driver available. Set OPENAI_API_KEY or configure Ollama.');

            return self::FAILURE;
        }

        $modelFilter = $this->option('model');
        $showStats = (bool) $this->option('stats');
        $backfill = (bool) $this->option('backfill');

        $modelClasses = $this->resolveEmbeddableModels($modelFilter);

        if ($showStats) {
            return $this->showEmbedStats($modelClasses, $embeddingService);
        }

        if (! $backfill) {
            $this->components->error('Embed requires --backfill or --stats flag.');
            $this->line('  aicl:rlm embed --backfill                    Generate missing embeddings for all models');
            $this->line('  aicl:rlm embed --backfill --model=RlmFailure Generate for specific model');
            $this->line('  aicl:rlm embed --stats                       Show embedding coverage stats');

            return self::FAILURE;
        }

        $this->components->info("Generating embeddings (driver: {$this->getDriverName($embeddingService)})...");
        $totalProcessed = 0;
        $totalSkipped = 0;

        foreach ($modelClasses as $class) {
            $label = class_basename($class);
            $records = $class::query()->get();
            $processed = 0;
            $skipped = 0;

            foreach ($records as $record) {
                if (! in_array(HasEmbeddings::class, class_uses_recursive($record), true)) {
                    $skipped++;

                    continue;
                }

                /** @phpstan-ignore method.notFound (method provided by HasEmbeddings trait) */
                if ($record->getCachedEmbedding() !== null) {
                    $skipped++;

                    continue;
                }

                /** @phpstan-ignore method.notFound (method provided by HasEmbeddings trait) */
                $record->dispatchEmbeddingJob();
                $processed++;
            }

            $this->line("  {$label}: {$processed} dispatched, {$skipped} skipped");
            $totalProcessed += $processed;
            $totalSkipped += $skipped;
        }

        $this->newLine();
        $this->components->info("Embedding complete: {$totalProcessed} jobs dispatched, {$totalSkipped} skipped");

        return self::SUCCESS;
    }

    // ─── Index (NEW) ────────────────────────────────────────────

    private function handleIndex(): int
    {
        $ks = $this->getKnowledgeService();
        $modelFilter = $this->option('model');
        $showStats = (bool) $this->option('stats');
        $all = (bool) $this->option('all');
        $recreate = (bool) $this->option('recreate');

        if ($showStats) {
            return $this->showIndexStats($ks);
        }

        if (! $all && ! $modelFilter) {
            $this->components->error('Index requires --all or --model flag.');
            $this->line('  aicl:rlm index --all               Re-index all 5 models into ES');
            $this->line('  aicl:rlm index --model=RlmFailure  Re-index specific model');
            $this->line('  aicl:rlm index --all --recreate    Delete and recreate ES indices');
            $this->line('  aicl:rlm index --stats             Show index stats');

            return self::FAILURE;
        }

        if (! $ks->isElasticsearchAvailable()) {
            $this->components->error('Elasticsearch is not available. Cannot index.');

            return self::FAILURE;
        }

        $modelClasses = $this->resolveIndexableModels($modelFilter);

        if ($recreate) {
            $this->components->info('Recreating ES indices...');
            foreach (\Aicl\Rlm\Embeddings\IndexMappings::all() as $indexName => $mapping) {
                $this->recreateEsIndex($indexName, $mapping);
            }

            $this->newLine();
        }

        $this->components->info('Re-indexing models into Elasticsearch...');

        foreach ($modelClasses as $class) {
            $label = class_basename($class);
            $count = $class::query()->count();

            $class::query()
                ->get()
                ->each(function (Model $model): void {
                    if (method_exists($model, 'shouldBeSearchable') && $model->shouldBeSearchable()) {
                        /** @phpstan-ignore method.notFound (method provided by Searchable trait) */
                        $model->searchable();
                    }
                });

            $this->line("  {$label}: {$count} records indexed");
        }

        $this->newLine();
        $this->components->info('Indexing complete.');

        return self::SUCCESS;
    }

    // ─── After-Action Review (NEW) ────────────────────────────────

    private function handleAar(): int
    {
        $entityName = $this->option('entity');

        if (! $entityName) {
            $this->components->error('AAR requires --entity option.');

            return self::FAILURE;
        }

        $ks = $this->getKnowledgeService();

        // 1. Load the latest generation trace for this entity
        $trace = GenerationTrace::query()
            ->byEntity($entityName)
            ->latest()
            ->first();

        if (! $trace) {
            $this->components->error("No generation trace found for entity: {$entityName}");

            return self::FAILURE;
        }

        // 2. Load failure reports created during this pipeline run
        $failureReports = FailureReport::query()
            ->with('failure')
            ->where('entity_name', $entityName)
            ->where('created_at', '>=', $trace->created_at)
            ->get();

        // 3. Load latest validation scores for this entity
        $latestStructural = RlmScore::query()
            ->forEntity($entityName)
            ->ofType('structural')
            ->latest()
            ->first();

        $latestSemantic = RlmScore::query()
            ->forEntity($entityName)
            ->ofType('semantic')
            ->latest()
            ->first();

        // 4. Get the risk briefing that would have been generated for this entity
        $entityContext = $trace->fixes_applied ? $this->inferEntityContext($trace) : null;
        $recall = $ks->recall('docs', 8, $entityContext, $entityName);
        $riskBriefing = $recall['risk_briefing'];

        // 5. Render AAR output
        $this->line("=== AFTER-ACTION REVIEW: {$entityName} ===");
        $this->newLine();

        // Pipeline summary
        $structuralScore = $latestStructural ? "{$latestStructural->passed}/{$latestStructural->total} ({$latestStructural->percentage}%)" : 'N/A';
        $semanticScore = $latestSemantic ? "{$latestSemantic->passed}/{$latestSemantic->total} ({$latestSemantic->percentage}%)" : 'N/A';
        $this->line("Pipeline result: {$structuralScore} structural, {$semanticScore} semantic — {$trace->fix_iterations} fix iteration(s)");
        if ($trace->pipeline_duration) {
            $minutes = round($trace->pipeline_duration / 60, 1);
            $this->line("Pipeline duration: {$minutes} minutes");
        }
        $this->newLine();

        // Pre-flagged risks vs outcomes
        if (! empty($riskBriefing['high_risk'])) {
            $this->line('PRE-FLAGGED RISKS:');
            $failureCodeHits = $failureReports->map(fn (FailureReport $r) => $r->failure?->failure_code)->filter()->unique()->all();

            foreach ($riskBriefing['high_risk'] as $risk) {
                $code = $risk['failure_code'];
                $materialized = in_array($code, $failureCodeHits, true);
                $icon = $materialized ? '✗' : '✓';
                $outcome = $materialized
                    ? 'MATERIALIZED'
                    : 'DID NOT MATERIALIZE (mitigation applied)';
                $this->line("  {$icon} {$code} {$risk['title']} — {$outcome}");
            }
            $this->newLine();
        }

        // New discoveries (failure reports not in pre-flagged risks)
        $preFlaggedCodes = collect($riskBriefing['high_risk'] ?? [])->pluck('failure_code')->all();
        $newDiscoveries = $failureReports->filter(
            fn (FailureReport $r): bool => ! in_array($r->failure?->failure_code, $preFlaggedCodes, true),
        );

        if ($newDiscoveries->isNotEmpty()) {
            $this->line('NEW DISCOVERIES:');
            foreach ($newDiscoveries as $discovery) {
                $failureCode = $discovery->failure->failure_code ?? 'unknown';
                $this->line("  ! {$failureCode}: {$discovery->entity_name} — {$discovery->resolution_method}");
            }
            $this->newLine();
        }

        // Entity features for context matching
        if ($entityContext !== null) {
            $features = implode(', ', array_keys(array_filter($entityContext)));
            $this->line("ENTITY FEATURES: {$features}");
            $this->newLine();
        }

        // Summary
        $riskCount = count($riskBriefing['high_risk'] ?? []);
        $newCount = $newDiscoveries->count();
        $this->line("SUMMARY: {$riskCount} risks flagged, {$newCount} new discoveries, {$trace->fix_iterations} fix iterations");

        return self::SUCCESS;
    }

    /**
     * Infer entity context from a generation trace's fixes and args.
     *
     * @return array<string, bool>
     */
    private function inferEntityContext(GenerationTrace $trace): array
    {
        $context = [];
        $args = $trace->scaffolder_args ?? '';

        $context['has_states'] = str_contains($args, '--states');
        $context['has_media'] = str_contains($args, '--media') || str_contains($args, 'media');
        $context['has_pdf'] = str_contains($args, '--pdf');
        $context['has_notifications'] = str_contains($args, '--notifications');
        $context['has_widgets'] = str_contains($args, '--widgets');
        $context['has_enum'] = str_contains($args, ':enum:');

        return $context;
    }

    // ─── Cleanup ────────────────────────────────────────────────

    private function handleCleanup(): int
    {
        if (! $this->option('remove-faker-records')) {
            $this->components->error('Cleanup requires --remove-faker-records flag.');
            $this->line('  aicl:rlm cleanup --remove-faker-records    Remove faker-generated records');
            $this->line('  aicl:rlm cleanup --remove-faker-records --dry-run    Preview what would be removed');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $prefix = $dryRun ? '[DRY RUN] ' : '';

        $this->components->info("{$prefix}Cleaning up faker-generated RLM records...");

        // 1. RlmFailure: faker records have failure_code matching F-\d+ pattern (not BF-\d+)
        $fakerFailures = RlmFailure::query()
            ->whereRaw("failure_code ~ '^F-\\d+$'")
            ->get();

        $fakerFailureIds = $fakerFailures->pluck('id')->all();

        $this->line("{$prefix}  RlmFailure: {$fakerFailures->count()} faker records found (F-### pattern)");

        // 2. PreventionRule: rules linked to faker failures (orphaned by deletion)
        $orphanedRules = PreventionRule::query()
            ->whereIn('rlm_failure_id', $fakerFailureIds)
            ->get();

        $this->line("{$prefix}  PreventionRule: {$orphanedRules->count()} rules linked to faker failures");

        // 3. RlmLesson: lessons with source='factory' or without source that have no BF-* context tags
        $fakerLessons = RlmLesson::query()
            ->where(function ($query): void {
                $query->where('source', 'factory')
                    ->orWhere(function ($q): void {
                        $q->whereNull('source')
                            ->where('confidence', '<', 0.5);
                    });
            })
            ->get();

        $this->line("{$prefix}  RlmLesson: {$fakerLessons->count()} faker records found");

        $totalRemoved = $fakerFailures->count() + $orphanedRules->count() + $fakerLessons->count();

        if ($totalRemoved === 0) {
            $this->components->info('No faker records found. Database is clean.');

            return self::SUCCESS;
        }

        if (! $dryRun) {
            // Delete in correct order (rules before failures due to FK)
            $rulesDeleted = PreventionRule::query()
                ->whereIn('rlm_failure_id', $fakerFailureIds)
                ->forceDelete();

            $lessonsDeleted = RlmLesson::query()
                ->where(function ($query): void {
                    $query->where('source', 'factory')
                        ->orWhere(function ($q): void {
                            $q->whereNull('source')
                                ->where('confidence', '<', 0.5);
                        });
                })
                ->forceDelete();

            $failuresDeleted = RlmFailure::query()
                ->whereRaw("failure_code ~ '^F-\\d+$'")
                ->forceDelete();

            $this->newLine();
            $this->components->info("Cleanup complete: {$failuresDeleted} failures, {$rulesDeleted} rules, {$lessonsDeleted} lessons removed.");
        } else {
            $this->newLine();
            $this->components->info("[DRY RUN] Would remove {$totalRemoved} total records. Run without --dry-run to execute.");
        }

        return self::SUCCESS;
    }

    // ─── Distill ────────────────────────────────────────────────

    private function handleDistill(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $showStats = (bool) $this->option('stats');
        $agentFilter = $this->option('agent');

        $service = app(DistillationService::class);

        if ($showStats) {
            $stats = $service->getStats();
            $this->components->info('Distillation Coverage Stats:');
            $this->line("  Total base failures: {$stats['total_failures']}");
            $this->line("  Clustered failures: {$stats['clustered_failures']}");
            $this->line("  Total clusters: {$stats['total_clusters']}");
            $this->line("  Projected lessons: {$stats['total_lessons']}");
            $this->newLine();

            if (! empty($stats['agents'])) {
                $rows = [];
                foreach ($stats['agents'] as $agent => $count) {
                    $rows[] = [$agent, $count];
                }
                $this->table(['Agent', 'Lessons'], $rows);
            }

            $existing = DistilledLesson::query()->count();
            $this->line("  Existing distilled lessons: {$existing}");

            return self::SUCCESS;
        }

        if ($dryRun) {
            $stats = $service->getStats();
            $this->components->info('[DRY RUN] Distillation preview:');
            $this->line("  Would cluster {$stats['total_failures']} failures into {$stats['total_clusters']} clusters");
            $this->line("  Would generate {$stats['total_lessons']} distilled lessons");
            $this->newLine();

            foreach ($stats['agents'] as $agent => $count) {
                $this->line("    {$agent}: {$count} lessons");
            }

            return self::SUCCESS;
        }

        $this->components->info('Running distillation pipeline...');

        $result = $service->distill($agentFilter);

        $this->newLine();
        $this->components->info("Distilled {$result['clusters']} clusters into {$result['lessons']} agent lessons.");

        if (! empty($result['agents'])) {
            foreach ($result['agents'] as $agent => $count) {
                $this->line("  {$agent}: {$count} lessons");
            }
        }

        return self::SUCCESS;
    }

    // ─── Feedback ──────────────────────────────────────────────

    private function handleFeedback(): int
    {
        $entityName = $this->option('entity');
        $surfacedRaw = $this->option('surfaced');
        $failuresRaw = $this->option('failures');

        if (! $entityName) {
            $this->components->error('Feedback requires --entity option.');

            return self::FAILURE;
        }

        if (! $surfacedRaw) {
            $this->components->error('Feedback requires --surfaced option (comma-separated DL codes).');

            return self::FAILURE;
        }

        $surfacedCodes = array_map('trim', explode(',', $surfacedRaw));
        $actualFailureCodes = $failuresRaw
            ? array_map('trim', explode(',', $failuresRaw))
            : [];

        // Look up surfaced lessons
        $surfacedLessons = DistilledLesson::query()
            ->whereIn('lesson_code', $surfacedCodes)
            ->get();

        if ($surfacedLessons->isEmpty()) {
            $this->components->error('No distilled lessons found for the provided codes.');

            return self::FAILURE;
        }

        $service = app(DistillationService::class);

        $preventedLessons = collect();
        $ignoredLessons = collect();
        $coveredFailureCodes = collect();

        foreach ($surfacedLessons as $lesson) {
            $sourceFailureCodes = $lesson->source_failure_codes ?? [];

            // Track all failure codes covered by surfaced lessons
            $coveredFailureCodes = $coveredFailureCodes->merge($sourceFailureCodes);

            // Check if any of this lesson's source failures actually occurred
            $overlapping = array_intersect($sourceFailureCodes, $actualFailureCodes);

            if (empty($overlapping)) {
                // None of the source failures occurred — lesson prevented them
                $lesson->increment('prevented_count');
                $preventedLessons->push($lesson);
            } else {
                // Source failures still occurred — lesson was ignored/ineffective
                $lesson->increment('ignored_count');
                $ignoredLessons->push($lesson);
            }

            // Recalculate confidence after count update
            $lesson->refresh();
            $service->recalculateConfidence($lesson);
        }

        // Identify uncovered failures — actual failures not covered by any surfaced lesson
        $uncoveredFailureCodes = collect($actualFailureCodes)
            ->diff($coveredFailureCodes->unique())
            ->values();

        // Populate GenerationTrace KPI fields
        $this->populateTraceKpiFields(
            $entityName,
            $surfacedCodes,
            $actualFailureCodes,
            $coveredFailureCodes->unique()->values()->all(),
        );

        // Output summary
        $phase = $this->option('phase') ? " (phase {$this->option('phase')})" : '';
        $this->line("=== FEEDBACK SUMMARY: {$entityName}{$phase} ===");
        $this->newLine();

        $this->line("Surfaced lessons: {$surfacedLessons->count()}");
        $this->line('Actual failures:  '.count($actualFailureCodes));
        $this->newLine();

        if ($preventedLessons->isNotEmpty()) {
            $this->line('PREVENTED ('.($preventedLessons->count()).' lessons effective):');
            foreach ($preventedLessons as $lesson) {
                $this->line("  + {$lesson->lesson_code}: {$lesson->title} (confidence: {$lesson->confidence})");
            }
            $this->newLine();
        }

        if ($ignoredLessons->isNotEmpty()) {
            $this->line('IGNORED ('.($ignoredLessons->count()).' lessons ineffective):');
            foreach ($ignoredLessons as $lesson) {
                $this->line("  - {$lesson->lesson_code}: {$lesson->title} (confidence: {$lesson->confidence})");
            }
            $this->newLine();
        }

        if ($uncoveredFailureCodes->isNotEmpty()) {
            $this->line('UNCOVERED FAILURES (not addressed by any surfaced lesson):');
            foreach ($uncoveredFailureCodes as $code) {
                $failure = RlmFailure::query()
                    ->where('failure_code', $code)
                    ->first();
                $title = $failure ? $failure->title : 'Unknown failure';
                $this->line("  ! {$code}: {$title}");
            }
            $this->newLine();
            $this->components->warn('Uncovered failures may indicate a need for new distilled lessons. Run: aicl:rlm distill');
        }

        $this->newLine();
        $this->components->info("Feedback recorded: {$preventedLessons->count()} prevented, {$ignoredLessons->count()} ignored, {$uncoveredFailureCodes->count()} uncovered.");

        return self::SUCCESS;
    }

    // ─── Health ──────────────────────────────────────────────────

    private function handleHealth(): int
    {
        $kpi = app(KpiCalculator::class);
        $showVerdict = (bool) $this->option('verdict');

        $this->line('=== RLM SYSTEM HEALTH ===');
        $this->newLine();

        // KPI 1: Fix Iteration Trend
        $fixTrend = $kpi->fixIterationTrend();
        $this->line('PIPELINE VELOCITY:');
        if ($fixTrend['trend'] === 'INSUFFICIENT_DATA') {
            $this->line('  Insufficient data (need at least 5 pipeline runs)');
        } else {
            $arrow = $fixTrend['percent_change'] < 0 ? '↓' : ($fixTrend['percent_change'] > 0 ? '↑' : '→');
            $this->line("  Last 5 entities: avg {$fixTrend['recent_avg']} fix iterations ({$arrow} from {$fixTrend['baseline_avg']} over last 20)");
            $this->line("  Trend: {$fixTrend['trend']}");
        }
        $this->newLine();

        // KPI 2: Failure Profile
        $failureRatio = $kpi->failureRatio();
        $this->line('FAILURE PROFILE:');
        if ($failureRatio['trend'] === 'INSUFFICIENT_DATA') {
            $this->line('  No pipeline runs recorded yet');
        } else {
            $this->line("  Last {$failureRatio['runs_analyzed']} pipeline runs: {$failureRatio['known_total']} known failures prevented, {$failureRatio['novel_total']} novel discoveries");
            $this->line("  Known failure recurrence rate: {$failureRatio['recurrence_rate']}%");
        }
        $this->newLine();

        // KPI 3: Lesson Effectiveness
        $effectiveness = $kpi->lessonEffectiveness();
        $this->line('LESSON EFFECTIVENESS:');
        $this->line("  {$effectiveness['active_count']} active distilled lessons");

        if ($effectiveness['top_performers']->isNotEmpty()) {
            $this->line('  Top performers:');
            foreach ($effectiveness['top_performers'] as $performer) {
                $this->line("    {$performer['lesson_code']} {$performer['title']}: {$performer['effectiveness']}% ({$performer['prevented']}/{$performer['total']})");
            }
        }

        if ($effectiveness['underperformers']->isNotEmpty()) {
            $this->line('  Underperformers:');
            foreach ($effectiveness['underperformers'] as $performer) {
                $this->line("    {$performer['lesson_code']} {$performer['title']}: {$performer['effectiveness']}% ({$performer['prevented']}/{$performer['total']})");
            }
        }

        // Auto-retirement
        $retired = $kpi->autoRetireLessons();
        if (! empty($retired)) {
            $this->line('  Retired (auto): '.count($retired).' lessons dropped below 30% threshold');
            foreach ($retired as $code) {
                $this->line("    - {$code}");
            }
        }

        $this->newLine();

        // Verdict (optional)
        if ($showVerdict) {
            $verdict = $kpi->computeVerdict();
            $this->line("SYSTEM VERDICT: {$verdict['verdict']}");

            $fixIcon = $verdict['metrics']['fix_trend_pass'] ? '✓' : '✗';
            $recurrenceIcon = $verdict['metrics']['recurrence_pass'] ? '✓' : '✗';
            $effectivenessIcon = $verdict['metrics']['effectiveness_pass'] ? '✓' : '✗';

            $this->line("  {$fixIcon} Fix iteration trend (target: >20% improvement)");
            $this->line("  {$recurrenceIcon} Known failure recurrence (target: <30%)");
            $this->line("  {$effectivenessIcon} Lesson effectiveness (target: >60%)");

            $this->line("  Total pipeline runs: {$verdict['total_runs']}");
        }

        return self::SUCCESS;
    }

    // ─── Trace KPI Population ──────────────────────────────────

    /**
     * Populate KPI fields on the entity's latest GenerationTrace.
     *
     * A "known" failure is one whose code appears in any active DistilledLesson's
     * source_failure_codes. A "novel" failure is one not covered by any lesson.
     *
     * @param  array<int, string>  $surfacedCodes
     * @param  array<int, string>  $actualFailureCodes
     * @param  array<int, string>  $coveredFailureCodes
     */
    private function populateTraceKpiFields(
        string $entityName,
        array $surfacedCodes,
        array $actualFailureCodes,
        array $coveredFailureCodes,
    ): void {
        $trace = GenerationTrace::query()
            ->byEntity($entityName)
            ->latest()
            ->first();

        if (! $trace) {
            return;
        }

        // Collect all failure codes covered by any active distilled lesson
        $allLessonFailureCodes = DistilledLesson::query()
            ->where('is_active', true)
            ->whereNotNull('source_failure_codes')
            ->pluck('source_failure_codes')
            ->flatten()
            ->unique()
            ->values()
            ->all();

        // Classify each actual failure as known or novel
        $knownCount = 0;
        $novelCount = 0;

        foreach ($actualFailureCodes as $code) {
            if (in_array($code, $allLessonFailureCodes, true)) {
                $knownCount++;
            } else {
                $novelCount++;
            }
        }

        $trace->update([
            'known_failure_count' => $knownCount,
            'novel_failure_count' => $novelCount,
            'surfaced_lesson_codes' => $surfacedCodes,
            'failure_codes_hit' => $actualFailureCodes,
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────

    private function showUsage(): int
    {
        $this->components->error("Unknown action: {$this->argument('action')}");
        $this->newLine();
        $this->line('Available actions:');
        $this->line('  search {query}           Cross-table search (ES hybrid or deterministic)');
        $this->line('  recall                   Agent cheat sheet (default) or full risk briefing (--format=full|json)');
        $this->line('  learn {summary}          Record a new lesson');
        $this->line('  failures                 Query failures with filters');
        $this->line('  scores {entity?}         View validation score history');
        $this->line('  stats                    Aggregate knowledge system statistics');
        $this->line('  embed                    Generate/manage embeddings (--backfill, --stats)');
        $this->line('  index                    Manage ES indices (--all, --recreate, --stats)');
        $this->line('  aar                      After-Action Review for entity (requires --entity)');
        $this->line('  export                   Export to markdown/JSON from PostgreSQL');
        $this->line('  trace-save               Save a generation trace (requires --entity)');
        $this->line('  sync                     Sync data with hub (requires --push or --pull)');
        $this->line('  cleanup                  Remove faker-generated records (requires --remove-faker-records)');
        $this->line('  distill                  Run distillation pipeline (--dry-run, --agent=, --stats)');
        $this->line('  feedback                 Record feedback on surfaced lessons (--entity, --surfaced, --failures)');
        $this->line('  health                   RLM system health KPIs and diagnostics (--verdict for system verdict)');

        return self::FAILURE;
    }

    private function renderSearchResult(Model $result): void
    {
        $rawType = $result->getAttribute('_type') ?? class_basename($result);
        $type = strtoupper(match ($rawType) {
            'RlmFailure' => 'FAILURE',
            'RlmLesson' => 'LESSON',
            'RlmPattern' => 'PATTERN',
            'PreventionRule' => 'PREVENTION_RULE',
            'GoldenAnnotation' => 'GOLDEN_ANNOTATION',
            'DistilledLesson' => 'DISTILLED_LESSON',
            default => $rawType,
        });
        $relevance = $result->getAttribute('_relevance');
        $relevanceStr = $relevance !== null ? sprintf(' [%.2f]', $relevance) : '';

        if ($result instanceof RlmLesson) {
            $confidence = $result->confidence ?? 1.0;
            $verified = $result->is_verified ? 'verified' : 'unverified';
            $subtopic = $result->subtopic ? " > {$result->subtopic}" : '';
            $this->line("  [{$type}]{$relevanceStr} {$result->topic}{$subtopic} (confidence: {$confidence}, {$verified})");
            $this->line("    {$result->summary}");
        } elseif ($result instanceof RlmFailure) {
            $severity = $result->severity->value;
            $this->line("  [{$type}]{$relevanceStr} {$result->failure_code} (severity: {$severity}, status: {$result->status})");
            $this->line("    {$result->title}");
            if ($result->preventive_rule) {
                $this->line("    Preventive: {$result->preventive_rule}");
            }
        } elseif ($result instanceof RlmPattern) {
            $this->line("  [{$type}]{$relevanceStr} {$result->name} (weight: {$result->weight}, target: {$result->target})");
            $this->line("    {$result->description}");
        } elseif ($result instanceof PreventionRule) {
            $this->line("  [{$type}]{$relevanceStr} (confidence: {$result->confidence}, priority: {$result->priority})");
            $this->line("    {$result->rule_text}");
        } elseif ($result instanceof GoldenAnnotation) {
            $category = $result->category->value;
            $this->line("  [{$type}]{$relevanceStr} {$result->annotation_key} ({$category})");
            $this->line("    {$result->annotation_text}");
        } elseif ($result instanceof DistilledLesson) {
            $this->line("  [{$type}]{$relevanceStr} {$result->lesson_code} → {$result->target_agent} (phase: {$result->target_phase}, impact: {$result->impact_score})");
            $this->line("    {$result->title}");
        } else {
            $this->line("  [{$type}]{$relevanceStr} ".($result->getAttribute('name') ?? $result->getAttribute('title') ?? $result->getKey()));
        }

        $this->newLine();
    }

    /**
     * @param  array<int, class-string<Model>>  $modelClasses
     */
    private function showEmbedStats(array $modelClasses, EmbeddingService $embeddingService): int
    {
        $this->line('Embedding Coverage:');
        $this->line("  Driver: {$this->getDriverName($embeddingService)}");
        $this->line("  Dimension: {$embeddingService->getDimension()}");
        $this->newLine();

        $rows = [];
        foreach ($modelClasses as $class) {
            $label = class_basename($class);
            $total = $class::query()->count();

            $cached = 0;
            $class::query()->get()->each(function (Model $model) use (&$cached): void {
                if (in_array(HasEmbeddings::class, class_uses_recursive($model), true)
                    /** @phpstan-ignore method.notFound (method provided by HasEmbeddings trait) */
                    && $model->getCachedEmbedding() !== null) {
                    $cached++;
                }
            });

            $coverage = $total > 0 ? round(($cached / $total) * 100, 1) : 0;
            $rows[] = [$label, $total, $cached, $total - $cached, "{$coverage}%"];
        }

        $this->table(['Model', 'Total', 'Embedded', 'Missing', 'Coverage'], $rows);

        return self::SUCCESS;
    }

    private function showIndexStats(KnowledgeService $ks): int
    {
        $this->line('Elasticsearch Index Status:');
        $this->line('  Available: '.($ks->isElasticsearchAvailable() ? 'yes' : 'no'));
        $this->newLine();

        if (! $ks->isElasticsearchAvailable()) {
            $this->components->warn('Elasticsearch is not reachable. Cannot show index stats.');

            return self::SUCCESS;
        }

        $indices = [
            'aicl_rlm_failures' => RlmFailure::class,
            'aicl_rlm_lessons' => RlmLesson::class,
            'aicl_rlm_patterns' => RlmPattern::class,
            'aicl_prevention_rules' => PreventionRule::class,
            'aicl_golden_annotations' => GoldenAnnotation::class,
            'aicl_distilled_lessons' => DistilledLesson::class,
        ];

        $rows = [];
        foreach ($indices as $indexName => $modelClass) {
            $pgCount = $modelClass::query()->count();
            $esCount = $this->getEsDocCount($indexName);
            $rows[] = [$indexName, $pgCount, $esCount ?? 'N/A'];
        }

        $this->table(['Index', 'PG Records', 'ES Documents'], $rows);

        return self::SUCCESS;
    }

    private function getEsDocCount(string $index): ?int
    {
        try {
            $scheme = config('aicl.search.elasticsearch.scheme', 'http');
            $host = config('aicl.search.elasticsearch.host', 'elasticsearch');
            $port = config('aicl.search.elasticsearch.port', 9200);

            $response = Http::timeout(5)
                ->get("{$scheme}://{$host}:{$port}/{$index}/_count");

            if ($response->successful()) {
                return $response->json('count');
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function recreateEsIndex(string $indexName, array $mapping): void
    {
        $scheme = config('aicl.search.elasticsearch.scheme', 'http');
        $host = config('aicl.search.elasticsearch.host', 'elasticsearch');
        $port = config('aicl.search.elasticsearch.port', 9200);
        $baseUrl = "{$scheme}://{$host}:{$port}";

        try {
            Http::timeout(5)->delete("{$baseUrl}/{$indexName}");
        } catch (\Throwable) {
            // Index might not exist
        }

        try {
            $response = Http::timeout(5)->put("{$baseUrl}/{$indexName}", $mapping);

            if ($response->successful()) {
                $this->line("  Created index: {$indexName}");
            } else {
                $this->components->warn("  Failed to create index {$indexName}: {$response->body()}");
            }
        } catch (\Throwable $e) {
            $this->components->error("  Error creating index {$indexName}: {$e->getMessage()}");
        }
    }

    /**
     * @return array<int, class-string<Model>>
     */
    private function resolveEmbeddableModels(?string $filter): array
    {
        $all = [
            'RlmFailure' => RlmFailure::class,
            'RlmLesson' => RlmLesson::class,
            'RlmPattern' => RlmPattern::class,
            'PreventionRule' => PreventionRule::class,
            'GoldenAnnotation' => GoldenAnnotation::class,
            'DistilledLesson' => DistilledLesson::class,
        ];

        if ($filter !== null && isset($all[$filter])) {
            return [$all[$filter]];
        }

        return array_values($all);
    }

    /**
     * @return array<int, class-string<Model>>
     */
    private function resolveIndexableModels(?string $filter): array
    {
        return $this->resolveEmbeddableModels($filter);
    }

    private function getDriverName(EmbeddingService $service): string
    {
        $driver = $service->getDriver();

        return match (true) {
            $driver instanceof \Aicl\Rlm\Embeddings\NeuronAiEmbeddingAdapter => 'NeuronAI ('.config('aicl.rlm.embeddings.driver', 'auto').')',
            $driver instanceof \Aicl\Rlm\Embeddings\NullDriver => 'null (disabled)',
            default => class_basename($driver),
        };
    }
}
