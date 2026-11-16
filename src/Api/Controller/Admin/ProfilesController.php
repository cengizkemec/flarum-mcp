<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Api\Controller\Admin;

use CengizKemec\FlarumMcp\Mcp\PermissionRegistry;
use CengizKemec\FlarumMcp\Model\McpCustomProfile;
use CengizKemec\FlarumMcp\Permission\PermissionService;
use CengizKemec\FlarumMcp\Permission\Permissions;
use Flarum\Foundation\ValidationException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ProfilesController extends AdminController
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly PermissionRegistry $registry
    ) {
    }

    public function handle(Request $request): Response
    {
        return $this->run($request, function () use ($request): Response {
            return match ($request->getAttribute('routeName')) {
                'cengizkemec-mcp.admin.profiles.index' => $this->index(),
                'cengizkemec-mcp.admin.profiles.create' => $this->create($request),
                'cengizkemec-mcp.admin.profiles.update' => $this->update($request),
                'cengizkemec-mcp.admin.profiles.delete' => $this->delete($request),
                'cengizkemec-mcp.admin.profiles.duplicate' => $this->duplicate($request),
                'cengizkemec-mcp.admin.profiles.export' => $this->export($request),
                'cengizkemec-mcp.admin.profiles.import' => $this->import($request),
                default => $this->fail('Unknown action.', 404, 'not_found'),
            };
        });
    }

    private function index(): Response
    {
        return $this->ok([
            'profiles' => $this->permissions->profiles(),
            'permissions' => $this->permissionCatalog(),
        ]);
    }

    private function create(Request $request): Response
    {
        $input = $this->input($request);
        $name = $this->name($input['name'] ?? null);
        $description = $this->description($input['description'] ?? null);
        $permissions = $this->permissions->sanitizePermissions(
            is_array($input['permissions'] ?? null) ? $input['permissions'] : []
        );

        $profile = McpCustomProfile::query()->create([
            'name' => $this->uniqueName($name),
            'description' => $description,
            'permissions' => $permissions,
            'created_by_id' => $this->admin($request)->id,
        ]);

        $this->permissions->forgetCaches();

        return $this->ok(['profile' => $this->profileData($profile)], 201);
    }

    private function update(Request $request): Response
    {
        $profile = $this->customProfile($request);
        $input = $this->input($request);

        if (array_key_exists('name', $input)) {
            $profile->name = $this->uniqueName($this->name($input['name']), $profile->id);
        }

        if (array_key_exists('description', $input)) {
            $profile->description = $this->description($input['description']);
        }

        if (array_key_exists('permissions', $input)) {
            if (! is_array($input['permissions'])) {
                throw new ValidationException(['permissions' => 'Permissions must be an array.']);
            }

            $profile->permissions = $this->permissions->sanitizePermissions($input['permissions']);
        }

        $profile->save();
        $this->permissions->forgetCaches();

        return $this->ok(['profile' => $this->profileData($profile)]);
    }

    private function delete(Request $request): Response
    {
        $profile = $this->customProfile($request);
        $id = $profile->id;
        $profile->delete();
        $this->permissions->forgetCaches();

        return $this->ok(['deleted' => true, 'id' => $id]);
    }

    private function duplicate(Request $request): Response
    {
        $source = $this->profileByIdentifier((string) $this->param($request, 'id'));

        $profile = McpCustomProfile::query()->create([
            'name' => $this->uniqueName(($source['name'] ?? $source['key']).' (copy)'),
            'description' => $source['description'],
            'permissions' => $this->permissions->sanitizePermissions($source['permissions']),
            'created_by_id' => $this->admin($request)->id,
        ]);

        $this->permissions->forgetCaches();

        return $this->ok(['profile' => $this->profileData($profile)], 201);
    }

    private function export(Request $request): Response
    {
        $profile = $this->profileByIdentifier((string) $this->param($request, 'id'));

        return $this->ok([
            'format' => 'flarum-mcp-profile',
            'version' => 1,
            'name' => $profile['name'] ?? $profile['key'],
            'description' => $profile['description'],
            'permissions' => $this->permissions->sanitizePermissions($profile['permissions']),
            'exportedFrom' => $profile['key'],
        ]);
    }

    private function import(Request $request): Response
    {
        $input = $this->input($request);
        $profile = is_array($input['profile'] ?? null) ? $input['profile'] : $input;

        if (($profile['format'] ?? 'flarum-mcp-profile') !== 'flarum-mcp-profile') {
            throw new ValidationException(['profile' => 'Unsupported profile format.']);
        }

        $name = $this->name($profile['name'] ?? null);
        $permissions = $this->permissions->sanitizePermissions(
            is_array($profile['permissions'] ?? null) ? $profile['permissions'] : []
        );

        $created = McpCustomProfile::query()->create([
            'name' => $this->uniqueName($name.' (imported)'),
            'description' => $this->description($profile['description'] ?? null),
            'permissions' => $permissions,
            'created_by_id' => $this->admin($request)->id,
        ]);

        $this->permissions->forgetCaches();

        return $this->ok(['profile' => $this->profileData($created)], 201);
    }

    /**
     * @return array{key: string, name: string|null, description: string|null, permissions: string[], builtin: bool}
     */
    private function profileByIdentifier(string $identifier): array
    {
        $profile = $this->permissions->profile($identifier);

        if ($profile === null) {
            throw new ValidationException(['profile' => 'Profile not found.']);
        }

        return $profile;
    }

    private function customProfile(Request $request): McpCustomProfile
    {
        return McpCustomProfile::query()->findOrFail((int) $this->param($request, 'id'));
    }

    private function name(mixed $value): string
    {
        $name = trim((string) $value);

        if ($name === '' || mb_strlen($name) > 100) {
            throw new ValidationException(['name' => 'A name between 1 and 100 characters is required.']);
        }

        return $name;
    }

    private function description(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        if (mb_strlen($value) > 500) {
            throw new ValidationException(['description' => 'Description must not exceed 500 characters.']);
        }

        return $value;
    }

    private function uniqueName(string $name, ?int $exceptId = null): string
    {
        $base = $name;
        $suffix = 1;

        while (true) {
            $query = McpCustomProfile::query()->where('name', $name);

            if ($exceptId !== null) {
                $query->where('id', '!=', $exceptId);
            }

            if (! $query->exists()) {
                return $name;
            }

            $name = $base.' ('.(++$suffix).')';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function profileData(McpCustomProfile $profile): array
    {
        return [
            'key' => 'custom-'.$profile->id,
            'id' => $profile->id,
            'name' => $profile->name,
            'description' => $profile->description,
            'permissions' => $this->permissions->sanitizePermissions($profile->permissions ?? []),
            'builtin' => false,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function permissionCatalog(): array
    {
        $catalog = [];

        foreach ($this->registry->all() as $id => $definition) {
            if ($this->registry->isAdminOnly($id)) {
                continue;
            }

            $catalog[] = [
                'id' => $id,
                'group' => $definition['group'],
                'groupLabelKey' => Permissions::groupLabelKey($definition['group']),
                'labelKey' => Permissions::exists($id) ? Permissions::labelKey($id) : null,
                'label' => $definition['label'] ?? $id,
                'risk' => $definition['risk'],
                'extensionId' => $definition['extension_id'] ?? null,
            ];
        }

        usort($catalog, fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        return $catalog;
    }
}
