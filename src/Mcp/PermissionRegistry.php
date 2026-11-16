<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use CengizKemec\FlarumMcp\Permission\Permissions;

/**
 * Runtime permission catalogue. Built-ins are always present; third-party
 * extensions may register additional identifiers through the Mcp extender.
 */
final class PermissionRegistry
{
    /** @var array<string, array{group: string, risk: string, admin_only?: bool, label?: string, extension_id?: string}> */
    private array $permissions = [];

    public function __construct()
    {
        foreach (Permissions::definitions() as $id => $definition) {
            $this->permissions[$id] = $definition;
        }
    }

    /**
     * @param array{group?: string, risk?: string, admin_only?: bool, label?: string, extension_id?: string} $meta
     */
    public function register(string $id, array $meta = []): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9_.-]{1,119}$/', $id)) {
            throw new \InvalidArgumentException("Invalid MCP permission identifier: $id");
        }

        $this->permissions[$id] = array_merge(
            ['group' => 'integrations', 'risk' => 'high'],
            $this->permissions[$id] ?? [],
            $meta,
            ['id' => $id]
        );
    }

    /**
     * @return array<string, array{group: string, risk: string, admin_only?: bool, label?: string, extension_id?: string}>
     */
    public function all(): array
    {
        return $this->permissions;
    }

    public function exists(string $id): bool
    {
        return isset($this->permissions[$id]);
    }

    public function risk(string $id): string
    {
        return $this->permissions[$id]['risk'] ?? 'high';
    }

    public function requiresApproval(string $id): bool
    {
        return $this->risk($id) === 'high';
    }

    public function isAdminOnly(string $id): bool
    {
        return (bool) ($this->permissions[$id]['admin_only'] ?? false);
    }

    public function group(string $id): string
    {
        return $this->permissions[$id]['group'] ?? 'integrations';
    }
}
