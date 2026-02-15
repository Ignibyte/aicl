<?php

namespace Aicl\Health\Contracts;

use Aicl\Health\ServiceCheckResult;

interface ServiceHealthCheck
{
    /**
     * Run the health check and return a result.
     * Implementations MUST catch their own exceptions and return
     * a down/degraded result — never throw.
     */
    public function check(): ServiceCheckResult;

    /**
     * Display order priority. Lower = higher in the list.
     */
    public function order(): int;
}
