<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use CengizKemec\FlarumMcp\Model\McpApproval;
use CengizKemec\FlarumMcp\Model\McpConnection;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Request-scoped state for one authenticated MCP operation.
 *
 * It is deliberately free of service dependencies so that it can be passed to
 * third-party handlers without giving them access to internal services.
 */
final class McpRequestContext
{
    public function __construct(
        public readonly McpConnection $connection,
        public readonly User $actor,
        public readonly ServerRequestInterface $request,
        public readonly string $ipAddress,
        public readonly string $origin = 'mcp',
        public readonly ?McpApproval $approval = null,
    ) {
    }

    public function isApprovalExecution(): bool
    {
        return $this->origin === 'approval' && $this->approval !== null;
    }
}
