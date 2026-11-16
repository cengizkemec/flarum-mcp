<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Exception;

use RuntimeException;

class McpRateLimitException extends RuntimeException
{
    public function __construct(
        public readonly int $retryAfter,
        public readonly int $limit,
        public readonly int $remaining = 0
    ) {
        parent::__construct('Rate limit exceeded.');
    }
}
