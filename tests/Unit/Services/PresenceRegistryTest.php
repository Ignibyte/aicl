<?php

namespace Aicl\Tests\Unit\Services;

use Aicl\Events\SessionTerminated;
use Aicl\Services\PresenceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PresenceRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected PresenceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(PresenceRegistry::class);

        // Clear any leftover presence data
        Cache::forget('presence:session_index');
    }

    // ── maskSessionId() ─────────────────────────────────────

    public function test_mask_session_id_shows_first_and_last_four(): void
    {
        $result = PresenceRegistry::maskSessionId('abcdefghijklmnop');

        $this->assertSame('abcd…mnop', $result);
    }

    public function test_mask_session_id_returns_full_id_when_short(): void
    {
        $result = PresenceRegistry::maskSessionId('abcd1234');

        $this->assertSame('abcd1234', $result);
    }

    public function test_mask_session_id_returns_full_id_when_very_short(): void
    {
        $result = PresenceRegistry::maskSessionId('abc');

        $this->assertSame('abc', $result);
    }

    public function test_mask_session_id_with_typical_session_length(): void
    {
        $sessionId = 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0';

        $result = PresenceRegistry::maskSessionId($sessionId);

        $this->assertSame('a1b2…s9t0', $result);
    }

    // ── touch() ─────────────────────────────────────────────

    public function test_touch_stores_session_data_in_cache(): void
    {
        $this->registry->touch('sess-001', 1, [
            'user_name' => 'John Doe',
            'user_email' => 'john@example.com',
            'current_url' => 'https://app.test/admin/dashboard',
            'ip_address' => '127.0.0.1',
        ]);

        $data = Cache::get('presence:sessions:sess-001');

        $this->assertNotNull($data);
        $this->assertSame(1, $data['user_id']);
        $this->assertSame('sess-001', $data['session_id']);
        $this->assertSame('John Doe', $data['user_name']);
        $this->assertSame('john@example.com', $data['user_email']);
        $this->assertSame('https://app.test/admin/dashboard', $data['current_url']);
        $this->assertSame('127.0.0.1', $data['ip_address']);
        $this->assertArrayHasKey('last_seen_at', $data);
        $this->assertArrayHasKey('session_id_short', $data);
    }

    public function test_touch_includes_masked_session_id(): void
    {
        $this->registry->touch('abcdefghijklmnop', 1, [
            'user_name' => 'Test',
            'user_email' => 'test@example.com',
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        $data = Cache::get('presence:sessions:abcdefghijklmnop');

        $this->assertSame('abcd…mnop', $data['session_id_short']);
    }

    public function test_touch_updates_existing_session(): void
    {
        $this->registry->touch('sess-001', 1, [
            'user_name' => 'John',
            'user_email' => 'john@example.com',
            'current_url' => '/admin/dashboard',
            'ip_address' => '127.0.0.1',
        ]);

        $this->registry->touch('sess-001', 1, [
            'user_name' => 'John',
            'user_email' => 'john@example.com',
            'current_url' => '/admin/users',
            'ip_address' => '127.0.0.1',
        ]);

        $data = Cache::get('presence:sessions:sess-001');
        $this->assertSame('/admin/users', $data['current_url']);

        $this->assertCount(1, $this->registry->allSessions());
    }

    public function test_touch_adds_session_to_index(): void
    {
        $this->registry->touch('sess-001', 1, [
            'user_name' => 'John',
            'user_email' => 'john@example.com',
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        $index = Cache::get('presence:session_index');

        $this->assertIsArray($index);
        $this->assertArrayHasKey('sess-001', $index);
    }

    // ── allSessions() ───────────────────────────────────────

    public function test_all_sessions_returns_empty_collection_when_no_sessions(): void
    {
        $sessions = $this->registry->allSessions();

        $this->assertTrue($sessions->isEmpty());
    }

    public function test_all_sessions_returns_all_tracked_sessions(): void
    {
        $this->registry->touch('sess-001', 1, [
            'user_name' => 'John',
            'user_email' => 'john@example.com',
            'current_url' => '/admin/dashboard',
            'ip_address' => '127.0.0.1',
        ]);

        $this->registry->touch('sess-002', 2, [
            'user_name' => 'Jane',
            'user_email' => 'jane@example.com',
            'current_url' => '/admin/users',
            'ip_address' => '10.0.0.1',
        ]);

        $sessions = $this->registry->allSessions();

        $this->assertCount(2, $sessions);
    }

    public function test_all_sessions_sorted_by_last_seen_descending(): void
    {
        // Touch first, then second — second is more recent
        $this->registry->touch('sess-older', 1, [
            'user_name' => 'Older',
            'user_email' => 'older@example.com',
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        // Advance time slightly so last_seen_at differs
        $this->travel(1)->seconds();

        $this->registry->touch('sess-newer', 2, [
            'user_name' => 'Newer',
            'user_email' => 'newer@example.com',
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        $sessions = $this->registry->allSessions();

        /** @phpstan-ignore-next-line */
        $this->assertSame('Newer', $sessions->first()['user_name']);
        /** @phpstan-ignore-next-line */
        $this->assertSame('Older', $sessions->last()['user_name']);
    }

    public function test_all_sessions_cleans_stale_index_entries(): void
    {
        $this->registry->touch('sess-001', 1, [
            'user_name' => 'Test',
            'user_email' => 'test@example.com',
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        // Manually remove the cache entry but leave the index
        Cache::forget('presence:sessions:sess-001');

        $sessions = $this->registry->allSessions();

        $this->assertTrue($sessions->isEmpty());

        // Index should be cleaned up
        $index = Cache::get('presence:session_index', []);
        $this->assertArrayNotHasKey('sess-001', $index);
    }

    // ── sessionsForUser() ───────────────────────────────────

    public function test_sessions_for_user_filters_by_user_id(): void
    {
        $this->registry->touch('sess-001', 1, [
            'user_name' => 'John',
            'user_email' => 'john@example.com',
            'current_url' => '/admin/dashboard',
            'ip_address' => '127.0.0.1',
        ]);

        $this->registry->touch('sess-002', 1, [
            'user_name' => 'John',
            'user_email' => 'john@example.com',
            'current_url' => '/admin/users',
            'ip_address' => '10.0.0.1',
        ]);

        $this->registry->touch('sess-003', 2, [
            'user_name' => 'Jane',
            'user_email' => 'jane@example.com',
            'current_url' => '/admin/settings',
            'ip_address' => '10.0.0.2',
        ]);

        $userSessions = $this->registry->sessionsForUser(1);

        $this->assertCount(2, $userSessions);
        $this->assertTrue($userSessions->every(fn (array $s): bool => $s['user_id'] === 1));
    }

    public function test_sessions_for_user_returns_empty_when_no_match(): void
    {
        $this->registry->touch('sess-001', 1, [
            'user_name' => 'John',
            'user_email' => 'john@example.com',
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        $sessions = $this->registry->sessionsForUser(999);

        $this->assertTrue($sessions->isEmpty());
    }

    // ── forget() ────────────────────────────────────────────

    public function test_forget_removes_session_from_cache_and_index(): void
    {
        $this->registry->touch('sess-001', 1, [
            'user_name' => 'Test',
            'user_email' => 'test@example.com',
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        $this->registry->forget('sess-001');

        $this->assertNull(Cache::get('presence:sessions:sess-001'));
        $this->assertTrue($this->registry->allSessions()->isEmpty());
    }

    public function test_forget_removes_index_when_last_session(): void
    {
        $this->registry->touch('sess-001', 1, [
            'user_name' => 'Test',
            'user_email' => 'test@example.com',
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        $this->registry->forget('sess-001');

        $this->assertNull(Cache::get('presence:session_index'));
    }

    public function test_forget_does_not_affect_other_sessions(): void
    {
        $this->registry->touch('sess-001', 1, [
            'user_name' => 'John',
            'user_email' => 'john@example.com',
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        $this->registry->touch('sess-002', 2, [
            'user_name' => 'Jane',
            'user_email' => 'jane@example.com',
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        $this->registry->forget('sess-001');

        $this->assertCount(1, $this->registry->allSessions());
        $this->assertNotNull(Cache::get('presence:sessions:sess-002'));
    }

    // ── terminateSession() ──────────────────────────────────

    public function test_terminate_session_destroys_session_and_removes_registry(): void
    {
        Event::fake([SessionTerminated::class]);

        $this->registry->touch('sess-001', 1, [
            'user_name' => 'John',
            'user_email' => 'john@example.com',
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        $result = $this->registry->terminateSession('sess-001');

        $this->assertTrue($result);
        $this->assertNull(Cache::get('presence:sessions:sess-001'));
        $this->assertTrue($this->registry->allSessions()->isEmpty());
    }

    public function test_terminate_session_dispatches_domain_event(): void
    {
        Event::fake([SessionTerminated::class]);

        $this->registry->touch('sess-001', 42, [
            'user_name' => 'John Doe',
            'user_email' => 'john@example.com',
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        $this->registry->terminateSession('sess-001');

        Event::assertDispatched(SessionTerminated::class, function (SessionTerminated $event): bool {
            return $event->terminatedSessionId === 'sess-001'
                && $event->terminatedUserId === 42
                && $event->terminatedUserName === 'John Doe';
        });
    }

    public function test_terminate_session_returns_false_for_unknown_session(): void
    {
        Event::fake([SessionTerminated::class]);

        $result = $this->registry->terminateSession('nonexistent');

        $this->assertFalse($result);
        Event::assertNotDispatched(SessionTerminated::class);
    }

    // ── Singleton binding ───────────────────────────────────

    public function test_presence_registry_is_bound_as_singleton(): void
    {
        $instance1 = app(PresenceRegistry::class);
        $instance2 = app(PresenceRegistry::class);

        $this->assertSame($instance1, $instance2);
    }
}
