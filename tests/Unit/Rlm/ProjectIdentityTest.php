<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\Rlm\ProjectIdentity;
use Tests\TestCase;

class ProjectIdentityTest extends TestCase
{
    private ProjectIdentity $identity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->identity = new ProjectIdentity;
    }

    public function test_hash_returns_sha256_string(): void
    {
        $hash = $this->identity->hash();

        $this->assertIsString($hash);
        $this->assertEquals(64, strlen($hash)); // SHA-256 = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    public function test_hash_is_deterministic(): void
    {
        $hash1 = $this->identity->hash();
        $hash2 = $this->identity->hash();

        $this->assertEquals($hash1, $hash2);
    }

    public function test_hash_changes_when_app_key_changes(): void
    {
        $hash1 = $this->identity->hash();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $hash2 = $this->identity->hash();

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_hash_changes_when_app_name_changes(): void
    {
        $hash1 = $this->identity->hash();

        config(['app.name' => 'DifferentProject_'.uniqid()]);
        $hash2 = $this->identity->hash();

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_anonymize_strips_sensitive_keys(): void
    {
        $data = [
            'entity_name' => 'User',
            'failure_code' => 'BF-001',
            'source_code' => '<?php class Foo {}',
            'api_key' => 'sk-secret-key',
            'password' => 'hunter2',
            'file_path' => '/app/Models/User.php',
        ];

        $result = $this->identity->anonymize($data);

        $this->assertArrayHasKey('entity_name', $result);
        $this->assertArrayHasKey('failure_code', $result);
        $this->assertArrayNotHasKey('source_code', $result);
        $this->assertArrayNotHasKey('api_key', $result);
        $this->assertArrayNotHasKey('password', $result);
        $this->assertArrayNotHasKey('file_path', $result);
    }

    public function test_anonymize_preserves_structural_metadata(): void
    {
        $data = [
            'entity_name' => 'Project',
            'field_types' => ['string', 'integer', 'foreignId'],
            'has_states' => true,
            'pattern_id' => 'migration.timestamps',
            'score' => 95.5,
        ];

        $result = $this->identity->anonymize($data);

        $this->assertEquals($data, $result);
    }

    public function test_anonymize_handles_nested_arrays(): void
    {
        $data = [
            'entity_name' => 'Task',
            'context' => [
                'fields' => ['name', 'status'],
                'credentials' => ['user' => 'admin', 'password' => 'secret'],
            ],
        ];

        $result = $this->identity->anonymize($data);

        $this->assertArrayHasKey('context', $result);
        $this->assertArrayHasKey('fields', $result['context']);
        $this->assertArrayNotHasKey('credentials', $result['context']);
    }

    public function test_anonymize_redacts_absolute_file_paths_in_values(): void
    {
        $data = [
            'description' => 'Missing field',
            'location' => '/Users/dev/Projects/myapp/app/Models/Foo.php',
            'url' => 'https://example.com/api',
        ];

        $result = $this->identity->anonymize($data);

        $this->assertEquals('Missing field', $result['description']);
        $this->assertEquals('[redacted:path]', $result['location']);
        $this->assertEquals('https://example.com/api', $result['url']);
    }

    public function test_is_hub_enabled_returns_false_by_default(): void
    {
        $this->assertFalse($this->identity->isHubEnabled());
    }

    public function test_is_hub_enabled_returns_true_when_configured(): void
    {
        config([
            'aicl.rlm.hub.enabled' => true,
            'aicl.rlm.hub.url' => 'https://hub.example.com',
            'aicl.rlm.hub.token' => 'test-token',
        ]);

        $this->assertTrue($this->identity->isHubEnabled());
    }

    public function test_is_hub_enabled_returns_false_without_url(): void
    {
        config([
            'aicl.rlm.hub.enabled' => true,
            'aicl.rlm.hub.url' => null,
            'aicl.rlm.hub.token' => 'test-token',
        ]);

        $this->assertFalse($this->identity->isHubEnabled());
    }

    public function test_is_hub_enabled_returns_false_without_token(): void
    {
        config([
            'aicl.rlm.hub.enabled' => true,
            'aicl.rlm.hub.url' => 'https://hub.example.com',
            'aicl.rlm.hub.token' => null,
        ]);

        $this->assertFalse($this->identity->isHubEnabled());
    }
}
