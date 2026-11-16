<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

final class AuthResult
{
    public function __construct(
        public readonly \CengizKemec\FlarumMcp\Model\McpConnection $connection,
        public readonly \Flarum\User\User $actor,
        public readonly string $tokenPrefix,
    ) {
    }
}
