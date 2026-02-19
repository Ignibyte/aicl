<?php

namespace Aicl\Rlm;

/**
 * Single canonical source for rule text normalization.
 *
 * Used by the RlmFailure model mutator, the CLI learn handler, and backfill scripts
 * to ensure consistent rule_norm/rule_hash computation. Digits are preserved
 * (e.g., "phase 4" and "phase 5" remain distinct).
 */
class RuleNormalizer
{
    /**
     * Normalize rule text: lowercase, strip non-word/non-space characters,
     * collapse whitespace, trim.
     */
    public static function normalize(string $rule): string
    {
        // Strip non-word, non-space characters (preserves digits)
        $normalized = preg_replace('/[^\w\s]/', '', $rule);

        // Collapse whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return strtolower(trim($normalized));
    }

    /**
     * Compute SHA-1 hash of normalized rule text.
     */
    public static function hash(string $rule): string
    {
        return sha1(self::normalize($rule));
    }
}
