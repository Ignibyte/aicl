<?php

namespace Aicl\Enums;

enum KnowledgeLinkType: string
{
    case GoldenEntityFile = 'golden_entity_file';
    case TestCase = 'test_case';
    case CommitSha = 'commit_sha';
    case DocAnchor = 'doc_anchor';

    public function label(): string
    {
        return match ($this) {
            self::GoldenEntityFile => 'Golden Entity File',
            self::TestCase => 'Test Case',
            self::CommitSha => 'Commit SHA',
            self::DocAnchor => 'Documentation Anchor',
        };
    }

    /**
     * Proof strength for recall ranking (higher = stronger proof).
     * tests > files > commits > docs
     */
    public function proofStrength(): int
    {
        return match ($this) {
            self::TestCase => 4,
            self::GoldenEntityFile => 3,
            self::CommitSha => 2,
            self::DocAnchor => 1,
        };
    }
}
