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

        /** @phpstan-ignore-next-line */
        $expected = "presence.testpresencemodels.{$model->getKey()}";

        /** @phpstan-ignore-next-line */
        $this->assertSame($expected, $model->presenceChannelName());
    }

    public function test_presence_channel_name_uses_lowercase_class_basename(): void
    {
        $user = User::factory()->create();
        $model = TestPresenceModel::find($user->getKey());

        /** @phpstan-ignore-next-line */
        $channelName = $model->presenceChannelName();

        $this->assertStringStartsWith('presence.testpresencemodel', $channelName);
        $this->assertStringNotContainsString('TestPresenceModel', $channelName);
    }

    // ── presencePermission() ────────────────────────────────────

    public function test_presence_permission_format(): void
    {
        $user = User::factory()->create();
        $model = TestPresenceModel::find($user->getKey());

        /** @phpstan-ignore-next-line */
        $this->assertSame('ViewAny:TestPresenceModel', $model->presencePermission());
    }

    public function test_presence_permission_uses_class_basename(): void
    {
        $user = User::factory()->create();
        $model = TestPresenceModel::find($user->getKey());

        /** @phpstan-ignore-next-line */
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
