<?php

namespace Aicl\Tests\Unit\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineContextCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $pipelineDir;

    private string $pipelineFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pipelineDir = base_path('.claude/planning/pipeline/active');
        $this->pipelineFile = $this->pipelineDir.'/PIPELINE-TestEntity.md';

        if (! is_dir($this->pipelineDir)) {
            mkdir($this->pipelineDir, 0755, true);
        }

        file_put_contents($this->pipelineFile, $this->getSamplePipelineContent());
    }

    protected function tearDown(): void
    {
        if (file_exists($this->pipelineFile)) {
            unlink($this->pipelineFile);
        }

        parent::tearDown();
    }

    public function test_extracts_specific_phase_section(): void
    {
        $this->artisan('aicl:pipeline-context', [
            'entity' => 'TestEntity',
            '--phase' => '3',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Phase 3: Generate');
    }

    public function test_extracts_agent_phases(): void
    {
        $this->artisan('aicl:pipeline-context', [
            'entity' => 'TestEntity',
            '--agent' => 'architect',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Phase 3: Generate');
    }

    public function test_includes_header_when_requested(): void
    {
        $this->artisan('aicl:pipeline-context', [
            'entity' => 'TestEntity',
            '--phase' => '1',
            '--header' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Pipeline: TestEntity');
    }

    public function test_fails_for_nonexistent_entity(): void
    {
        $this->artisan('aicl:pipeline-context', [
            'entity' => 'Nonexistent',
        ])
            ->assertFailed()
            ->expectsOutputToContain('No pipeline document found');
    }

    public function test_outputs_full_document_without_filters(): void
    {
        $this->artisan('aicl:pipeline-context', [
            'entity' => 'TestEntity',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Phase 1: Plan');
    }

    private function getSamplePipelineContent(): string
    {
        return <<<'MD'
# Pipeline: TestEntity

| Field | Value |
|-------|-------|
| **Status** | Phase 3: Generate |
| **Created** | 2026-02-10 |
| **Last Agent** | /solutions |

---

## Phase 1: Plan
**Agent:** /pm
**Status:** PASS
**Completed:** 2026-02-10

### Entity Spec
- **Name:** TestEntity
- **Table:** test_entities

---

## Phase 2: Design
**Agent:** /solutions
**Status:** PASS
**Completed:** 2026-02-10

### Design Blueprint
Standard golden pattern.

---

## Phase 3: Generate
**Agent:** /architect
**Status:** Not Started

### Files Created
| File | Path |
|------|------|
| Model | `app/Models/TestEntity.php` |

---

## Phase 4: Validate (Pre-Registration)

### RLM Validation
**Agent:** /rlm
**Status:** Not Started

---

## Phase 5: Register
**Agent:** /architect
**Status:** Not Started

---

## Phase 8: Complete
**Agent:** /docs
**Status:** Not Started
MD;
    }
}
