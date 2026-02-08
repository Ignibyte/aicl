<?php

namespace Aicl\Traits;

use Illuminate\Http\Request;

/**
 * Enforces a maximum per_page limit on paginated API responses.
 *
 * Prevents resource exhaustion by capping the number of records
 * a client can request per page (OWASP API4).
 */
trait PaginatesApiRequests
{
    protected function getPerPage(Request $request, int $default = 15, int $max = 100): int
    {
        return max(1, min($request->integer('per_page', $default), $max));
    }
}
