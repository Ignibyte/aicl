<?php

namespace Aicl\Rlm\Exceptions;

/**
 * Programming error exception for RLM services (Tier 2).
 *
 * Thrown when a caller violates a method contract (e.g., missing required field).
 */
class RlmInvalidArgumentException extends \InvalidArgumentException
{
    /**
     * Create an exception for a missing required field.
     */
    public static function missingRequiredField(string $field): self
    {
        return new self("{$field} is required");
    }
}
