<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Models\RlmFailure;
use Aicl\Rlm\DistillationService;
use Aicl\Rlm\KnowledgeService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RlmCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id' => 1]);
    }

    private function seedTestData(): void
    {
        $ks = app(KnowledgeService::class);
        $ks->addLesson(
            topic: 'filament',
            summary: 'Section is in Schemas namespace',
            detail: 'Use Filament\Schemas\Components\Section',
            tags: 'filament, section',
        );
        $ks->addLesson(
            topic: 'testing',
            summary: 'Seed permissions on both guards',
            detail: 'Must seed on web and api',
        );
        $ks->recordFailure([
            'failure_code' => 'BF-001',
            'tier' => 'base',
            'category' => 'scaffolding',
            'title' => 'searchableColumns defaults',
            'description' => 'HasStandardScopes defaults to name, title',
            'severity' => 'critical',
            'preventive_rule' => 'Override searchableColumns',
        ]);
    }

    public function test_search_returns_matching_results(): void
    {
        $this->seedTestData();

        $this->artisan('aicl:rlm', ['action' => 'search', 'query' => 'Section'])
            ->assertSuccessful()
            ->expectsOutputToContain('Section');
    }

    public function test_search_with_type_filter(): void
    {
        $this->seedTestData();

        $this->artisan('aicl:rlm', ['action' => 'search', 'query' => 'Section', '--type' => 'lesson'])
            ->assertSuccessful()
            ->expectsOutputToContain('LESSON');
    }

    public function test_search_requires_query(): void
    {
        $this->artisan('aicl:rlm', ['action' => 'search'])
            ->assertFailed();
    }

    public function test_recall_requires_agent_and_phase(): void
    {
        $this->artisan('aicl:rlm', ['action' => 'recall'])
            ->assertFailed();
    }

    public function test_recall_returns_filtered_context(): void
    {
        $this->seedTestData();

        $this->artisan('aicl:rlm', [
            'action' => 'recall',
            '--agent' => 'architect',
            '--phase' => '3',
            '--format' => 'full',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('RELEVANT FAILURES')
            ->expectsOutputToContain('RELEVANT LESSONS');
    }

    public function test_learn_creates_lesson(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            'query' => 'Dusk cookies cause timeout',
            '--topic' => 'dusk',
            '--subtopic' => 'session-management',
            '--tags' => 'dusk, cookies, timeout',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('recorded');

        $this->assertDatabaseHas('rlm_lessons', [
            'topic' => 'dusk',
            'summary' => 'Dusk cookies cause timeout',
        ]);
    }

    public function test_learn_requires_topic(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'learn',
            'query' => 'Some lesson',
        ])
            ->assertFailed();
    }

    public function test_failures_returns_filtered_results(): void
    {
        $this->seedTestData();

        $this->artisan('aicl:rlm', ['action' => 'failures'])
            ->assertSuccessful()
            ->expectsOutputToContain('FAILURES');
    }

    public function test_failures_with_severity_filter(): void
    {
        $this->seedTestData();

        $this->artisan('aicl:rlm', ['action' => 'failures', '--severity' => 'critical'])
            ->assertSuccessful()
            ->expectsOutputToContain('BF-001');
    }

    public function test_scores_returns_entity_history(): void
    {
        $ks = app(KnowledgeService::class);
        $ks->recordScore('Project', 'structural', 42, 42, 100.0);

        $this->artisan('aicl:rlm', ['action' => 'scores', '--entity' => 'Project'])
            ->assertSuccessful()
            ->expectsOutputToContain('Project');
    }

    public function test_stats_returns_summary(): void
    {
        $this->seedTestData();

        $this->artisan('aicl:rlm', ['action' => 'stats'])
            ->assertSuccessful()
            ->expectsOutputToContain('Knowledge System Summary')
            ->expectsOutputToContain('Patterns')
            ->expectsOutputToContain('Failures')
            ->expectsOutputToContain('Lessons');
    }

    public function test_export_creates_markdown_files(): void
    {
        $this->seedTestData();

        $outputDir = sys_get_temp_dir().'/rlm_cmd_export_'.uniqid();

        $this->artisan('aicl:rlm', ['action' => 'export', '--output' => $outputDir])
            ->assertSuccessful()
            ->expectsOutputToContain('Exported');

        $this->assertFileExists($outputDir.'/failures.md');
        $this->assertFileExists($outputDir.'/lessons.md');
        $this->assertFileExists($outputDir.'/scores.md');

        // Cleanup
        array_map('unlink', glob($outputDir.'/*'));
        rmdir($outputDir);
    }

    public function test_unknown_action_shows_usage(): void
    {
        $this->artisan('aicl:rlm', ['action' => 'nonexistent'])
            ->assertFailed()
            ->expectsOutputToContain('Unknown action');
    }

    public function test_graceful_degradation_on_search_fallback(): void
    {
        // KnowledgeService uses deterministic fallback when ES is unavailable
        // (which it is in test environment). It should still succeed.
        $this->artisan('aicl:rlm', ['action' => 'search', 'query' => 'test'])
            ->assertSuccessful();
    }

    public function test_validate_runs_successfully(): void
    {
        // Validate User entity — should run and output a score
        $this->artisan('aicl:validate', ['name' => 'User'])
            ->expectsOutputToContain('Score');
    }

    public function test_trace_save_requires_entity(): void
    {
        $this->artisan('aicl:rlm', ['action' => 'trace-save'])
            ->assertFailed()
            ->expectsOutputToContain('--entity');
    }

    public function test_trace_save_records_trace(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'trace-save',
            '--entity' => 'TestEntity',
            '--scaffolder-args' => 'aicl:make-entity TestEntity --fields="name:string"',
            '--file-manifest' => '{"model":"app/Models/TestEntity.php"}',
            '--structural-score' => '95',
            '--fix-iterations' => '1',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('saved for entity: TestEntity');

        $this->assertDatabaseHas('generation_traces', [
            'entity_name' => 'TestEntity',
            'structural_score' => 95,
            'fix_iterations' => 1,
        ]);
    }

    public function test_trace_save_with_semantic_score(): void
    {
        $this->artisan('aicl:rlm', [
            'action' => 'trace-save',
            '--entity' => 'TestEntity',
            '--structural-score' => '100',
            '--semantic-score' => '93.5',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Semantic score: 93.5%');

        $this->assertDatabaseHas('generation_traces', [
            'entity_name' => 'TestEntity',
            'semantic_score' => 93.5,
        ]);
    }

    public function test_trace_save_with_fixes_json(): void
    {
        $fixes = json_encode([
            ['pattern' => 'searchableColumns', 'fix' => 'Override method'],
            ['pattern' => 'pdfView', 'fix' => 'Rename to pdfView()'],
        ]);

        $this->artisan('aicl:rlm', [
            'action' => 'trace-save',
            '--entity' => 'TestEntity',
            '--fixes' => $fixes,
            '--fix-iterations' => '2',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Fix iterations: 2');

        $this->assertDatabaseHas('generation_traces', [
            'entity_name' => 'TestEntity',
            'fix_iterations' => 2,
        ]);
    }

    public function test_trace_save_shows_in_usage(): void
    {
        $this->artisan('aicl:rlm', ['action' => 'nonexistent'])
            ->expectsOutputToContain('trace-save');
    }

    public function test_recall_cheatsheet_is_default_format(): void
    {
        $this->seedBaseFailuresAndDistill();

        $this->artisan('aicl:rlm', [
            'action' => 'recall',
            '--agent' => 'architect',
            '--phase' => '3',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('CHEAT SHEET')
            ->expectsOutputToContain('TOP');
    }

    public function test_recall_cheatsheet_shows_lessons_and_rules(): void
    {
        $this->seedBaseFailuresAndDistill();

        $this->artisan('aicl:rlm', [
            'action' => 'recall',
            '--agent' => 'architect',
            '--phase' => '3',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('LESSONS')
            ->expectsOutputToContain('DL-');
    }

    public function test_recall_cheatsheet_falls_back_to_full_when_no_lessons(): void
    {
        $this->seedTestData();

        $this->artisan('aicl:rlm', [
            'action' => 'recall',
            '--agent' => 'architect',
            '--phase' => '3',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Falling back to full recall')
            ->expectsOutputToContain('RELEVANT FAILURES');
    }

    public function test_recall_json_format(): void
    {
        $this->seedBaseFailuresAndDistill();

        \Illuminate\Support\Facades\Artisan::call('aicl:rlm', [
            'action' => 'recall',
            '--agent' => 'architect',
            '--phase' => '3',
            '--format' => 'json',
        ]);

        $output = \Illuminate\Support\Facades\Artisan::output();
        $decoded = json_decode(trim($output), true);

        $this->assertNotNull($decoded, "JSON output should be valid JSON. Got: {$output}");
        $this->assertSame('architect', $decoded['agent']);
        $this->assertSame(3, $decoded['phase']);
        $this->assertArrayHasKey('lessons', $decoded);
        $this->assertArrayHasKey('when_then_rules', $decoded);
    }

    public function test_recall_full_format(): void
    {
        $this->seedTestData();

        $this->artisan('aicl:rlm', [
            'action' => 'recall',
            '--agent' => 'architect',
            '--phase' => '3',
            '--format' => 'full',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('RELEVANT FAILURES')
            ->expectsOutputToContain('RELEVANT LESSONS')
            ->expectsOutputToContain('RECENT SCORES');
    }

    public function test_recall_cheatsheet_with_entity_context(): void
    {
        $this->seedBaseFailuresAndDistill();

        $this->artisan('aicl:rlm', [
            'action' => 'recall',
            '--agent' => 'architect',
            '--phase' => '3',
            '--entity' => 'Task',
            '--entity-context' => '{"has_states":true}',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('CHEAT SHEET');
    }

    private function seedBaseFailuresAndDistill(): void
    {
        RlmFailure::factory()->create([
            'failure_code' => 'BF-001',
            'category' => FailureCategory::Scaffolding,
            'severity' => FailureSeverity::High,
            'preventive_rule' => 'Override searchableColumns.',
            'owner_id' => $this->admin->id,
        ]);

        RlmFailure::factory()->create([
            'failure_code' => 'BF-012',
            'category' => FailureCategory::Filament,
            'severity' => FailureSeverity::Critical,
            'preventive_rule' => 'Use Schemas namespace for Section/Grid.',
            'owner_id' => $this->admin->id,
        ]);

        app(DistillationService::class)->distill();
    }
}
