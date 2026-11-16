<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use Mcp\Server\ClientGateway;
use Mcp\Server\Exception\PromptGetException;
use Mcp\Server\Handler\PromptHandlerInterface;
use Psr\Log\LoggerInterface;

final class PromptHandlerAdapter implements PromptHandlerInterface
{
    public function __construct(
        private readonly PromptDefinition $prompt,
        private readonly McpRequestContext $context,
        private readonly AuditLogger $audit,
        private readonly LoggerInterface $logger
    ) {
    }

    public function get(array $arguments, ClientGateway $gateway): mixed
    {
        $started = hrtime(true);

        try {
            $result = ($this->prompt->handler)($arguments, $this->context);

            $this->audit->elementRead(
                $this->context,
                'prompt',
                $this->prompt->name,
                $this->prompt->permission,
                'success',
                $this->elapsed($started)
            );

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('MCP prompt generation failed.', [
                'prompt' => $this->prompt->name,
                'exception' => $e,
            ]);

            $this->audit->elementRead(
                $this->context,
                'prompt',
                $this->prompt->name,
                $this->prompt->permission,
                'error',
                $this->elapsed($started),
                $e->getMessage()
            );

            throw new PromptGetException('Unable to generate this prompt.');
        }
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
