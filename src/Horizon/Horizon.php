<?php

declare(strict_types=1);

namespace Aicl\Horizon;

use Closure;
use Exception;
use Illuminate\Http\Request;

/**
 * Horizon.
 */
class Horizon
{
    /**
     * The callback that should be used to authenticate Horizon users.
     *
     * @var Closure
     */
    public static $authUsing;

    /**
     * The database configuration methods.
     *
     * @var array<int, string>
     */
    public static $databases = [
        'Jobs', 'Supervisors', 'CommandQueue', 'Tags',
        'Metrics', 'Locks', 'Processes',
    ];

    /**
     * Determine if the given request can access the Horizon dashboard.
     *
     * @param Request $request
     *
     * @return bool
     */
    public static function check($request)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return (static::$authUsing ?: function () {
            return app()->environment('local');
        })($request);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Set the callback that should be used to authenticate Horizon users.
     *
     * @return static
     */
    public static function auth(Closure $callback)
    {
        static::$authUsing = $callback;

        return new static;
    }

    /**
     * Configure the Redis databases that will store Horizon data.
     *
     * @param string $connection
     *
     * @throws Exception
     */
    public static function use($connection)
    {
        if (! is_null($config = config("database.redis.clusters.{$connection}.0"))) {
            // @codeCoverageIgnoreStart — Horizon process management
            config(["database.redis.{$connection}" => $config]);
        } elseif (is_null($config) && is_null($config = config("database.redis.{$connection}"))) {
            throw new Exception("Redis connection [{$connection}] has not been configured.");
            // @codeCoverageIgnoreEnd
        }

        $config['options']['prefix'] = config('aicl-horizon.prefix') ?: 'horizon:';

        config(['database.redis.horizon' => $config]);
    }
}
