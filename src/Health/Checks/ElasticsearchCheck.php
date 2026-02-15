<?php

namespace Aicl\Health\Checks;

use Aicl\Health\Contracts\ServiceHealthCheck;
use Aicl\Health\ServiceCheckResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class ElasticsearchCheck implements ServiceHealthCheck
{
    public function check(): ServiceCheckResult
    {
        try {
            $host = $this->getHost();

            if (! $host) {
                return ServiceCheckResult::healthy(
                    name: 'Elasticsearch',
                    icon: 'heroicon-o-magnifying-glass',
                    details: ['Status' => 'Not configured'],
                );
            }

            $healthResponse = Http::timeout(2)->get("{$host}/_cluster/health");

            if (! $healthResponse->successful()) {
                return ServiceCheckResult::down(
                    name: 'Elasticsearch',
                    icon: 'heroicon-o-magnifying-glass',
                    error: "Cluster health returned {$healthResponse->status()}.",
                );
            }

            $health = $healthResponse->json();
            $clusterStatus = $health['status'] ?? 'unknown';

            // Get index stats
            $indexCount = 0;
            $docCount = 0;

            try {
                $indicesResponse = Http::timeout(2)->get("{$host}/_cat/indices", ['format' => 'json']);

                if ($indicesResponse->successful()) {
                    $indices = $indicesResponse->json();
                    $indexCount = count($indices);
                    $docCount = array_sum(array_column($indices, 'docs.count'));
                }
            } catch (Throwable) {
                // Non-critical — proceed with cluster health data
            }

            $details = [
                'Cluster Status' => ucfirst($clusterStatus),
                'Cluster Name' => $health['cluster_name'] ?? 'Unknown',
                'Nodes' => (string) ($health['number_of_nodes'] ?? 0),
                'Indices' => (string) $indexCount,
                'Documents' => number_format($docCount),
            ];

            return match ($clusterStatus) {
                'green' => ServiceCheckResult::healthy(
                    name: 'Elasticsearch',
                    icon: 'heroicon-o-magnifying-glass',
                    details: $details,
                ),
                'yellow' => ServiceCheckResult::degraded(
                    name: 'Elasticsearch',
                    icon: 'heroicon-o-magnifying-glass',
                    details: $details,
                    error: 'Cluster status is yellow — replicas may be unassigned.',
                ),
                default => ServiceCheckResult::down(
                    name: 'Elasticsearch',
                    icon: 'heroicon-o-magnifying-glass',
                    error: "Cluster status is {$clusterStatus}.",
                ),
            };
        } catch (Throwable $e) {
            return ServiceCheckResult::down(
                name: 'Elasticsearch',
                icon: 'heroicon-o-magnifying-glass',
                error: $e->getMessage(),
            );
        }
    }

    public function order(): int
    {
        return 40;
    }

    protected function getHost(): ?string
    {
        // Check config used by matchish/laravel-scout-elasticsearch
        $host = config('elasticsearch.host');

        if ($host) {
            return rtrim($host, '/');
        }

        // Fallback: build from aicl search config
        $esHost = config('aicl.search.elasticsearch.host');

        if (! $esHost) {
            return null;
        }

        $scheme = config('aicl.search.elasticsearch.scheme', 'http');
        $port = config('aicl.search.elasticsearch.port', 9200);

        return "{$scheme}://{$esHost}:{$port}";
    }
}
