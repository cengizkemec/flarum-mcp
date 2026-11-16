<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Api\Controller\Admin;

use CengizKemec\FlarumMcp\Mcp\PermissionRegistry;
use CengizKemec\FlarumMcp\Mcp\PromptRegistry;
use CengizKemec\FlarumMcp\Model\McpPrompt;
use CengizKemec\FlarumMcp\Permission\PermissionService;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PromptsController extends AdminController
{
    public function __construct(
        private readonly PromptRegistry $prompts,
        private readonly PermissionService $permissions,
        private readonly PermissionRegistry $permissionRegistry,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function handle(Request $request): Response
    {
        return $this->run($request, function () use ($request): Response {
            return match ($request->getAttribute('routeName')) {
                'cengizkemec-mcp.admin.prompts.index' => $this->index(),
                'cengizkemec-mcp.admin.prompts.create' => $this->create($request),
                'cengizkemec-mcp.admin.prompts.update' => $this->update($request),
                'cengizkemec-mcp.admin.prompts.delete' => $this->delete($request),
                default => $this->fail('Unknown action.', 404, 'not_found'),
            };
        });
    }

    private function index(): Response
    {
        $builtIn = $this->prompts->adminList($this->permissions, $this->translator);

        foreach ($builtIn as &$prompt) {
            $prompt['builtin'] = true;
            $prompt['enabled'] = true;
            $prompt['content'] = null;
        }
        unset($prompt);

        $custom = McpPrompt::query()->orderBy('name')->get()
            ->map(function (McpPrompt $prompt): array {
                $data = $prompt->toAdminArray();
                $data['builtin'] = false;

                return $data;
            })->all();

        return $this->ok([
            'prompts' => array_merge($builtIn, $custom),
            'permissions' => array_values(array_filter(
                array_keys($this->permissionRegistry->all()),
                fn (string $id): bool => ! $this->permissionRegistry->isAdminOnly($id)
            )),
        ]);
    }

    private function create(Request $request): Response
    {
        $input = $this->input($request);
        $name = $this->name($input['name'] ?? null);

        if ($this->prompts->get($name) !== null || McpPrompt::query()->where('name', $name)->exists()) {
            throw new ValidationException(['name' => 'A prompt with this name already exists.']);
        }

        $prompt = McpPrompt::query()->create([
            'name' => $name,
            'title' => $this->nullableText($input['title'] ?? null, 160, 'title'),
            'description' => $this->nullableText($input['description'] ?? null, 500, 'description'),
            'content' => $this->content($input['content'] ?? null),
            'arguments' => $this->arguments($input['arguments'] ?? []),
            'enabled' => $this->bool($input['enabled'] ?? true, true),
            'required_permission' => $this->permission($input['requiredPermission'] ?? 'prompts.read'),
            'locale' => $this->locale($input['locale'] ?? 'en'),
            'created_by_id' => $this->admin($request)->id,
        ]);

        return $this->ok(['prompt' => $prompt->toAdminArray()], 201);
    }

    private function update(Request $request): Response
    {
        $prompt = McpPrompt::query()->findOrFail((int) $this->param($request, 'id'));
        $input = $this->input($request);

        if (array_key_exists('name', $input)) {
            $name = $this->name($input['name']);

            if ($name !== $prompt->name && ($this->prompts->get($name) !== null || McpPrompt::query()->where('name', $name)->exists())) {
                throw new ValidationException(['name' => 'A prompt with this name already exists.']);
            }

            $prompt->name = $name;
        }

        if (array_key_exists('title', $input)) {
            $prompt->title = $this->nullableText($input['title'], 160, 'title');
        }

        if (array_key_exists('description', $input)) {
            $prompt->description = $this->nullableText($input['description'], 500, 'description');
        }

        if (array_key_exists('content', $input)) {
            $prompt->content = $this->content($input['content']);
        }

        if (array_key_exists('arguments', $input)) {
            $prompt->arguments = $this->arguments($input['arguments']);
        }

        if (array_key_exists('enabled', $input)) {
            $prompt->enabled = $this->bool($input['enabled']);
        }

        if (array_key_exists('requiredPermission', $input)) {
            $prompt->required_permission = $this->permission($input['requiredPermission']);
        }

        if (array_key_exists('locale', $input)) {
            $prompt->locale = $this->locale($input['locale']);
        }

        $prompt->save();

        return $this->ok(['prompt' => $prompt->toAdminArray()]);
    }

    private function delete(Request $request): Response
    {
        $prompt = McpPrompt::query()->findOrFail((int) $this->param($request, 'id'));
        $id = $prompt->id;
        $prompt->delete();

        return $this->ok(['deleted' => true, 'id' => $id]);
    }

    private function name(mixed $value): string
    {
        $name = trim((string) $value);

        if (! preg_match('/^[a-zA-Z0-9_-]{1,120}$/', $name)) {
            throw new ValidationException(['name' => 'Use 1-120 letters, numbers, underscores or hyphens.']);
        }

        return $name;
    }

    private function nullableText(mixed $value, int $max, string $key): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        if (mb_strlen($value) > $max) {
            throw new ValidationException([$key => "Text must not exceed $max characters."]);
        }

        return $value;
    }

    private function content(mixed $value): string
    {
        $content = trim((string) $value);

        if ($content === '' || mb_strlen($content) > 20000) {
            throw new ValidationException(['content' => 'Prompt content must be between 1 and 20,000 characters.']);
        }

        return $content;
    }

    /**
     * @return array<int, array{name: string, description: string, required: bool}>
     */
    private function arguments(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $arguments = [];

        foreach ($value as $argument) {
            if (! is_array($argument)) {
                continue;
            }

            $name = trim((string) ($argument['name'] ?? ''));

            if (! preg_match('/^[a-zA-Z0-9_]{1,64}$/', $name)) {
                throw new ValidationException(['arguments' => 'Argument names must use 1-64 letters, numbers or underscores.']);
            }

            $arguments[] = [
                'name' => $name,
                'description' => mb_substr(trim((string) ($argument['description'] ?? '')), 0, 200),
                'required' => (bool) ($argument['required'] ?? false),
            ];
        }

        if (count($arguments) > 20) {
            throw new ValidationException(['arguments' => 'A prompt may have at most 20 arguments.']);
        }

        return $arguments;
    }

    private function permission(mixed $value): string
    {
        $permission = (string) $value;

        if (! $this->permissionRegistry->exists($permission) || $this->permissionRegistry->isAdminOnly($permission)) {
            throw new ValidationException(['requiredPermission' => 'Unknown or unavailable permission identifier.']);
        }

        return $permission;
    }

    private function locale(mixed $value): string
    {
        $locale = strtolower(trim((string) $value));

        if (! preg_match('/^[a-z]{2}(?:_[a-z]{2})?$/', $locale)) {
            throw new ValidationException(['locale' => 'Invalid locale code.']);
        }

        return $locale;
    }
}
