<?php

namespace Aicl\Rlm\Exceptions;

/**
 * Wraps Elasticsearch failures for internal use.
 *
 * Caught within KnowledgeService methods and converted to
 * Tier 3 handling (log warning + return null). Not intended
 * for callers to catch directly.
 */
class SearchUnavailableException extends RlmException {}
