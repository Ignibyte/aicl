<?php

namespace Aicl\Tests\Unit\Health;

use Aicl\Health\Checks\ElasticsearchCheck;
use Aicl\Health\ServiceStatus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ElasticsearchCheckTest extends TestCase
{
    private ElasticsearchCheck $check;

    protected function setUp(): void
    {
        parent::setUp();

        $this->check = new ElasticsearchCheck;
    }

    // ── Green (Healthy) ──────────────────────────────────────

    public function test_returns_healthy_when_cluster_green(): void
    {
        config(['elasticsearch.host' => 'http://localhost:9200']);

        Http::fake([
            'localhost:9200/_cluster/health' => Http::response([
                'status' => 'green',
                'cluster_name' => 'test-cluster',
                'number_of_nodes' => 1,
            ]),
            'localhost:9200/_cat/indices*' => Http::response([
                ['index' => 'idx1', 'docs.count' => '100'],
                ['index' => 'idx2', 'docs.count' => '200'],
            ]),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
        $this->assertSame('Elasticsearch', $result->name);
        $this->assertSame('heroicon-o-magnifying-glass', $result->icon);
        $this->assertNull($result->error);
    }

    public function test_healthy_includes_cluster_details(): void
    {
        config(['elasticsearch.host' => 'http://localhost:9200']);

        Http::fake([
            'localhost:9200/_cluster/health' => Http::response([
                'status' => 'green',
                'cluster_name' => 'my-cluster',
                'number_of_nodes' => 3,
            ]),
            'localhost:9200/_cat/indices*' => Http::response([
                ['index' => 'idx1', 'docs.count' => '500'],
            ]),
        ]);

        $result = $this->check->check();

        $this->assertSame('Green', $result->details['Cluster Status']);
        $this->assertSame('my-cluster', $result->details['Cluster Name']);
        $this->assertSame('3', $result->details['Nodes']);
        $this->assertSame('1', $result->details['Indices']);
        $this->assertSame('500', $result->details['Documents']);
    }

    // ── Yellow (Degraded) ────────────────────────────────────

    public function test_returns_degraded_when_cluster_yellow(): void
    {
        config(['elasticsearch.host' => 'http://localhost:9200']);

        Http::fake([
            'localhost:9200/_cluster/health' => Http::response([
                'status' => 'yellow',
                'cluster_name' => 'test-cluster',
                'number_of_nodes' => 1,
            ]),
            'localhost:9200/_cat/indices*' => Http::response([]),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Degraded, $result->status);
        $this->assertStringContainsString('yellow', $result->error);
        $this->assertStringContainsString('replicas', $result->error);
    }

    // ── Red (Down) ───────────────────────────────────────────

    public function test_returns_down_when_cluster_red(): void
    {
        config(['elasticsearch.host' => 'http://localhost:9200']);

        Http::fake([
            'localhost:9200/_cluster/health' => Http::response([
                'status' => 'red',
                'cluster_name' => 'test-cluster',
                'number_of_nodes' => 1,
            ]),
            'localhost:9200/_cat/indices*' => Http::response([]),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Down, $result->status);
        $this->assertStringContainsString('red', $result->error);
    }

    // ── Not configured ───────────────────────────────────────

    public function test_returns_healthy_not_configured_when_no_host(): void
    {
        config([
            'elasticsearch.host' => null,
            'aicl.search.elasticsearch.host' => null,
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
        $this->assertSame('Not configured', $result->details['Status']);
    }

    // ── HTTP Failure ─────────────────────────────────────────

    public function test_returns_down_when_http_request_fails(): void
    {
        config(['elasticsearch.host' => 'http://localhost:9200']);

        Http::fake([
            'localhost:9200/_cluster/health' => Http::response(null, 500),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Down, $result->status);
        $this->assertStringContainsString('500', $result->error);
    }

    public function test_returns_down_when_connection_exception(): void
    {
        config(['elasticsearch.host' => 'http://localhost:9200']);

        Http::fake([
            'localhost:9200/_cluster/health' => fn () => throw new \RuntimeException('Connection timed out'),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Down, $result->status);
        $this->assertSame('Connection timed out', $result->error);
    }

    // ── Fallback host config ─────────────────────────────────

    public function test_uses_aicl_search_config_as_fallback(): void
    {
        config([
            'elasticsearch.host' => null,
            'aicl.search.elasticsearch.host' => 'es-host',
            'aicl.search.elasticsearch.scheme' => 'https',
            'aicl.search.elasticsearch.port' => 9243,
        ]);

        Http::fake([
            'es-host:9243/_cluster/health' => Http::response([
                'status' => 'green',
                'cluster_name' => 'fallback-cluster',
                'number_of_nodes' => 1,
            ]),
            'es-host:9243/_cat/indices*' => Http::response([]),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
    }

    public function test_strips_trailing_slash_from_host(): void
    {
        config(['elasticsearch.host' => 'http://localhost:9200/']);

        Http::fake([
            'localhost:9200/_cluster/health' => Http::response([
                'status' => 'green',
                'cluster_name' => 'test',
                'number_of_nodes' => 1,
            ]),
            'localhost:9200/_cat/indices*' => Http::response([]),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
    }

    // ── Indices stats failure is non-critical ────────────────

    public function test_handles_indices_stats_failure_gracefully(): void
    {
        config(['elasticsearch.host' => 'http://localhost:9200']);

        Http::fake([
            'localhost:9200/_cluster/health' => Http::response([
                'status' => 'green',
                'cluster_name' => 'test-cluster',
                'number_of_nodes' => 1,
            ]),
            'localhost:9200/_cat/indices*' => fn () => throw new \RuntimeException('Forbidden'),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
        $this->assertSame('0', $result->details['Indices']);
        $this->assertSame('0', $result->details['Documents']);
    }

    // ── order() ──────────────────────────────────────────────

    public function test_order_returns_40(): void
    {
        $this->assertSame(40, $this->check->order());
    }

    // ── getHost() ────────────────────────────────────────────

    public function test_get_host_returns_null_when_nothing_configured(): void
    {
        config([
            'elasticsearch.host' => null,
            'aicl.search.elasticsearch.host' => null,
        ]);

        $method = new \ReflectionMethod(ElasticsearchCheck::class, 'getHost');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($this->check));
    }

    public function test_get_host_prefers_elasticsearch_host_config(): void
    {
        config([
            'elasticsearch.host' => 'http://primary:9200',
            'aicl.search.elasticsearch.host' => 'fallback',
        ]);

        $method = new \ReflectionMethod(ElasticsearchCheck::class, 'getHost');
        $method->setAccessible(true);

        $this->assertSame('http://primary:9200', $method->invoke($this->check));
    }

    public function test_get_host_builds_from_aicl_config(): void
    {
        config([
            'elasticsearch.host' => null,
            'aicl.search.elasticsearch.host' => 'my-es',
            'aicl.search.elasticsearch.scheme' => 'https',
            'aicl.search.elasticsearch.port' => 9243,
        ]);

        $method = new \ReflectionMethod(ElasticsearchCheck::class, 'getHost');
        $method->setAccessible(true);

        $this->assertSame('https://my-es:9243', $method->invoke($this->check));
    }

    public function test_get_host_uses_default_scheme_and_port(): void
    {
        config(['elasticsearch.host' => null]);

        // Remove the scheme and port keys so the defaults kick in
        $config = app('config');
        $aicl = $config->get('aicl', []);
        $aicl['search']['elasticsearch']['host'] = 'my-es';
        unset($aicl['search']['elasticsearch']['scheme']);
        unset($aicl['search']['elasticsearch']['port']);
        $config->set('aicl', $aicl);

        $method = new \ReflectionMethod(ElasticsearchCheck::class, 'getHost');
        $method->setAccessible(true);

        $this->assertSame('http://my-es:9200', $method->invoke($this->check));
    }
}
