<?php

declare(strict_types=1);

namespace App\Application\Transfer\Service;

// PhpRedis is configured in snc_redis.yaml (type: phpredis).
// The SNC RedisProxy for phpredis extends \Redis, so we type-hint against it.

/**
 * Sliding-window rate limiter backed by Redis.
 *
 * Uses a Lua script for atomic increment+expire to prevent race conditions
 * between the check and the count update.
 *
 * Key format: rate_limit:{identifier}
 */
final class RateLimiterService
{
    private const KEY_PREFIX = 'rate_limit:';

    // Lua script: atomically increments counter and sets expiry on first hit
    private const LUA_SCRIPT = <<<'LUA'
        local key = KEYS[1]
        local limit = tonumber(ARGV[1])
        local window = tonumber(ARGV[2])
        local current = redis.call('INCR', key)
        if current == 1 then
            redis.call('EXPIRE', key, window)
        end
        if current > limit then
            return 0
        end
        return 1
    LUA;

    public function __construct(
        private readonly \Redis $redis,
        private readonly int $maxRequests = 100,
        private readonly int $windowSeconds = 60,
    ) {}

    /**
     * Returns true if the request is within the rate limit.
     * Returns false if the limit has been exceeded.
     */
    public function isAllowed(string $identifier): bool
    {
        // phpredis eval() signature: eval(string $script, array $args, int $numKeys)
        // KEYS[1] = key, ARGV[1] = limit, ARGV[2] = window
        $result = $this->redis->eval(
            self::LUA_SCRIPT,
            [$this->buildKey($identifier), (string) $this->maxRequests, (string) $this->windowSeconds],
            1,
        );

        return (bool) $result;
    }

    public function getRemainingRequests(string $identifier): int
    {
        $key     = $this->buildKey($identifier);
        $current = (int) ($this->redis->get($key) ?? 0);

        return max(0, $this->maxRequests - $current);
    }

    private function buildKey(string $identifier): string
    {
        return self::KEY_PREFIX . $identifier;
    }
}
