<?php

namespace Aicl\Rlm;

class ProjectIdentity
{
    /**
     * Sensitive field keys that should be stripped during anonymization.
     *
     * @var array<string>
     */
    protected static array $sensitiveKeys = [
        'source_code',
        'file_contents',
        'file_path',
        'absolute_path',
        'directory',
        'app_key',
        'api_key',
        'token',
        'secret',
        'password',
        'credentials',
        'connection_string',
        'database_url',
    ];

    /**
     * Generate a deterministic, anonymous project hash.
     *
     * Uses app name + app key to produce a unique identifier
     * that does not reveal the project's identity.
     */
    public function hash(): string
    {
        return hash('sha256', config('app.name').config('app.key'));
    }

    /**
     * Anonymize data for hub transmission.
     *
     * Strips sensitive fields (file paths, source code, credentials)
     * while preserving structural metadata (field types, entity names,
     * pattern IDs, scores).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function anonymize(array $data): array
    {
        return $this->scrub($data);
    }

    /**
     * Check whether the hub is enabled and configured.
     */
    public function isHubEnabled(): bool
    {
        return (bool) config('aicl.rlm.hub.enabled', false)
            && ! empty(config('aicl.rlm.hub.url'))
            && ! empty(config('aicl.rlm.hub.token'));
    }

    /**
     * Get the configured hub URL.
     */
    public function hubUrl(): ?string
    {
        return config('aicl.rlm.hub.url');
    }

    /**
     * Get the configured hub API token.
     */
    public function hubToken(): ?string
    {
        return config('aicl.rlm.hub.token');
    }

    /**
     * Recursively scrub sensitive fields from an array.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function scrub(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->scrub($value);
            } elseif (is_string($value) && $this->containsFilePath($value)) {
                $result[$key] = '[redacted:path]';
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Check if a key name indicates sensitive data.
     */
    protected function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);

        foreach (static::$sensitiveKeys as $sensitive) {
            if ($lower === $sensitive || str_contains($lower, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a string value looks like a file path.
     */
    protected function containsFilePath(string $value): bool
    {
        // Match absolute Unix paths or paths with directory separators
        // that look like file system paths (not URL paths)
        if (preg_match('#^/(?:home|var|tmp|usr|etc|app|Users|opt)/[^\s]+#', $value)) {
            return true;
        }

        // Match Windows-style paths
        if (preg_match('#^[A-Z]:\\\\#i', $value)) {
            return true;
        }

        return false;
    }
}
