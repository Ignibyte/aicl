<?php

namespace Aicl\Health\Checks;

use Aicl\Health\Contracts\ServiceHealthCheck;
use Aicl\Health\ServiceCheckResult;

class ApplicationCheck implements ServiceHealthCheck
{
    public function check(): ServiceCheckResult
    {
        $details = [
            'PHP Version' => PHP_VERSION,
            'Laravel Version' => app()->version(),
            'Octane Driver' => config('octane.server', 'not configured'),
            'Cache Driver' => config('cache.default'),
            'Session Driver' => config('session.driver'),
            'Queue Driver' => config('queue.default'),
            'Environment' => app()->environment(),
            'Debug Mode' => config('app.debug') ? 'Enabled' : 'Disabled',
        ];

        if (app()->environment('production') && config('app.debug')) {
            return ServiceCheckResult::degraded(
                name: 'Application',
                icon: 'heroicon-o-cog-6-tooth',
                details: $details,
                error: 'Debug mode is enabled in production.',
            );
        }

        return ServiceCheckResult::healthy(
            name: 'Application',
            icon: 'heroicon-o-cog-6-tooth',
            details: $details,
        );
    }

    public function order(): int
    {
        return 60;
    }
}
