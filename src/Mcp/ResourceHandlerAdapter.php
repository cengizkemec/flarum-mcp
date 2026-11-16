<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use Mcp\Server\ClientGateway;
use Mcp\Server\Exception\ResourceReadException;
use Mcp\Server\Handler\ResourceHandlerInterface;
use Psr\Log\LoggerInterface;

final class ResourceHandlerAdapter implements ResourceHandlerInterface
{
    public function __construct(
        private readonly ResourceDefinition $resource,
        private readonly McpRequestContext $context,
        private readonly AuditLogger $audit,
        private readonly LoggerInterface $logger
    ) {
    }

    public function read(string $uri, ClientGateway $gateway): mixed
    {
        $started = hrtime(true);

        try {
            $result = ($this->resource->handler)($this->context, []);

            $this->audit->elementRead(
                $this->context,
                'resource',
                $this->resource->identifier(),
                $this->resource->permission,
                'success',
                $this->elapsed($started)
            );

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('MCP resource read failed.', [
                'resource' => $this->resource->identifier(),
                'exception' => $e,
            ]);

            $this->audit->elementRead(
                $this->context,
                'resource',
                $this->resource->identifier(),
                $this->resource->permission,
                'error',
                $this->elapsed($started),
                $e->getMessage()
            );

            throw new ResourceReadException('Unable to read this resource.');
        }
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
