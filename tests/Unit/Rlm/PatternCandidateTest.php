<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\EntityPattern;
use Aicl\Rlm\PatternCandidate;
use PHPUnit\Framework\TestCase;

class PatternCandidateTest extends TestCase
{
    public function test_has_required_properties(): void
    {
        $candidate = new PatternCandidate(
            name: 'candidate.test_check',
            description: 'Test has a check method',
            target: 'test',
            suggestedRegex: 'function test_',
            severity: 'warning',
            weight: 1.0,
            confidence: 0.85,
            occurrences: 3,
            source: 'fix_analysis',
        );

        $this->assertSame('candidate.test_check', $candidate->name);
        $this->assertSame('Test has a check method', $candidate->description);
        $this->assertSame('test', $candidate->target);
        $this->assertSame('function test_', $candidate->suggestedRegex);
        $this->assertSame('warning', $candidate->severity);
        $this->assertSame(1.0, $candidate->weight);
        $this->assertSame(0.85, $candidate->confidence);
        $this->assertSame(3, $candidate->occurrences);
        $this->assertSame('fix_analysis', $candidate->source);
    }

    public function test_to_entity_pattern(): void
    {
        $candidate = new PatternCandidate(
            name: 'candidate.test_check',
            description: 'Test pattern',
            target: 'model',
            suggestedRegex: 'use HasFactory',
            severity: 'error',
            weight: 2.0,
        );

        $pattern = $candidate->toEntityPattern();

        $this->assertInstanceOf(EntityPattern::class, $pattern);
        $this->assertSame('candidate.test_check', $pattern->name);
        $this->assertSame('Test pattern', $pattern->description);
        $this->assertSame('model', $pattern->target);
        $this->assertSame('use HasFactory', $pattern->check);
        $this->assertSame('error', $pattern->severity);
        $this->assertSame(2.0, $pattern->weight);
    }

    public function test_to_markdown(): void
    {
        $candidate = new PatternCandidate(
            name: 'candidate.scope_check',
            description: 'Model has scopeActive method',
            target: 'model',
            suggestedRegex: 'scopeActive',
            severity: 'warning',
            weight: 1.5,
            confidence: 0.75,
            occurrences: 4,
            source: 'fix_analysis',
        );

        $md = $candidate->toMarkdown();

        $this->assertStringContainsString('### candidate.scope_check', $md);
        $this->assertStringContainsString('**Description:** Model has scopeActive method', $md);
        $this->assertStringContainsString('**Target:** model', $md);
        $this->assertStringContainsString('`scopeActive`', $md);
        $this->assertStringContainsString('**Confidence:** 75.0%', $md);
        $this->assertStringContainsString('**Occurrences:** 4', $md);
        $this->assertStringContainsString('**Source:** fix_analysis', $md);
    }

    public function test_defaults(): void
    {
        $candidate = new PatternCandidate(
            name: 'test',
            description: 'test',
            target: 'model',
            suggestedRegex: 'test',
        );

        $this->assertSame('warning', $candidate->severity);
        $this->assertSame(1.0, $candidate->weight);
        $this->assertSame(0.0, $candidate->confidence);
        $this->assertSame(0, $candidate->occurrences);
        $this->assertSame('', $candidate->source);
    }
}
