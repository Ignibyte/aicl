<?php

namespace Aicl\Rlm\Exceptions;

/**
 * Wraps embedding driver failures for internal use.
 *
 * Caught within EmbeddingService methods and converted to
 * Tier 3 handling (log warning + return null). Not intended
 * for callers to catch directly.
 */
class EmbeddingException extends RlmException {}
