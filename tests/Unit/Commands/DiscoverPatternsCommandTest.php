<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Models\GenerationTrace;
use Aicl\Models\RlmScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoverPatternsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_registered(): void
    {
        $this->artisan('aicl:discover-patterns')
            ->assertSuccessful();
    }

    public function test_no_candidates_when_no_traces(): void
    {
        $this->artisan('aicl:discover-patterns')
            ->assertSuccessful()
            ->expectsOutputToContain('No pattern candidates found');
    }

    public function test_discovers_candidates_from_recurring_fixes(): void
    {
        $fixes = [
            ['pattern' => 'searchableColumns', 'fix' => 'Override searchableColumns method', 'target' => 'model'],
        ];

        GenerationTrace::factory()->create([
            'entity_name' => 'Entity1',
            'scaffolder_args' => '--fields name:string',
            'fixes_applied' => $fixes,
            'is_processed' => false,
        ]);
        GenerationTrace::factory()->create([
            'entity_name' => 'Entity2',
            'scaffolder_args' => '--fields name:string',
            'fixes_applied' => $fixes,
            'is_processed' => false,
        ]);

        $outputDir = sys_get_temp_dir().'/discover_cmd_'.uniqid();

        $this->artisan('aicl:discover-patterns', ['--output' => $outputDir])
            ->assertSuccessful()
            ->expectsOutputToContain('candidate pattern(s)')
            ->expectsOutputToContain('Candidates exported to');

        // Verify file was created
        $files = glob($outputDir.'/candidates_*.md');
        $this->assertNotEmpty($files);

        // Verify traces were marked as processed
        $unprocessed = GenerationTrace::query()->unprocessed()->count();
        $this->assertSame(0, $unprocessed);

        // Cleanup
        array_map('unlink', $files);
        rmdir($outputDir);
    }

    public function test_stale_mode_with_no_scores(): void
    {
        $this->artisan('aicl:discover-patterns', ['--stale' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No stale patterns detected');
    }

    public function test_stale_mode_detects_always_passing(): void
    {
        $details = [
            ['name' => 'model.has_fillable', 'passed' => true, 'severity' => 'error'],
            ['name' => 'model.has_factory', 'passed' => false, 'severity' => 'error'],
        ];

        RlmScore::factory()->structural()->create(['entity_name' => 'Entity1', 'details' => $details]);
        RlmScore::factory()->structural()->create(['entity_name' => 'Entity2', 'details' => $details]);
        RlmScore::factory()->structural()->create(['entity_name' => 'Entity3', 'details' => $details]);

        $this->artisan('aicl:discover-patterns', ['--stale' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('stale pattern(s)')
            ->expectsOutputToContain('always pass');
    }

    public function test_min_occurrences_option(): void
    {
        $fixes = [['pattern' => 'testFix', 'fix' => 'Fix it']];

        // Only 2 traces — below threshold of 3
        GenerationTrace::factory()->create([
            'entity_name' => 'E1',
            'scaffolder_args' => '--fields name:string',
            'fixes_applied' => $fixes,
            'is_processed' => false,
        ]);
        GenerationTrace::factory()->create([
            'entity_name' => 'E2',
            'scaffolder_args' => '--fields name:string',
            'fixes_applied' => $fixes,
            'is_processed' => false,
        ]);

        $this->artisan('aicl:discover-patterns', ['--min-occurrences' => 3])
            ->assertSuccessful()
            ->expectsOutputToContain('No pattern candidates found');
    }
}
