<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ToolHandlerInterface;

final class ToolHandlerAdapter implements ToolHandlerInterface
{
    public function __construct(
        private readonly ToolDefinition $tool,
        private readonly McpRequestContext $context,
        private readonly ToolExecutor $executor
    ) {
    }

    public function execute(array $arguments, ClientGateway $gateway): mixed
    {
        return $this->executor->execute($this->tool, $arguments, $this->context);
    }
}
