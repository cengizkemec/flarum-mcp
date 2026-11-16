<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Skill;

use Carbon\Carbon;
use CengizKemec\FlarumMcp\Exception\McpSkillException;
use CengizKemec\FlarumMcp\Mcp\McpRequestContext;
use CengizKemec\FlarumMcp\Mcp\PermissionRegistry;
use CengizKemec\FlarumMcp\Model\McpSkill;
use CengizKemec\FlarumMcp\Permission\PermissionService;
use Flarum\Foundation\Paths;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

final class SkillManager
{
    private const DISABLED_SETTING = 'cengizkemec-mcp.disabled_builtin_skills';
    private const MAX_UPLOAD_BYTES = 5242880;

    /** @var array<string, ParsedSkill>|null */
    private ?array $builtIns = null;

    public function __construct(
        private readonly SkillParser $parser,
        private readonly RemoteFetcher $remote,
        private readonly ZipInstaller $zip,
        private readonly SettingsRepositoryInterface $settings,
        private readonly PermissionService $permissions,
        private readonly PermissionRegistry $permissionRegistry,
        private readonly SkillRegistry $registeredSkills,
        private readonly Paths $paths,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, ParsedSkill>
     */
    public function builtIns(): array
    {
        if ($this->builtIns !== null) {
            return $this->builtIns;
        }

        $this->builtIns = [];
        $directory = dirname(__DIR__, 2).'/resources/skills';

        if (! is_dir($directory)) {
            return $this->builtIns;
        }

        foreach (glob($directory.'/*/SKILL.md') ?: [] as $file) {
            try {
                $parsed = $this->parser->parse((string) file_get_contents($file), 'builtin');
                $this->builtIns[$parsed->slug] = $parsed;
            } catch (\Throwable $e) {
                $this->logger->warning('Could not load a built-in MCP skill.', [
                    'file' => $file,
                    'exception' => $e,
                ]);
            }
        }

        ksort($this->builtIns);

        return $this->builtIns;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allForAdmin(): array
    {
        $items = [];

        foreach ($this->builtIns() as $skill) {
            $items[] = $this->builtInToArray($skill, false);
        }

        foreach ($this->registeredSkills->all() as $slug => $entry) {
            $items[] = $this->registeredToArray($entry['skill'], $entry['extension_id'], false);
        }

        foreach (McpSkill::query()->orderBy('name')->get() as $skill) {
            $items[] = $skill->toAdminArray();
        }

        usort($items, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $items;
    }

    /**
     * @return array{skills: array<int, array<string, mixed>>}
     */
    public function listForContext(McpRequestContext $context): array
    {
        $items = [];

        foreach ($this->builtIns() as $skill) {
            $permission = $this->builtInPermission($skill);

            if (! $this->isBuiltInEnabled($skill->slug) || ! $this->permissions->allows($context->connection, $permission)) {
                continue;
            }

            $items[] = $this->builtInToArray($skill, false);
        }

        foreach ($this->registeredSkills->all() as $entry) {
            $skill = $entry['skill'];
            $permission = $this->registeredPermission($skill);

            if (! $this->permissions->integrationApproved($entry['extension_id'])
                || ! $this->permissions->allows($context->connection, $permission)) {
                continue;
            }

            $items[] = $this->registeredToArray($skill, $entry['extension_id'], false);
        }

        foreach (McpSkill::query()->where('enabled', true)->orderBy('name')->get() as $skill) {
            $permission = $this->customPermission($skill);

            if (! $this->permissions->allows($context->connection, $permission)) {
                continue;
            }

            $items[] = $skill->toAdminArray();
        }

        usort($items, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return ['skills' => $items];
    }

    public function findForContext(McpRequestContext $context, string $slug): ?array
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $builtIn = $this->builtIns()[$slug] ?? null;

        if ($builtIn !== null) {
            $permission = $this->builtInPermission($builtIn);

            if (! $this->isBuiltInEnabled($slug) || ! $this->permissions->allows($context->connection, $permission)) {
                return null;
            }

            return $this->builtInToArray($builtIn, true);
        }

        $registered = $this->registeredSkills->get($slug);

        if ($registered !== null) {
            $skill = $registered['skill'];
            $permission = $this->registeredPermission($skill);

            if (! $this->permissions->integrationApproved($registered['extension_id'])
                || ! $this->permissions->allows($context->connection, $permission)) {
                return null;
            }

            return $this->registeredToArray($skill, $registered['extension_id'], true);
        }

        $skill = McpSkill::query()->where('slug', $slug)->where('enabled', true)->first();

        if ($skill === null) {
            return null;
        }

        if (! $this->permissions->allows($context->connection, $this->customPermission($skill))) {
            return null;
        }

        return $skill->toAdminArray(true);
    }

    public function findForAdmin(string $id): ?array
    {
        if (str_starts_with($id, 'builtin:')) {
            $skill = $this->builtIns()[substr($id, 8)] ?? null;

            return $skill ? $this->builtInToArray($skill, true) : null;
        }

        if (str_starts_with($id, 'registered:')) {
            $registered = $this->registeredSkills->get(substr($id, 11));

            return $registered
                ? $this->registeredToArray($registered['skill'], $registered['extension_id'], true)
                : null;
        }

        if (! ctype_digit($id)) {
            return null;
        }

        $skill = McpSkill::query()->find((int) $id);

        return $skill?->toAdminArray(true);
    }

    public function installContent(
        User $admin,
        string $content,
        string $sourceType = 'manual',
        ?string $sourceUrl = null,
        bool $update = false
    ): array {
        $parsed = $this->parser->parse($content, $sourceType, $sourceUrl);

        if (isset($this->builtIns()[$parsed->slug])) {
            throw new McpSkillException('A built-in Skill already uses this slug. Choose another name.', 'builtin_skill_conflict');
        }

        $existing = McpSkill::query()->where('slug', $parsed->slug)->first();

        if ($existing !== null && ! $update) {
            throw new McpSkillException('A Skill with this slug is already installed. Use update to replace it.', 'skill_exists');
        }

        $skill = $existing ?? new McpSkill();
        $skill->slug = $parsed->slug;
        $skill->name = $parsed->name;
        $skill->description = $parsed->description;
        $skill->version = $parsed->version;
        $skill->source_type = $sourceType;
        $skill->source_url = $sourceUrl;
        $skill->content = $parsed->content;
        $skill->checksum = $parsed->checksum;
        $skill->is_builtin = false;
        $skill->required_permission = $existing?->required_permission ?: 'skills.read';
        $skill->metadata = $parsed->metadata;
        $skill->installed_by_id = $admin->id;
        $skill->enabled = $existing?->enabled ?? true;
        $skill->save();

        return $skill->toAdminArray();
    }

    public function installFromUrl(User $admin, string $url, bool $update = false): array
    {
        $content = $this->remote->fetch($url);

        return $this->installContent($admin, $content, 'url', $url, $update);
    }

    public function installFromZip(User $admin, string $base64, bool $update = false): array
    {
        $binary = base64_decode($base64, true);

        if ($binary === false || $binary === '') {
            throw new McpSkillException('The ZIP upload is not valid base64 data.', 'invalid_zip');
        }

        if (strlen($binary) > self::MAX_UPLOAD_BYTES) {
            throw new McpSkillException('The ZIP upload exceeds the 5 MiB limit.', 'zip_too_large');
        }

        $directory = $this->paths->storage.'/mcp-tmp';

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new McpSkillException('Could not create a temporary directory for the ZIP upload.', 'storage_unavailable');
        }

        $path = tempnam($directory, 'skill-');

        if ($path === false) {
            throw new McpSkillException('Could not create a temporary file for the ZIP upload.', 'storage_unavailable');
        }

        try {
            if (file_put_contents($path, $binary) === false) {
                throw new McpSkillException('Could not write the ZIP upload.', 'storage_unavailable');
            }

            $extracted = $this->zip->extractSkill($path);

            return $this->installContent($admin, $extracted['content'], 'zip', null, $update);
        } finally {
            @unlink($path);
        }
    }

    public function updateCustom(User $admin, int $id, ?string $content, ?string $name, ?string $description, ?string $permission): array
    {
        $skill = McpSkill::query()->findOrFail($id);

        if ($content !== null && $content !== '') {
            $parsed = $this->parser->parse($content, $skill->source_type, $skill->source_url);

            if ($parsed->slug !== $skill->slug) {
                throw new McpSkillException('The slug of an installed Skill cannot be changed.', 'skill_slug_immutable');
            }

            $skill->content = $parsed->content;
            $skill->checksum = $parsed->checksum;
            $skill->version = $parsed->version;
            $skill->name = $name ?: $parsed->name;
            $skill->description = $description ?: $parsed->description;
            $skill->metadata = $parsed->metadata;
        } else {
            if ($name !== null && trim($name) !== '') {
                $skill->name = mb_substr(trim($name), 0, 160);
            }

            if ($description !== null) {
                $skill->description = mb_substr(trim($description), 0, 500);
            }
        }

        if ($permission !== null && $permission !== '') {
            if (! $this->permissionRegistry->exists($permission)) {
                throw new McpSkillException('Unknown permission identifier.', 'invalid_permission');
            }

            $skill->required_permission = $permission;
        }

        $skill->save();

        return $skill->toAdminArray();
    }

    public function deleteCustom(int $id): void
    {
        $skill = McpSkill::query()->findOrFail($id);

        if ($skill->is_builtin) {
            throw new McpSkillException('Built-in Skills cannot be deleted. Disable them instead.', 'builtin_skill_delete');
        }

        $skill->delete();
    }

    public function setEnabled(string $id, bool $enabled): array
    {
        if (str_starts_with($id, 'builtin:')) {
            $slug = substr($id, 8);

            if (! isset($this->builtIns()[$slug])) {
                throw new McpSkillException('Built-in Skill not found.', 'skill_not_found');
            }

            $disabled = $this->disabledBuiltIns();

            if ($enabled) {
                $disabled = array_values(array_diff($disabled, [$slug]));
            } else {
                $disabled[] = $slug;
                $disabled = array_values(array_unique($disabled));
            }

            $this->settings->set(self::DISABLED_SETTING, json_encode($disabled));

            return $this->builtInToArray($this->builtIns()[$slug], false);
        }

        if (! ctype_digit($id)) {
            throw new McpSkillException('Skill not found.', 'skill_not_found');
        }

        $skill = McpSkill::query()->findOrFail((int) $id);
        $skill->enabled = $enabled;
        $skill->save();

        return $skill->toAdminArray();
    }

    public function export(string $id): array
    {
        $skill = $this->findForAdmin($id);

        if ($skill === null) {
            throw new McpSkillException('Skill not found.', 'skill_not_found');
        }

        return [
            'filename' => $skill['slug'].'.md',
            'content' => $skill['content'],
        ];
    }

    private function builtInToArray(ParsedSkill $skill, bool $includeContent): array
    {
        $data = [
            'id' => 'builtin:'.$skill->slug,
            'slug' => $skill->slug,
            'name' => $skill->name,
            'description' => $skill->description,
            'version' => $skill->version,
            'sourceType' => 'builtin',
            'sourceUrl' => null,
            'checksum' => $skill->checksum,
            'enabled' => $this->isBuiltInEnabled($skill->slug),
            'isBuiltin' => true,
            'requiredPermission' => $this->builtInPermission($skill),
            'metadata' => $skill->metadata,
            'createdAt' => null,
            'updatedAt' => null,
        ];

        if ($includeContent) {
            $data['content'] = $skill->content;
        }

        return $data;
    }

    private function builtInPermission(ParsedSkill $skill): string
    {
        $permission = $skill->metadata['required_permission'] ?? 'skills.read';

        return is_string($permission) && $this->permissionRegistry->exists($permission)
            ? $permission
            : 'skills.read';
    }

    private function customPermission(McpSkill $skill): string
    {
        $permission = $skill->required_permission ?: 'skills.read';

        return $this->permissionRegistry->exists($permission) ? $permission : 'skills.read';
    }

    private function registeredToArray(ParsedSkill $skill, string $extensionId, bool $includeContent): array
    {
        $data = [
            'id' => 'registered:'.$skill->slug,
            'slug' => $skill->slug,
            'name' => $skill->name,
            'description' => $skill->description,
            'version' => $skill->version,
            'sourceType' => 'extension',
            'sourceUrl' => null,
            'checksum' => $skill->checksum,
            'enabled' => true,
            'isBuiltin' => false,
            'extensionId' => $extensionId,
            'requiredPermission' => $this->registeredPermission($skill),
            'metadata' => $skill->metadata,
            'createdAt' => null,
            'updatedAt' => null,
        ];

        if ($includeContent) {
            $data['content'] = $skill->content;
        }

        return $data;
    }

    private function registeredPermission(ParsedSkill $skill): string
    {
        $permission = $skill->metadata['required_permission'] ?? 'skills.read';

        return is_string($permission) && $this->permissionRegistry->exists($permission)
            ? $permission
            : 'skills.read';
    }

    private function isBuiltInEnabled(string $slug): bool
    {
        return ! in_array($slug, $this->disabledBuiltIns(), true);
    }

    /**
     * @return string[]
     */
    private function disabledBuiltIns(): array
    {
        $raw = $this->settings->get(self::DISABLED_SETTING);
        $decoded = $raw ? json_decode($raw, true) : [];

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
