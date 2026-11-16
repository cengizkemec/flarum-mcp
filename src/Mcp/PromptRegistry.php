<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use CengizKemec\FlarumMcp\Model\McpConnection;
use CengizKemec\FlarumMcp\Model\McpPrompt;
use CengizKemec\FlarumMcp\Permission\PermissionService;
use Flarum\Locale\TranslatorInterface;

final class PromptRegistry
{
    /** @var array<string, PromptDefinition> */
    private array $prompts = [];

    private bool $customPromptsRegistered = false;

    public function register(PromptDefinition $prompt): void
    {
        if (! preg_match('/^[a-zA-Z0-9_-]{1,120}$/', $prompt->name)) {
            throw new \InvalidArgumentException("Invalid MCP prompt name: {$prompt->name}");
        }

        if (isset($this->prompts[$prompt->name])) {
            throw new \InvalidArgumentException("An MCP prompt named {$prompt->name} is already registered.");
        }

        $this->prompts[$prompt->name] = $prompt;
    }

    /**
     * @param iterable<PromptDefinition> $prompts
     */
    public function registerMany(iterable $prompts): void
    {
        foreach ($prompts as $prompt) {
            $this->register($prompt);
        }
    }

    /**
     * Add database-backed custom prompts. A custom prompt may not reuse the
     * name of a built-in or third-party prompt.
     */
    public function registerCustomPrompts(): void
    {
        if ($this->customPromptsRegistered) {
            return;
        }

        $this->customPromptsRegistered = true;

        foreach (McpPrompt::query()->where('enabled', true)->orderBy('name')->get() as $prompt) {
            if (isset($this->prompts[$prompt->name])) {
                continue;
            }

            $this->register(new PromptDefinition(
                name: $prompt->name,
                permission: $prompt->required_permission ?: 'prompts.read',
                handler: fn (array $arguments): array => $this->renderCustomPrompt($prompt, $arguments),
                descriptionText: $prompt->description,
                arguments: $prompt->arguments ?? [],
                source: 'custom',
                enabledByDefault: true,
                titleKey: null
            ));
        }
    }

    public function get(string $name): ?PromptDefinition
    {
        return $this->prompts[$name] ?? null;
    }

    /**
     * @return array<string, PromptDefinition>
     */
    public function all(): array
    {
        return $this->prompts;
    }

    /**
     * @return array<string, PromptDefinition>
     */
    public function availableFor(McpConnection $connection, PermissionService $permissions): array
    {
        return array_filter(
            $this->prompts,
            fn (PromptDefinition $prompt): bool => $permissions->canUsePrompt(
                $connection,
                $prompt->permission,
                $prompt->extensionId
            )
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function adminList(PermissionService $permissions, TranslatorInterface $translator): array
    {
        $items = [];

        foreach ($this->prompts as $prompt) {
            $items[] = $prompt->toAdminArray(
                $translator,
                $permissions->integrationApproved($prompt->extensionId)
            );
        }

        usort($items, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $items;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function renderCustomPrompt(McpPrompt $prompt, array $arguments): array
    {
        $content = $prompt->content;

        foreach ($arguments as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $content = str_replace(
                ['{{'.$key.'}}', '{'.$key.'}'],
                (string) $value,
                $content
            );
        }

        return [
            [
                'role' => 'user',
                'content' => ['type' => 'text', 'text' => $content],
            ],
        ];
    }
}
