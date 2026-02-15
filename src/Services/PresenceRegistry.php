<?php

namespace Aicl\Services;

use Aicl\Events\SessionTerminated;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class PresenceRegistry
{
    protected const KEY_PREFIX = 'presence:sessions:';

    /**
     * Store or update a session's presence data in the cache.
     *
     * @param  array<string, mixed>  $meta
     */
    public function touch(string $sessionId, int $userId, array $meta): void
    {
        $data = array_merge($meta, [
            'user_id' => $userId,
            'session_id' => $sessionId,
            'session_id_short' => static::maskSessionId($sessionId),
            'last_seen_at' => now()->toIso8601String(),
        ]);

        $ttlSeconds = (int) config('session.lifetime', 120) * 60 + 300;

        Cache::put(self::KEY_PREFIX.$sessionId, $data, $ttlSeconds);

        $this->addToIndex($sessionId, $ttlSeconds);
    }

    /**
     * Get all active sessions from the registry.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function allSessions(): Collection
    {
        $index = $this->getIndex();

        if (empty($index)) {
            return collect();
        }

        $sessions = collect();

        foreach (array_keys($index) as $sessionId) {
            $data = Cache::get(self::KEY_PREFIX.$sessionId);

            if ($data !== null) {
                $sessions->push($data);
            } else {
                $this->removeFromIndex($sessionId);
            }
        }

        return $sessions->sortByDesc('last_seen_at')->values();
    }

    /**
     * Get sessions for a specific user.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function sessionsForUser(int $userId): Collection
    {
        return $this->allSessions()->where('user_id', $userId)->values();
    }

    /**
     * Remove a session from the registry without destroying the actual session.
     */
    public function forget(string $sessionId): void
    {
        Cache::forget(self::KEY_PREFIX.$sessionId);
        $this->removeFromIndex($sessionId);
    }

    /**
     * Terminate a session: destroy the actual session and remove the registry entry.
     * Dispatches a SessionTerminated DomainEvent for audit logging.
     */
    public function terminateSession(string $sessionId): bool
    {
        $data = Cache::get(self::KEY_PREFIX.$sessionId);

        if ($data === null) {
            return false;
        }

        Session::getHandler()->destroy($sessionId);

        $this->forget($sessionId);

        SessionTerminated::dispatch(
            $sessionId,
            (int) ($data['user_id'] ?? 0),
            (string) ($data['user_name'] ?? 'Unknown'),
        );

        return true;
    }

    /**
     * Mask a session ID for safe display: first 4 + last 4 characters.
     */
    public static function maskSessionId(string $sessionId): string
    {
        $length = strlen($sessionId);

        if ($length <= 8) {
            return $sessionId;
        }

        return substr($sessionId, 0, 4).'…'.substr($sessionId, -4);
    }

    /**
     * Add a session ID to the index set for enumeration.
     */
    protected function addToIndex(string $sessionId, int $ttlSeconds): void
    {
        $index = $this->getIndex();
        $index[$sessionId] = true;

        Cache::put('presence:session_index', $index, $ttlSeconds);
    }

    /**
     * Remove a session ID from the index set.
     */
    protected function removeFromIndex(string $sessionId): void
    {
        $index = $this->getIndex();
        unset($index[$sessionId]);

        if (empty($index)) {
            Cache::forget('presence:session_index');
        } else {
            $ttlSeconds = (int) config('session.lifetime', 120) * 60 + 300;
            Cache::put('presence:session_index', $index, $ttlSeconds);
        }
    }

    /**
     * Get the current session index.
     *
     * @return array<string, bool>
     */
    protected function getIndex(): array
    {
        return Cache::get('presence:session_index', []);
    }
}
