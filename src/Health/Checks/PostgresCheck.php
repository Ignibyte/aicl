<?php

namespace Aicl\Health\Checks;

use Aicl\Health\Contracts\ServiceHealthCheck;
use Aicl\Health\ServiceCheckResult;
use Illuminate\Support\Facades\DB;
use Throwable;

class PostgresCheck implements ServiceHealthCheck
{
    public function check(): ServiceCheckResult
    {
        try {
            $connection = config('database.default');
            DB::connection($connection)->getPdo();

            $version = DB::connection($connection)->selectOne('SELECT version()');
            $versionString = $version ? $this->parseVersion($version->version) : 'Unknown';

            $activeConnections = DB::connection($connection)
                ->selectOne('SELECT count(*) as count FROM pg_stat_activity');

            $dbSize = DB::connection($connection)
                ->selectOne('SELECT pg_database_size(current_database()) as size');

            $details = [
                'Version' => $versionString,
                'Active Connections' => (string) ($activeConnections->count ?? 0),
                'Database Size' => $this->formatBytes((int) ($dbSize->size ?? 0)),
                'Connection' => $connection,
            ];

            return ServiceCheckResult::healthy(
                name: 'PostgreSQL',
                icon: 'heroicon-o-circle-stack',
                details: $details,
            );
        } catch (Throwable $e) {
            return ServiceCheckResult::down(
                name: 'PostgreSQL',
                icon: 'heroicon-o-circle-stack',
                error: $e->getMessage(),
            );
        }
    }

    public function order(): int
    {
        return 20;
    }

    protected function parseVersion(string $fullVersion): string
    {
        if (preg_match('/PostgreSQL\s+([\d.]+)/', $fullVersion, $matches)) {
            return $matches[1];
        }

        return $fullVersion;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2).' '.$units[$i];
    }
}
