<?php

namespace Aicl\Tests\Unit\Broadcasting;

use Aicl\Broadcasting\Traits\HasPresenceChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasPresenceChannelTest extends TestCase
{
    use RefreshDatabase;

    // ── presenceChannelName() ───────────────────────────────────

    public function test_presence_channel_name_format(): void
    {
        $user = User::factory()->create();
        $model = TestPresenceModel::find($user->getKey());

        $expected = "presence.testpresencemodels.{$model->getKey()}";

        $this->assertSame($expected, $model->presenceChannelName());
    }

    public function test_presence_channel_name_uses_lowercase_class_basename(): void
    {
        $user = User::factory()->create();
        $model = TestPresenceModel::find($user->getKey());

        $channelName = $model->presenceChannelName();

        $this->assertStringStartsWith('presence.testpresencemodel', $channelName);
        $this->assertStringNotContainsString('TestPresenceModel', $channelName);
    }

    // ── presencePermission() ────────────────────────────────────

    public function test_presence_permission_format(): void
    {
        $user = User::factory()->create();
        $model = TestPresenceModel::find($user->getKey());

        $this->assertSame('ViewAny:TestPresenceModel', $model->presencePermission());
    }

    public function test_presence_permission_uses_class_basename(): void
    {
        $user = User::factory()->create();
        $model = TestPresenceModel::find($user->getKey());

        $permission = $model->presencePermission();

        $this->assertStringNotContainsString('\\', $permission);
        $this->assertStringContainsString('TestPresenceModel', $permission);
    }
}

// ── Test Stubs ────────────────────────────────────────────────────

class TestPresenceModel extends Model
{
    use HasPresenceChannel;

    protected $table = 'users';
}
