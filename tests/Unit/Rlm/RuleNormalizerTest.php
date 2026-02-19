<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\RuleNormalizer;
use PHPUnit\Framework\TestCase;

class RuleNormalizerTest extends TestCase
{
    public function test_normalize_lowercases_text(): void
    {
        $this->assertSame(
            'always override searchablecolumns',
            RuleNormalizer::normalize('ALWAYS Override searchableColumns')
        );
    }

    public function test_normalize_strips_punctuation(): void
    {
        $this->assertSame(
            'use schemescomponentssection not formscomponents',
            RuleNormalizer::normalize('Use Schemes\Components\Section, NOT Forms\Components.')
        );
    }

    public function test_normalize_collapses_whitespace(): void
    {
        $this->assertSame(
            'always override searchablecolumns',
            RuleNormalizer::normalize('  always   override   searchableColumns  ')
        );
    }

    public function test_normalize_preserves_digits(): void
    {
        $this->assertSame(
            'phase 4 must run before phase 5',
            RuleNormalizer::normalize('Phase 4 must run before Phase 5.')
        );
    }

    public function test_normalize_returns_empty_string_for_whitespace_only(): void
    {
        $this->assertSame('', RuleNormalizer::normalize('   '));
    }

    public function test_normalize_returns_empty_string_for_punctuation_only(): void
    {
        $this->assertSame('', RuleNormalizer::normalize('!!!...'));
    }

    public function test_hash_returns_40_char_sha1(): void
    {
        $hash = RuleNormalizer::hash('Override searchableColumns');
        $this->assertSame(40, strlen($hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $hash);
    }

    public function test_hash_is_deterministic(): void
    {
        $hash1 = RuleNormalizer::hash('Override searchableColumns');
        $hash2 = RuleNormalizer::hash('Override searchableColumns');
        $this->assertSame($hash1, $hash2);
    }

    public function test_hash_is_stable_across_minor_wording_variations(): void
    {
        // Same semantic rule with different punctuation and casing
        $variations = [
            'Override searchableColumns() to list only real columns.',
            'override searchablecolumns() to list only real columns',
            'Override searchableColumns() to list only real columns!',
            '  Override  searchableColumns()  to  list  only  real  columns.  ',
        ];

        $hashes = array_map(fn (string $v) => RuleNormalizer::hash($v), $variations);
        $unique = array_unique($hashes);

        $this->assertCount(1, $unique, 'All minor wording variations should produce the same hash');
    }

    public function test_hash_differs_for_semantically_different_rules(): void
    {
        $hash1 = RuleNormalizer::hash('Override searchableColumns for existing columns');
        $hash2 = RuleNormalizer::hash('Always use Schemas namespace for Section');

        $this->assertNotSame($hash1, $hash2);
    }

    public function test_hash_preserves_digit_differences(): void
    {
        $hash1 = RuleNormalizer::hash('Phase 4 must validate');
        $hash2 = RuleNormalizer::hash('Phase 5 must validate');

        $this->assertNotSame($hash1, $hash2, 'Different digits should produce different hashes');
    }

    public function test_normalize_handles_underscores_as_word_characters(): void
    {
        // \w includes underscores, so they should be preserved
        $this->assertSame(
            'use has_standard_scopes trait',
            RuleNormalizer::normalize('Use has_standard_scopes trait.')
        );
    }
}
