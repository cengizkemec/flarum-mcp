<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use CengizKemec\FlarumMcp\Exception\McpRateLimitException;
use CengizKemec\FlarumMcp\Model\McpConnection;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Log\LoggerInterface;

/**
 * Per-connection fixed-window rate limiting.
 *
 * The cache is used instead of a database table because MCP requests are
 * short-lived and the counters do not need to survive a cache flush. If the
 * cache backend is unavailable the request is allowed (fail open) and a
 * warning is logged; authentication still applies.
 */
final class RateLimiter
{
    private const PREFIX = 'cengizkemec-mcp:rl:';

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    public function consume(McpConnection $connection, string $ipAddress): RateLimitResult
    {
        $now = time();

        $windows = [
            ['minute', 60, (int) $connection->rate_limit_per_minute],
            ['hour', 3600, (int) $connection->rate_limit_per_hour],
            ['day', 86400, (int) $connection->rate_limit_per_day],
        ];

        $lowestRemaining = null;
        $lowestReset = null;
        $lowestLimit = null;

        foreach ($windows as [$name, $ttl, $limit]) {
            if ($limit < 1) {
                continue;
            }

            $bucket = intdiv($now, $ttl);
            $key = self::PREFIX.$connection->id.':'.$name.':'.$bucket;

            try {
                if (! $this->cache->add($key, 1, $ttl + 5)) {
                    $this->cache->increment($key);
                }

                $used = (int) $this->cache->get($key, 1);
            } catch (\Throwable $e) {
                $this->logger->warning('MCP rate limiter cache unavailable; allowing request.', [
                    'connection_id' => $connection->id,
                    'exception' => $e,
                ]);

                return new RateLimitResult(0, 0, $now);
            }

            $remaining = max(0, $limit - $used);
            $resetAt = ($bucket + 1) * $ttl;

            if ($lowestRemaining === null || $remaining < $lowestRemaining) {
                $lowestRemaining = $remaining;
                $lowestReset = $resetAt;
                $lowestLimit = $limit;
            }

            if ($used > $limit) {
                $retryAfter = max(1, $resetAt - $now);

                throw new McpRateLimitException($retryAfter, $limit, 0);
            }
        }

        return new RateLimitResult(
            $lowestLimit ?? 0,
            $lowestRemaining ?? 0,
            $lowestReset ?? ($now + 60)
        );
    }
}
