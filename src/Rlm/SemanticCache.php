<?php

namespace Aicl\Rlm;

use Aicl\Models\RlmSemanticCache;

class SemanticCache
{
    /**
     * Generate a cache key from check name + file contents.
     *
     * @param  array<string, string>  $fileContents  target => content
     */
    public function cacheKey(SemanticCheck $check, array $fileContents): string
    {
        $data = $check->name;
        ksort($fileContents);

        foreach ($fileContents as $target => $content) {
            $data .= "|{$target}:".sha1($content);
        }

        return hash('sha256', $data);
    }

    /**
     * Get a cached result if it exists and hasn't expired.
     */
    public function get(string $key): ?SemanticResult
    {
        try {
            $row = RlmSemanticCache::query()
                ->where('cache_key', $key)
                ->where('expires_at', '>', now())
                ->first();

            if (! $row) {
                return null;
            }

            $check = $this->findCheck($row->check_name);
            if (! $check) {
                return null;
            }

            return new SemanticResult(
                check: $check,
                passed: $row->passed,
                message: $row->message,
                confidence: (float) $row->confidence,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Store a result in the cache.
     */
    public function put(string $key, SemanticResult $result, string $entityName = ''): void
    {
        if ($result->skipped) {
            return;
        }

        try {
            $ttl = (int) config('aicl.rlm.semantic.cache_ttl', 604800);

            RlmSemanticCache::query()->updateOrCreate(
                ['cache_key' => $key],
                [
                    'check_name' => $result->check->name,
                    'entity_name' => $entityName,
                    'passed' => $result->passed,
                    'message' => $result->message,
                    'confidence' => $result->confidence,
                    'files_hash' => $key,
                    'expires_at' => now()->addSeconds($ttl),
                ],
            );
        } catch (\Throwable) {
            // Cache write failure is not critical
        }
    }

    /**
     * Remove all expired cache entries.
     */
    public function prune(): int
    {
        try {
            return RlmSemanticCache::query()
                ->where('expires_at', '<=', now())
                ->delete();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Clear all cache entries for a specific entity.
     */
    public function clearForEntity(string $entityName): int
    {
        try {
            return RlmSemanticCache::query()
                ->where('entity_name', $entityName)
                ->delete();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function findCheck(string $name): ?SemanticCheck
    {
        foreach (SemanticCheckRegistry::all() as $check) {
            if ($check->name === $name) {
                return $check;
            }
        }

        return null;
    }
}
