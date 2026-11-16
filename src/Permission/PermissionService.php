<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Permission;

use CengizKemec\FlarumMcp\Mcp\PermissionRegistry;
use CengizKemec\FlarumMcp\Mcp\ToolDefinition;
use CengizKemec\FlarumMcp\Model\McpConnection;
use CengizKemec\FlarumMcp\Model\McpCustomProfile;
use CengizKemec\FlarumMcp\Model\McpExtensionApproval;
use CengizKemec\FlarumMcp\Model\McpResourceState;
use CengizKemec\FlarumMcp\Model\McpToolState;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;

/**
 * Evaluates the effective MCP permission of a connection.
 *
 * The effective permission is always the intersection of:
 *   1. the assigned profile,
 *   2. the connection's optional custom allowlist,
 *   3. the tool/resource switch,
 *   4. the extension integration approval state.
 *
 * Flarum abilities are checked separately at execution time and are never
 * bypassed by an MCP permission.
 */
final class PermissionService
{
    /** @var array<string, bool>|null */
    private ?array $toolStates = null;

    /** @var array<string, bool>|null */
    private ?array $resourceStates = null;

    /** @var array<string, string>|null */
    private ?array $integrationStates = null;

    /** @var array<string, array<string, mixed>|null> */
    private array $profileCache = [];

    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly \Flarum\Extension\ExtensionManager $extensions
    ) {
    }

    /**
     * @return array<string, array{name_key: string, description_key: string, permissions: string[]}>
     */
    public function builtInProfiles(): array
    {
        return Permissions::builtInProfiles();
    }

    /**
     * @return array<int, array{key: string, name: string, description: string|null, permissions: string[], builtin: bool}>
     */
    public function profiles(): array
    {
        $profiles = [];

        foreach (Permissions::builtInProfiles() as $key => $definition) {
            $profiles[] = [
                'key' => $key,
                'name_key' => $definition['name_key'],
                'description_key' => $definition['description_key'],
                'name' => null,
                'description' => null,
                'permissions' => $key === Permissions::PROFILE_FULL ? $this->fullPermissions() : $definition['permissions'],
                'builtin' => true,
            ];
        }

        foreach (McpCustomProfile::query()->orderBy('name')->get() as $profile) {
            $profiles[] = [
                'key' => 'custom-'.$profile->id,
                'name_key' => null,
                'description_key' => null,
                'name' => $profile->name,
                'description' => $profile->description,
                'permissions' => $this->sanitizePermissions($profile->permissions ?? []),
                'builtin' => false,
                'id' => $profile->id,
            ];
        }

        return $profiles;
    }

    /**
     * @return array{key: string, name: string|null, description: string|null, permissions: string[], builtin: bool, name_key?: string, description_key?: string}|null
     */
    public function profile(string $key): ?array
    {
        if (array_key_exists($key, $this->profileCache)) {
            return $this->profileCache[$key];
        }

        $builtIns = Permissions::builtInProfiles();
        $profile = null;

        if (isset($builtIns[$key])) {
            $profile = [
                'key' => $key,
                'name_key' => $builtIns[$key]['name_key'],
                'description_key' => $builtIns[$key]['description_key'],
                'name' => null,
                'description' => null,
                'permissions' => $key === Permissions::PROFILE_FULL ? $this->fullPermissions() : $builtIns[$key]['permissions'],
                'builtin' => true,
            ];
        } elseif (str_starts_with($key, 'custom-')) {
            $id = (int) substr($key, 7);
            $custom = McpCustomProfile::query()->find($id);

            if ($custom) {
                $profile = [
                    'key' => $key,
                    'name' => $custom->name,
                    'description' => $custom->description,
                    'permissions' => $this->sanitizePermissions($custom->permissions ?? []),
                    'builtin' => false,
                ];
            }
        }

        return $this->profileCache[$key] = $profile;
    }

    /**
     * @return string[]
     */
    public function profilePermissions(string $key): array
    {
        return $this->profile($key)['permissions'] ?? [];
    }

    /**
     * The full profile is intentionally dynamic: extensions may register new
     * permissions after this class is constructed, and Full Access should
     * include every non-admin permission that exists at request time.
     *
     * @return string[]
     */
    private function fullPermissions(): array
    {
        return array_values(array_filter(
            array_keys($this->registry->all()),
            fn (string $id): bool => ! $this->registry->isAdminOnly($id)
        ));
    }

    /**
     * @return string[]
     */
    public function effectivePermissions(McpConnection $connection): array
    {
        $profile = $this->profile($connection->permission_profile);

        if ($profile === null) {
            return [];
        }

        $permissions = $profile['permissions'];

        if ($connection->custom_permissions !== null) {
            $permissions = array_values(array_intersect($permissions, $this->sanitizePermissions($connection->custom_permissions)));
        }

        return $this->sanitizePermissions($permissions);
    }

    public function allows(McpConnection $connection, string $permission, bool $ignoreConnectionState = false): bool
    {
        if (! $ignoreConnectionState && ! $connection->isUsable()) {
            return false;
        }

        if (! $this->registry->exists($permission)) {
            return false;
        }

        return in_array($permission, $this->effectivePermissions($connection), true);
    }

    public function assertAllows(McpConnection $connection, string $permission): void
    {
        if (! $this->allows($connection, $permission)) {
            throw new PermissionDeniedException();
        }
    }

    /**
     * Drop per-request caches after an administrative state change. In a
     * normal PHP request this is mostly a no-op, but it keeps long-running
     * processes and integration tests consistent within the same container.
     */
    public function forgetCaches(): void
    {
        $this->toolStates = null;
        $this->resourceStates = null;
        $this->integrationStates = null;
        $this->profileCache = [];
    }

    public function toolEnabled(string $toolName, bool $default = true): bool
    {
        if ($this->toolStates === null) {
            $this->toolStates = [];
            foreach (McpToolState::query()->get(['tool_name', 'enabled']) as $state) {
                $this->toolStates[$state->tool_name] = (bool) $state->enabled;
            }
        }

        return $this->toolStates[$toolName] ?? $default;
    }

    public function resourceEnabled(string $uri, bool $default = true): bool
    {
        if ($this->resourceStates === null) {
            $this->resourceStates = [];
            foreach (McpResourceState::query()->get(['resource_uri', 'enabled']) as $state) {
                $this->resourceStates[$state->resource_uri] = (bool) $state->enabled;
            }
        }

        return $this->resourceStates[$uri] ?? $default;
    }

    public function integrationApproved(?string $extensionId): bool
    {
        if ($extensionId === null || $extensionId === '' || $extensionId === 'cengizkemec-mcp') {
            return true;
        }

        if ($this->integrationStates === null) {
            $this->integrationStates = [];
            foreach (McpExtensionApproval::query()->get(['extension_id', 'status']) as $approval) {
                $this->integrationStates[$approval->extension_id] = $approval->status;
            }
        }

        return ($this->integrationStates[$extensionId] ?? McpExtensionApproval::STATUS_PENDING) === McpExtensionApproval::STATUS_APPROVED;
    }

    public function canUseTool(McpConnection $connection, ToolDefinition $tool): bool
    {
        if ($tool->requiresExtension !== null && ! $this->extensions->isEnabled($tool->requiresExtension)) {
            return false;
        }

        if (! $this->allows($connection, $tool->permission)) {
            return false;
        }

        if (! $this->toolEnabled($tool->name, $tool->enabledByDefault)) {
            return false;
        }

        return $this->integrationApproved($tool->extensionId);
    }

    public function requiresApproval(McpConnection $connection, ToolDefinition $tool): bool
    {
        if (! $connection->require_approval) {
            return false;
        }

        return $tool->requiresApproval || $this->registry->requiresApproval($tool->permission);
    }

    public function canReadResource(
        McpConnection $connection,
        string $permission,
        string $uri,
        ?string $extensionId = null,
        ?string $requiresExtension = null
    ): bool {
        if ($requiresExtension !== null && ! $this->extensions->isEnabled($requiresExtension)) {
            return false;
        }

        return $this->allows($connection, $permission)
            && $this->resourceEnabled($uri)
            && $this->integrationApproved($extensionId);
    }

    public function canUsePrompt(McpConnection $connection, string $permission, ?string $extensionId = null): bool
    {
        return $this->allows($connection, $permission) && $this->integrationApproved($extensionId);
    }

    /**
     * Remove unknown and admin-only identifiers. This is the single choke point
     * that keeps a custom profile from smuggling in an administrative scope.
     *
     * @param array<int, mixed> $permissions
     * @return string[]
     */
    public function sanitizePermissions(array $permissions): array
    {
        $clean = [];

        foreach ($permissions as $permission) {
            if (! is_string($permission)) {
                continue;
            }

            if (! $this->registry->exists($permission) || $this->registry->isAdminOnly($permission)) {
                continue;
            }

            $clean[$permission] = $permission;
        }

        return array_values($clean);
    }

    public function isAdmin(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function assertAdmin(User $actor): void
    {
        $actor->assertAdmin();
    }
}
