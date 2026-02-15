<?php

namespace Aicl\Tests\Feature\Broadcasting;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PresenceAdminPanelChannelTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);

        Event::fake([
            EntityCreated::class,
            EntityUpdated::class,
            EntityDeleted::class,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        $this->admin->assignRole('admin');

        $this->viewer = User::factory()->create([
            'name' => 'Viewer User',
            'email' => 'viewer@example.com',
        ]);
        $this->viewer->assignRole('viewer');
    }

    public function test_admin_user_returns_user_data(): void
    {
        $this->actingAs($this->admin);

        $channels = app('Illuminate\Broadcasting\BroadcastManager')
            ->getChannels();

        $callback = $channels['presence-admin-panel'];
        $result = $callback($this->admin);

        $this->assertIsArray($result);
        $this->assertEquals($this->admin->id, $result['id']);
        $this->assertEquals('Admin User', $result['name']);
        $this->assertArrayNotHasKey('email', $result);
        $this->assertArrayNotHasKey('ip_address', $result);
        $this->assertArrayHasKey('joined_at', $result);
        $this->assertArrayHasKey('current_url', $result);
    }

    public function test_non_admin_user_returns_false(): void
    {
        $this->actingAs($this->viewer);

        $channels = app('Illuminate\Broadcasting\BroadcastManager')
            ->getChannels();

        $callback = $channels['presence-admin-panel'];
        $result = $callback($this->viewer);

        $this->assertFalse($result);
    }

    public function test_channel_callback_returns_false_for_null_user(): void
    {
        $channels = app('Illuminate\Broadcasting\BroadcastManager')
            ->getChannels();

        $callback = $channels['presence-admin-panel'];
        $result = $callback(null);

        $this->assertFalse($result);
    }

    public function test_channel_is_registered(): void
    {
        $channels = app('Illuminate\Broadcasting\BroadcastManager')
            ->getChannels();

        $this->assertArrayHasKey('presence-admin-panel', $channels);
    }
}
