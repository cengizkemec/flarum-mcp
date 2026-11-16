<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use Closure;
use Flarum\Locale\TranslatorInterface;

final class PromptDefinition
{
    /**
     * @param Closure(array<string, mixed>, McpRequestContext): mixed $handler
     * @param array<int, array{name: string, description?: string, required?: bool}> $arguments
     */
    public function __construct(
        public readonly string $name,
        public readonly string $permission,
        public readonly Closure $handler,
        public readonly ?string $descriptionKey = null,
        public readonly ?string $descriptionText = null,
        public readonly array $arguments = [],
        public readonly string $source = 'builtin',
        public readonly ?string $extensionId = null,
        public readonly bool $enabledByDefault = true,
        public readonly ?string $titleKey = null,
        public readonly ?string $titleText = null,
    ) {
    }

    public function description(TranslatorInterface $translator): string
    {
        if ($this->descriptionText !== null) {
            return $this->descriptionText;
        }

        if ($this->descriptionKey !== null) {
            return (string) $translator->trans($this->descriptionKey);
        }

        return $this->name;
    }

    public function title(TranslatorInterface $translator): ?string
    {
        if ($this->titleText !== null) {
            return $this->titleText;
        }

        if ($this->titleKey !== null) {
            return (string) $translator->trans($this->titleKey);
        }

        return null;
    }

    public function toAdminArray(TranslatorInterface $translator, bool $approved): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title($translator),
            'description' => $this->description($translator),
            'permission' => $this->permission,
            'arguments' => $this->arguments,
            'source' => $this->source,
            'extensionId' => $this->extensionId,
            'approved' => $approved,
        ];
    }
}
