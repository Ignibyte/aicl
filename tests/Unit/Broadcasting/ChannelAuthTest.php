<?php

namespace Aicl\Tests\Unit\Broadcasting;

use Aicl\Broadcasting\ChannelAuth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── entityChannel() ─────────────────────────────────────────

    public function test_entity_channel_returns_true_for_authorized_user(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $permission = Permission::findOrCreate('ViewAny:User', 'web');
        $user->givePermissionTo($permission);

        $result = ChannelAuth::entityChannel($user, User::class, $target->getKey());

        $this->assertTrue($result);
    }

    public function test_entity_channel_returns_false_for_unauthorized_user(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $result = ChannelAuth::entityChannel($user, User::class, $target->getKey());

        $this->assertFalse($result);
    }

    public function test_entity_channel_returns_false_for_nonexistent_entity(): void
    {
        $user = User::factory()->create();

        $permission = Permission::findOrCreate('ViewAny:User', 'web');
        $user->givePermissionTo($permission);

        $result = ChannelAuth::entityChannel($user, User::class, 999999);

        $this->assertFalse($result);
    }

    // ── userChannel() ───────────────────────────────────────────

    public function test_user_channel_returns_true_for_matching_id(): void
    {
        $user = User::factory()->create();

        $result = ChannelAuth::userChannel($user, $user->getKey());

        $this->assertTrue($result);
    }

    public function test_user_channel_returns_false_for_non_matching_id(): void
    {
        $user = User::factory()->create();

        $result = ChannelAuth::userChannel($user, $user->getKey() + 1000);

        $this->assertFalse($result);
    }

    public function test_user_channel_handles_string_int_coercion(): void
    {
        $user = User::factory()->create();

        $result = ChannelAuth::userChannel($user, (string) $user->getKey());

        $this->assertTrue($result);
    }

    // ── presenceChannel() ───────────────────────────────────────

    public function test_presence_channel_returns_user_data_when_authorized(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe']);
        $target = User::factory()->create();

        $permission = Permission::findOrCreate('ViewAny:User', 'web');
        $user->givePermissionTo($permission);

        $result = ChannelAuth::presenceChannel($user, User::class, $target->getKey());

        $this->assertIsArray($result);
        $this->assertSame($user->getKey(), $result['id']);
        $this->assertSame('Jane Doe', $result['name']);
    }

    public function test_presence_channel_returns_false_when_unauthorized(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $result = ChannelAuth::presenceChannel($user, User::class, $target->getKey());

        $this->assertFalse($result);
    }
}
