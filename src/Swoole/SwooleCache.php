<?php

namespace Aicl\Swoole;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use JsonException;
use Swoole\Table;

final class SwooleCache
{
    /**
     * Registered table definitions.
     *
     * @var array<string, array{rows: int, ttl: int, valueSize: int}>
     */
    private static array $registrations = [];

    /**
     * Warm callbacks keyed by table name.
     *
     * @var array<string, list<Closure>>
     */
    private static array $warmCallbacks = [];

    /**
     * Custom table resolver for testing.
     *
     * @var (Closure(string): (Table|null))|null
     */
    private static ?Closure $resolver = null;

    /**
     * Custom clock function for testing (returns Unix timestamp).
     *
     * @var (Closure(): int)|null
     */
    private static ?Closure $clock = null;

    /**
     * Register a named cache table with its configuration.
     *
     * Must be called at boot time (service provider). Tables are created
     * by Octane before workers start via config/octane.php.
     *
     * @param  string  $name  Table identifier (e.g., 'permissions')
     * @param  int  $rows  Maximum row count (Swoole Table size)
     * @param  int  $ttl  Default TTL in seconds for this table
     * @param  int  $valueSize  Max bytes for the JSON value column
     */
    public static function register(
        string $name,
        int $rows = 1000,
        int $ttl = 60,
        int $valueSize = 10000,
    ): void {
        self::$registrations[$name] = [
            'rows' => $rows,
            'ttl' => $ttl,
            'valueSize' => $valueSize,
        ];
    }

    /**
     * Store a value in a cache table with optional TTL override.
     *
     * @param  string  $table  Registered table name
     * @param  string  $key  Row key
     * @param  mixed  $value  Value (JSON-serializable)
     * @param  int|null  $ttl  TTL in seconds (null = use table default)
     * @return bool True if stored, false if unavailable or table full
     */
    public static function set(string $table, string $key, mixed $value, ?int $ttl = null): bool
    {
        $swooleTable = self::resolveTable($table);

        if ($swooleTable === null) {
            return false;
        }

        $ttl ??= self::$registrations[$table]['ttl'] ?? 60;

        try {
            $json = json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return $swooleTable->set($key, [
            'value' => $json,
            'expires_at' => self::currentTime() + $ttl,
        ]);
    }

    /**
     * Retrieve a value. Returns null if missing, expired, or unavailable.
     *
     * Expired rows are lazily deleted on access.
     */
    public static function get(string $table, string $key): mixed
    {
        $swooleTable = self::resolveTable($table);

        if ($swooleTable === null) {
            return null;
        }

        $row = $swooleTable->get($key);

        if ($row === false) {
            return null;
        }

        // Lazy TTL expiration
        if (($row['expires_at'] ?? 0) > 0 && $row['expires_at'] < self::currentTime()) {
            $swooleTable->del($key);

            return null;
        }

        try {
            return json_decode($row['value'], associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Remove a specific row from a cache table.
     *
     * @return bool True if deleted, false if not found or unavailable
     */
    public static function forget(string $table, string $key): bool
    {
        $swooleTable = self::resolveTable($table);

        if ($swooleTable === null) {
            return false;
        }

        return $swooleTable->del($key);
    }

    /**
     * Clear all rows from a cache table.
     *
     * @return bool True if flushed, false if unavailable
     */
    public static function flush(string $table): bool
    {
        $swooleTable = self::resolveTable($table);

        if ($swooleTable === null) {
            return false;
        }

        // Swoole Table has no native flush — iterate and delete
        $keys = [];
        foreach ($swooleTable as $key => $row) {
            $keys[] = $key;
        }

        foreach ($keys as $key) {
            $swooleTable->del($key);
        }

        return true;
    }

    /**
     * Get the number of rows currently in a cache table.
     *
     * @return int Row count (0 if unavailable)
     */
    public static function count(string $table): int
    {
        $swooleTable = self::resolveTable($table);

        if ($swooleTable === null) {
            return 0;
        }

        return $swooleTable->count();
    }

    /**
     * Bulk populate a table from a data source.
     *
     * The loader returns an associative array of [key => value] pairs.
     * Each value is stored with the table's default TTL.
     */
    public static function warm(string $table, Closure $loader): void
    {
        if (! self::isAvailable()) {
            return;
        }

        $data = $loader();

        foreach ($data as $key => $value) {
            self::set($table, (string) $key, $value);
        }
    }

    /**
     * Register a warm callback to be executed when Swoole workers boot.
     *
     * Callbacks are stored and replayed on each WorkerStarting event.
     */
    public static function registerWarm(string $table, Closure $loader): void
    {
        if (! isset(self::$warmCallbacks[$table])) {
            self::$warmCallbacks[$table] = [];
        }

        self::$warmCallbacks[$table][] = $loader;
    }

    /**
     * Get all registered warm callbacks.
     *
     * @return array<string, list<Closure>>
     */
    public static function warmCallbacks(): array
    {
        return self::$warmCallbacks;
    }

    /**
     * Register event-driven invalidation.
     *
     * When the given event fires, the resolver extracts the cache key(s)
     * to invalidate from the event instance.
     *
     * @param  string  $table  Registered table name
     * @param  string  $event  Fully-qualified event class name
     * @param  Closure  $resolver  Receives event instance, returns string|array<string> of keys to forget
     */
    public static function invalidateOn(string $table, string $event, Closure $resolver): void
    {
        Event::listen($event, function (mixed $eventInstance) use ($table, $resolver): void {
            $keys = Arr::wrap($resolver($eventInstance));

            foreach ($keys as $key) {
                static::forget($table, (string) $key);
            }
        });
    }

    /**
     * Check if SwooleCache is operational.
     *
     * Returns true when a custom resolver is set (test mode) or when
     * running inside an Octane Swoole server with tables available.
     */
    public static function isAvailable(): bool
    {
        if (self::$resolver !== null) {
            return true;
        }

        // Must be in a Swoole worker context — extension loaded alone is insufficient
        // (PHPUnit runs with Swoole extension but no active server)
        if (! extension_loaded('swoole') || ! class_exists(\Laravel\Octane\Facades\Octane::class)) {
            return false;
        }

        // Check if we're actually inside an Octane worker by verifying
        // the WorkerState has been populated with tables
        try {
            $workerState = app(\Laravel\Octane\Swoole\WorkerState::class); // @phpstan-ignore class.notFound
            /** @var object{tables: mixed} $workerState */

            return is_array($workerState->tables) && $workerState->tables !== [];
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get all registered table definitions.
     *
     * Used by the service provider to merge into config/octane.php tables array.
     *
     * @return array<string, array{rows: int, ttl: int, valueSize: int}>
     */
    public static function registrations(): array
    {
        return self::$registrations;
    }

    /**
     * Get the Octane table config array for all registered SwooleCache tables.
     *
     * Returns the format expected by config('octane.tables').
     *
     * @return array<string, array<string, string>>
     */
    public static function octaneTableConfig(): array
    {
        $tables = [];

        foreach (self::$registrations as $name => $def) {
            $key = "{$name}:{$def['rows']}";
            $tables[$key] = [
                'value' => "string:{$def['valueSize']}",
                'expires_at' => 'int',
            ];
        }

        return $tables;
    }

    /**
     * Reset all internal state.
     *
     * Used in tests and during service provider re-registration.
     */
    public static function reset(): void
    {
        self::$registrations = [];
        self::$warmCallbacks = [];
        self::$resolver = null;
        self::$clock = null;
    }

    /**
     * Inject a custom table resolver for testing.
     *
     * When set, resolveTable() uses this resolver instead of Octane::table().
     *
     * @param  (Closure(string): (Table|null))|null  $resolver
     */
    public static function useResolver(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Inject a custom clock function for testing time-dependent behavior.
     *
     * @param  (Closure(): int)|null  $clock  Returns Unix timestamp
     */
    public static function useClock(?Closure $clock): void
    {
        self::$clock = $clock;
    }

    /**
     * Get the current Unix timestamp.
     *
     * Uses the custom clock if set (for testing), otherwise falls back to time().
     */
    private static function currentTime(): int
    {
        if (self::$clock !== null) {
            return (self::$clock)();
        }

        return time();
    }

    /**
     * Resolve the table instance for a given table name.
     *
     * Returns null if the table is not available (Swoole not running,
     * table not registered, or table not created by Octane).
     *
     * @return Table|object|null Returns a Swoole Table, a mock object (testing), or null
     */
    private static function resolveTable(string $table): mixed
    {
        if (! isset(self::$registrations[$table])) {
            throw new InvalidArgumentException(
                "SwooleCache table [{$table}] has not been registered."
            );
        }

        if (self::$resolver !== null) {
            return (self::$resolver)($table);
        }

        if (! self::isAvailable()) {
            return null;
        }

        try {
            return \Laravel\Octane\Facades\Octane::table($table);
        } catch (\Exception) {
            return null;
        }
    }
}
