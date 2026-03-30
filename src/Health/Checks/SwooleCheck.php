<?php

declare(strict_types=1);

namespace Aicl\Health\Checks;

use Aicl\Health\Contracts\ServiceHealthCheck;
use Aicl\Health\ServiceCheckResult;
use Swoole\Coroutine;
use Throwable;

/**
 * Health check for Swoole/OpenSwoole extension availability and coroutine support.
 *
 * @codeCoverageIgnore External service health check
 */
class SwooleCheck implements ServiceHealthCheck
{
    /**
     * @codeCoverageIgnore Swoole extension is always loaded in CI — branch coverage depends on runtime environment state
     */
    public function check(): ServiceCheckResult
    {
        try {
            if (! extension_loaded('swoole') && ! extension_loaded('openswoole')) {
                return ServiceCheckResult::down(
                    name: 'Swoole',
                    icon: 'heroicon-o-bolt',
                    error: 'Swoole extension is not loaded.',
                );
            }

            $details = [
                'Workers' => (string) config('octane.workers', 'auto'),
                'Octane Driver' => config('octane.server', 'not configured'),
            ];

            // Try to get coroutine stats if running under Swoole
            if (class_exists(Coroutine::class)) {
                try {
                    $stats = Coroutine::stats();
                    $details['Coroutines'] = (string) ($stats['coroutine_num'] ?? 0);
                } catch (Throwable) {
                    // Not running in coroutine context
                }
            }

            $details['Memory'] = $this->formatBytes(memory_get_usage(true));

            // Detect if running under Octane
            $isOctane = isset($_SERVER['LARAVEL_OCTANE']) || app()->bound('octane');

            if (! $isOctane) {
                return ServiceCheckResult::degraded(
                    name: 'Swoole',
                    icon: 'heroicon-o-bolt',
                    details: $details,
                    error: 'Swoole loaded but not running under Octane.',
                );
            }

            return ServiceCheckResult::healthy(
                name: 'Swoole',
                icon: 'heroicon-o-bolt',
                details: $details,
            );
        } catch (Throwable $e) {
            return ServiceCheckResult::down(
                name: 'Swoole',
                icon: 'heroicon-o-bolt',
                error: $e->getMessage(),
            );
        }
    }

    public function order(): int
    {
        return 10;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitCount = count($units);
        $i = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $i < $unitCount - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 1).' '.$units[$i];
    }
}
