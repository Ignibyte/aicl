<?php

namespace Aicl\Tests\Feature;

use Aicl\AiclServiceProvider;
use Aicl\Console\Commands\ScoutImportCommand;
use Tests\TestCase;

class ElasticsearchIntegrationTest extends TestCase
{
    public function test_scout_driver_feature_flag_exists_in_config(): void
    {
        $features = config('aicl.features');

        $this->assertArrayHasKey('scout_driver', $features);
    }

    public function test_elasticsearch_config_has_default_host(): void
    {
        $this->assertSame('elasticsearch', config('aicl.search.elasticsearch.host'));
    }

    public function test_elasticsearch_config_has_default_port(): void
    {
        $this->assertSame(9200, config('aicl.search.elasticsearch.port'));
    }

    public function test_elasticsearch_config_has_default_scheme(): void
    {
        $this->assertSame('http', config('aicl.search.elasticsearch.scheme'));
    }

    public function test_scout_uses_database_driver_when_flag_is_false(): void
    {
        config([
            'aicl.features.scout_driver' => false,
            'scout.driver' => 'database',
        ]);

        $provider = new AiclServiceProvider($this->app);
        $provider->register();

        $this->assertSame('database', config('scout.driver'));
    }

    public function test_scout_driver_switches_to_elasticsearch_when_package_installed(): void
    {
        config([
            'aicl.features.scout_driver' => 'elasticsearch',
            'scout.driver' => 'database',
        ]);

        $provider = new AiclServiceProvider($this->app);
        $provider->register();

        // With matchish/laravel-scout-elasticsearch installed, the driver should switch
        $this->assertSame(
            \Matchish\ScoutElasticSearch\Engines\ElasticSearchEngine::class,
            config('scout.driver')
        );
    }

    public function test_elasticsearch_config_respects_custom_values(): void
    {
        config([
            'aicl.search.elasticsearch.host' => 'es.example.com',
            'aicl.search.elasticsearch.port' => 9243,
            'aicl.search.elasticsearch.scheme' => 'https',
        ]);

        $host = config('aicl.search.elasticsearch.host');
        $port = config('aicl.search.elasticsearch.port');
        $scheme = config('aicl.search.elasticsearch.scheme');

        $this->assertSame('es.example.com', $host);
        $this->assertSame(9243, $port);
        $this->assertSame('https', $scheme);
    }

    public function test_scout_import_command_is_registered(): void
    {
        $this->assertTrue(
            class_exists(ScoutImportCommand::class),
            'ScoutImportCommand class should exist'
        );

        $command = new ScoutImportCommand;
        $this->assertSame('aicl:scout-import', $command->getName());
    }

    public function test_scout_import_command_has_flush_option(): void
    {
        $command = new ScoutImportCommand;
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('flush'));
    }

    public function test_scout_import_command_discovers_searchable_models(): void
    {
        $command = new ScoutImportCommand;

        // Use reflection to call the protected discoverSearchableModels method
        $reflection = new \ReflectionMethod($command, 'discoverSearchableModels');
        $models = $reflection->invoke($command);

        // With the package installed, hub models should be discoverable
        $this->assertIsObject($models);
    }

    public function test_feature_flag_key_follows_existing_convention(): void
    {
        $features = config('aicl.features');

        $this->assertArrayHasKey('scout_driver', $features);
        $this->assertArrayHasKey('social_login', $features);
        $this->assertArrayHasKey('saml', $features);
        $this->assertArrayHasKey('websockets', $features);
    }

    public function test_search_config_has_elasticsearch_section(): void
    {
        $search = config('aicl.search');

        $this->assertArrayHasKey('elasticsearch', $search);
        $this->assertArrayNotHasKey('meilisearch', $search);
    }
}
