<?php

declare(strict_types=1);

namespace App\Application\Transfer\Service;

use Predis\Client;

/**
 * Redis-backed idempotency store.
 *
 * Guarantees exactly-once transfer execution:
 *  - Before processing: check if key exists → return cached transfer ID
 *  - After processing: store transfer ID under the key with TTL
 *
 * The key format is: idempotency:{idempotencyKey}
 */
final class IdempotencyService
{
    private const KEY_PREFIX = 'idempotency:';

    public function __construct(
        private readonly \Redis $redis,
        private readonly int $ttl = 86400, // 24 hours default
    ) {}

    public function get(string $idempotencyKey): ?string
    {
        $value = $this->redis->get($this->buildKey($idempotencyKey));

        return $value !== null ? (string) $value : null;
    }

    public function set(string $idempotencyKey, string $transferId): void
    {
        $this->redis->setex($this->buildKey($idempotencyKey), $this->ttl, $transferId);
    }

    public function exists(string $idempotencyKey): bool
    {
        return (bool) $this->redis->exists($this->buildKey($idempotencyKey));
    }

    private function buildKey(string $idempotencyKey): string
    {
        return self::KEY_PREFIX . $idempotencyKey;
    }
}
