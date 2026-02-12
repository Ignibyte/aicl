<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Models\GenerationTrace;
use Aicl\Models\RlmScore;
use Aicl\Rlm\PatternCandidate;
use Aicl\Rlm\PatternDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private PatternDiscovery $discovery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->discovery = new PatternDiscovery;
    }

    // ─── analyzeTraces ──────────────────────────────────────────

    public function test_analyze_traces_returns_empty_when_no_traces(): void
    {
        $this->assertSame([], $this->discovery->analyzeTraces());
    }

    public function test_analyze_traces_returns_empty_when_no_fixes(): void
    {
        GenerationTrace::factory()->create([
            'entity_name' => 'Entity1',
            'fixes_applied' => null,
            'is_processed' => false,
        ]);

        $this->assertSame([], $this->discovery->analyzeTraces());
    }

    public function test_analyze_traces_finds_recurring_fix_pattern(): void
    {
        $fixes = [
            ['pattern' => 'searchableColumns', 'fix' => 'Override searchableColumns method', 'target' => 'model'],
        ];

        GenerationTrace::factory()->create([
            'entity_name' => 'Entity1',
            'fixes_applied' => $fixes,
            'is_processed' => false,
        ]);
        GenerationTrace::factory()->create([
            'entity_name' => 'Entity2',
            'fixes_applied' => $fixes,
            'is_processed' => false,
        ]);

        $candidates = $this->discovery->analyzeTraces(minOccurrences: 2);

        $this->assertCount(1, $candidates);
        $this->assertInstanceOf(PatternCandidate::class, $candidates[0]);
        $this->assertStringContainsString('searchablecolumns', $candidates[0]->name);
        $this->assertSame('model', $candidates[0]->target);
        $this->assertSame(2, $candidates[0]->occurrences);
        $this->assertSame('fix_analysis', $candidates[0]->source);
    }

    public function test_analyze_traces_respects_min_occurrences(): void
    {
        $fixes = [
            ['pattern' => 'searchableColumns', 'fix' => 'Override method'],
        ];

        GenerationTrace::factory()->create([
            'entity_name' => 'Entity1',
            'fixes_applied' => $fixes,
            'is_processed' => false,
        ]);

        $candidates = $this->discovery->analyzeTraces(minOccurrences: 2);
        $this->assertSame([], $candidates);
    }

    public function test_analyze_traces_respects_min_confidence(): void
    {
        $fix1 = [['pattern' => 'rare_fix', 'fix' => 'Fix something']];
        $fix2 = [['pattern' => 'common_fix', 'fix' => 'Fix other thing']];

        GenerationTrace::factory()->create(['entity_name' => 'E1', 'fixes_applied' => $fix1, 'is_processed' => false]);
        GenerationTrace::factory()->create(['entity_name' => 'E2', 'fixes_applied' => $fix1, 'is_processed' => false]);
        GenerationTrace::factory()->create(['entity_name' => 'E3', 'fixes_applied' => $fix2, 'is_processed' => false]);
        GenerationTrace::factory()->create(['entity_name' => 'E4', 'fixes_applied' => $fix2, 'is_processed' => false]);
        GenerationTrace::factory()->create(['entity_name' => 'E5', 'fixes_applied' => $fix2, 'is_processed' => false]);

        $candidates = $this->discovery->analyzeTraces(minOccurrences: 2, minConfidence: 0.5);

        $names = array_map(fn (PatternCandidate $c): string => $c->name, $candidates);
        $this->assertNotEmpty($candidates);
        $this->assertTrue(
            in_array('candidate.common_fix', $names, true),
            'common_fix should be a candidate'
        );
    }

    public function test_analyze_traces_deduplicates_same_entity(): void
    {
        $fixes = [['pattern' => 'pdfView', 'fix' => 'Rename to pdfView()']];

        GenerationTrace::factory()->create([
            'entity_name' => 'Entity1',
            'fixes_applied' => $fixes,
            'is_processed' => false,
        ]);
        GenerationTrace::factory()->create([
            'entity_name' => 'Entity1',
            'fixes_applied' => $fixes,
            'is_processed' => false,
        ]);

        $candidates = $this->discovery->analyzeTraces(minOccurrences: 2);
        $this->assertSame([], $candidates);
    }

    public function test_analyze_traces_handles_multiple_fixes_per_trace(): void
    {
        $fixes = [
            ['pattern' => 'searchableColumns', 'fix' => 'Override method', 'target' => 'model'],
            ['pattern' => 'pdfView', 'fix' => 'Rename to pdfView()', 'target' => 'filament'],
        ];

        GenerationTrace::factory()->create(['entity_name' => 'E1', 'fixes_applied' => $fixes, 'is_processed' => false]);
        GenerationTrace::factory()->create(['entity_name' => 'E2', 'fixes_applied' => $fixes, 'is_processed' => false]);

        $candidates = $this->discovery->analyzeTraces(minOccurrences: 2);
        $this->assertCount(2, $candidates);

        $names = array_map(fn (PatternCandidate $c): string => $c->name, $candidates);
        $this->assertContains('candidate.searchablecolumns', $names);
        $this->assertContains('candidate.pdfview', $names);
    }

    // ─── detectStalePatterns ────────────────────────────────────

    public function test_detect_stale_returns_empty_when_no_scores(): void
    {
        $this->assertSame([], $this->discovery->detectStalePatterns());
    }

    public function test_detect_stale_finds_always_passing_patterns(): void
    {
        $details = [
            ['name' => 'model.has_fillable', 'passed' => true, 'severity' => 'error'],
            ['name' => 'model.has_factory', 'passed' => true, 'severity' => 'error'],
            ['name' => 'model.has_search', 'passed' => false, 'severity' => 'warning'],
        ];

        RlmScore::factory()->structural()->create(['entity_name' => 'Entity1', 'details' => $details]);
        RlmScore::factory()->structural()->create(['entity_name' => 'Entity2', 'details' => $details]);
        RlmScore::factory()->structural()->create(['entity_name' => 'Entity3', 'details' => $details]);

        $stale = $this->discovery->detectStalePatterns(minEntities: 3);

        $names = array_column($stale, 'name');
        $this->assertContains('model.has_fillable', $names);
        $this->assertContains('model.has_factory', $names);
        $this->assertNotContains('model.has_search', $names);
    }

    public function test_detect_stale_respects_min_entities(): void
    {
        $details = [
            ['name' => 'model.has_fillable', 'passed' => true, 'severity' => 'error'],
        ];

        RlmScore::factory()->structural()->create(['entity_name' => 'Entity1', 'details' => $details]);
        RlmScore::factory()->structural()->create(['entity_name' => 'Entity2', 'details' => $details]);

        $stale = $this->discovery->detectStalePatterns(minEntities: 3);
        $this->assertSame([], $stale);
    }

    public function test_detect_stale_only_counts_latest_per_entity(): void
    {
        $failDetails = [['name' => 'model.has_fillable', 'passed' => false]];
        $passDetails = [['name' => 'model.has_fillable', 'passed' => true]];

        RlmScore::factory()->structural()->create(['entity_name' => 'Entity1', 'details' => $failDetails]);
        RlmScore::factory()->structural()->create(['entity_name' => 'Entity1', 'details' => $passDetails]);
        RlmScore::factory()->structural()->create(['entity_name' => 'Entity2', 'details' => $passDetails]);
        RlmScore::factory()->structural()->create(['entity_name' => 'Entity3', 'details' => $passDetails]);

        $stale = $this->discovery->detectStalePatterns(minEntities: 3);

        $found = array_filter($stale, fn (array $s): bool => $s['name'] === 'model.has_fillable');
        $this->assertCount(1, $found);
        $this->assertSame(3, reset($found)['entity_count']);
    }

    public function test_detect_stale_ignores_semantic_scores(): void
    {
        $details = [['name' => 'semantic.test', 'passed' => true]];

        RlmScore::factory()->semantic()->create(['entity_name' => 'E1', 'details' => $details]);
        RlmScore::factory()->semantic()->create(['entity_name' => 'E2', 'details' => $details]);
        RlmScore::factory()->semantic()->create(['entity_name' => 'E3', 'details' => $details]);

        $stale = $this->discovery->detectStalePatterns(minEntities: 3);
        $this->assertSame([], $stale);
    }

    // ─── markProcessed ──────────────────────────────────────────

    public function test_mark_processed_updates_traces(): void
    {
        $trace1 = GenerationTrace::factory()->create(['is_processed' => false]);
        $trace2 = GenerationTrace::factory()->create(['is_processed' => false]);

        $count = $this->discovery->markProcessed([$trace1->id, $trace2->id]);
        $this->assertSame(2, $count);

        $this->assertTrue($trace1->fresh()->is_processed);
        $this->assertTrue($trace2->fresh()->is_processed);
    }

    // ─── exportCandidates ───────────────────────────────────────

    public function test_export_candidates_creates_markdown_file(): void
    {
        $outputDir = sys_get_temp_dir().'/discovery_test_'.uniqid();

        $candidates = [
            new PatternCandidate(
                name: 'candidate.searchable_columns',
                description: 'Override searchableColumns method',
                target: 'model',
                suggestedRegex: 'searchableColumns',
                confidence: 0.85,
                occurrences: 3,
                source: 'fix_analysis',
            ),
        ];

        $path = $this->discovery->exportCandidates($candidates, $outputDir);

        $this->assertFileExists($path);
        $this->assertStringContainsString('candidates_', basename($path));

        $content = file_get_contents($path);
        $this->assertStringContainsString('# Pattern Discovery Candidates', $content);
        $this->assertStringContainsString('candidate.searchable_columns', $content);
        $this->assertStringContainsString('85.0%', $content);
        $this->assertStringContainsString('NEVER auto-promoted', $content);

        unlink($path);
        rmdir($outputDir);
    }

    public function test_export_candidates_creates_output_directory(): void
    {
        $outputDir = sys_get_temp_dir().'/discovery_nested_'.uniqid().'/sub';

        $path = $this->discovery->exportCandidates([], $outputDir);

        $this->assertFileExists($path);
        $this->assertDirectoryExists($outputDir);

        unlink($path);
        rmdir($outputDir);
        rmdir(dirname($outputDir));
    }

    public function test_export_includes_all_candidates(): void
    {
        $outputDir = sys_get_temp_dir().'/discovery_multi_'.uniqid();

        $candidates = [
            new PatternCandidate(name: 'candidate.a', description: 'First', target: 'model', suggestedRegex: 'a'),
            new PatternCandidate(name: 'candidate.b', description: 'Second', target: 'factory', suggestedRegex: 'b'),
            new PatternCandidate(name: 'candidate.c', description: 'Third', target: 'test', suggestedRegex: 'c'),
        ];

        $path = $this->discovery->exportCandidates($candidates, $outputDir);
        $content = file_get_contents($path);

        $this->assertStringContainsString('**Candidates:** 3', $content);
        $this->assertStringContainsString('### candidate.a', $content);
        $this->assertStringContainsString('### candidate.b', $content);
        $this->assertStringContainsString('### candidate.c', $content);

        unlink($path);
        rmdir($outputDir);
    }
}
