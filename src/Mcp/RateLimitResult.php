<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

final class RateLimitResult
{
    public function __construct(
        public readonly int $limit,
        public readonly int $remaining,
        public readonly int $resetAt,
    ) {
    }
}
