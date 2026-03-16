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
    /**
     * Resolve the per-page count from the request, clamped to safe bounds.
     *
     * @param  Request  $request  The current HTTP request
     * @param  int  $default  Default items per page when not specified
     * @param  int  $max  Maximum allowed items per page (OWASP API4 protection)
     * @return int Clamped per-page value between 1 and $max
     */
    protected function getPerPage(Request $request, int $default = 15, int $max = 100): int
    {
        return max(1, min($request->integer('per_page', $default), $max));
    }
}
