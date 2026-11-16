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

final class ResourceRegistry
{
    /** @var array<string, ResourceDefinition> */
    private array $resources = [];

    public function register(ResourceDefinition $resource): void
    {
        $id = $resource->identifier();

        if ($id === '' || strlen($id) > 191) {
            throw new \InvalidArgumentException('MCP resource URI must be between 1 and 191 characters.');
        }

        if (isset($this->resources[$id])) {
            throw new \InvalidArgumentException("An MCP resource with identifier $id is already registered.");
        }

        $this->resources[$id] = $resource;
    }

    /**
     * @param iterable<ResourceDefinition> $resources
     */
    public function registerMany(iterable $resources): void
    {
        foreach ($resources as $resource) {
            $this->register($resource);
        }
    }

    public function get(string $uri): ?ResourceDefinition
    {
        return $this->resources[$uri] ?? null;
    }

    /**
     * @return array<string, ResourceDefinition>
     */
    public function all(): array
    {
        return $this->resources;
    }

    /**
     * @return array<string, ResourceDefinition>
     */
    public function availableFor(McpConnection $connection, PermissionService $permissions): array
    {
        return array_filter(
            $this->resources,
            fn (ResourceDefinition $resource): bool => $permissions->canReadResource(
                $connection,
                $resource->permission,
                $resource->identifier(),
                $resource->extensionId,
                $resource->requiresExtension
            )
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function adminList(PermissionService $permissions, TranslatorInterface $translator): array
    {
        $items = [];

        foreach ($this->resources as $resource) {
            $items[] = $resource->toAdminArray(
                $translator,
                $permissions->resourceEnabled($resource->identifier(), $resource->enabledByDefault),
                $permissions->integrationApproved($resource->extensionId)
            );
        }

        usort($items, fn (array $a, array $b): int => strcmp($a['uri'], $b['uri']));

        return $items;
    }
}
