<?php

namespace Aicl\Health\Checks;

use Aicl\Health\Contracts\ServiceHealthCheck;
use Aicl\Health\ServiceCheckResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class ReverbCheck implements ServiceHealthCheck
{
    public function check(): ServiceCheckResult
    {
        try {
            if (! config('aicl.features.websockets', true)) {
                return ServiceCheckResult::healthy(
                    name: 'Reverb',
                    icon: 'heroicon-o-signal',
                    details: ['Status' => 'Disabled'],
                );
            }

            $host = $this->getHost();
            $port = $this->getPort();
            $scheme = $this->getScheme();

            // Reverb exposes an HTTP health endpoint on the same port
            $url = "{$scheme}://{$host}:{$port}";

            // Try connecting to Reverb's HTTP endpoint
            $response = Http::timeout(2)
                ->withOptions(['verify' => false])
                ->get($url);

            // Reverb returns various status codes — a response means it's running.
            // 200 = health endpoint, 401/426 = WebSocket upgrade expected (still alive)
            $statusCode = $response->status();

            $details = [
                'Host' => "{$host}:{$port}",
                'Scheme' => $scheme,
            ];

            // Check if the supervisor process is running
            $supervisorRunning = $this->isSupervisorProcessRunning();
            $details['Process'] = $supervisorRunning ? 'Running' : 'Unknown';

            if ($statusCode >= 200 && $statusCode < 500) {
                return ServiceCheckResult::healthy(
                    name: 'Reverb',
                    icon: 'heroicon-o-signal',
                    details: $details,
                );
            }

            return ServiceCheckResult::degraded(
                name: 'Reverb',
                icon: 'heroicon-o-signal',
                details: $details,
                error: "Reverb responded with HTTP {$statusCode}.",
            );
        } catch (Throwable $e) {
            // Connection refused/timeout means Reverb is not running
            $message = $e->getMessage();

            // Check if it's just not reachable vs actual error
            if (str_contains($message, 'Connection refused') || str_contains($message, 'timed out')) {
                return ServiceCheckResult::down(
                    name: 'Reverb',
                    icon: 'heroicon-o-signal',
                    error: 'Reverb is not reachable. Ensure the WebSocket server is running.',
                );
            }

            return ServiceCheckResult::down(
                name: 'Reverb',
                icon: 'heroicon-o-signal',
                error: $message,
            );
        }
    }

    public function order(): int
    {
        return 35;
    }

    protected function getHost(): string
    {
        return config('reverb.servers.reverb.host', '0.0.0.0') === '0.0.0.0'
            ? '127.0.0.1'
            : config('reverb.servers.reverb.host', '127.0.0.1');
    }

    protected function getPort(): int
    {
        return (int) config('reverb.servers.reverb.port', 8080);
    }

    protected function getScheme(): string
    {
        $tls = config('reverb.servers.reverb.options.tls', []);

        return ! empty($tls) ? 'https' : 'http';
    }

    protected function isSupervisorProcessRunning(): bool
    {
        try {
            $output = shell_exec('ps aux 2>/dev/null | grep -c "[r]everb"');

            return ((int) trim((string) $output)) > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
