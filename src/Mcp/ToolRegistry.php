<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use CengizKemec\FlarumMcp\Model\McpConnection;
use CengizKemec\FlarumMcp\Permission\PermissionService;
use Flarum\Locale\TranslatorInterface;

final class ToolRegistry
{
    /** @var array<string, ToolDefinition> */
    private array $tools = [];

    public function register(ToolDefinition $tool): void
    {
        if (! preg_match('/^[a-zA-Z0-9_-]{1,120}$/', $tool->name)) {
            throw new \InvalidArgumentException("Invalid MCP tool name: {$tool->name}");
        }

        if (isset($this->tools[$tool->name])) {
            throw new \InvalidArgumentException("An MCP tool named {$tool->name} is already registered.");
        }

        $this->tools[$tool->name] = $tool;
    }

    /**
     * @param iterable<ToolDefinition> $tools
     */
    public function registerMany(iterable $tools): void
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function get(string $name): ?ToolDefinition
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array<string, ToolDefinition>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Tools that are visible to this connection. Execution is checked again in
     * ToolExecutor, so hiding a tool is never the only line of defence.
     *
     * @return array<string, ToolDefinition>
     */
    public function availableFor(McpConnection $connection, PermissionService $permissions): array
    {
        return array_filter(
            $this->tools,
            fn (ToolDefinition $tool): bool => $permissions->canUseTool($connection, $tool)
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function adminList(PermissionService $permissions, TranslatorInterface $translator): array
    {
        $items = [];

        foreach ($this->tools as $tool) {
            $items[] = $tool->toAdminArray(
                $translator,
                $permissions->toolEnabled($tool->name, $tool->enabledByDefault),
                $permissions->integrationApproved($tool->extensionId)
            );
        }

        usort($items, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $items;
    }
}
