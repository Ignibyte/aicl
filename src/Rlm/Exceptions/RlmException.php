<?php

namespace Aicl\Rlm\Exceptions;

/**
 * Base exception for RLM service errors.
 *
 * Used for runtime failures in external service calls (Tier 3)
 * and unexpected runtime errors within RLM services.
 */
class RlmException extends \RuntimeException {}
