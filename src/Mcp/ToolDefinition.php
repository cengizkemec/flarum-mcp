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

/**
 * A single executable MCP tool.
 *
 * "handler" is called with the validated arguments and a request context. It
 * may return an array, a scalar, or an Mcp\Schema\Result\CallToolResult.
 */
final class ToolDefinition
{
    /**
     * @param array<string, mixed> $inputSchema
     * @param array<string, mixed>|null $outputSchema
     * @param Closure(array<string, mixed>, McpRequestContext): mixed $handler
     */
    public function __construct(
        public readonly string $name,
        public readonly array $inputSchema,
        public readonly string $permission,
        public readonly Closure $handler,
        public readonly ?string $descriptionKey = null,
        public readonly ?string $descriptionText = null,
        public readonly ?string $titleKey = null,
        public readonly ?string $titleText = null,
        public readonly ?array $outputSchema = null,
        public readonly bool $requiresApproval = false,
        public readonly string $source = 'builtin',
        public readonly ?string $extensionId = null,
        public readonly bool $enabledByDefault = true,
        public readonly array $meta = [],
        public readonly ?string $requiresExtension = null,
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

    public function isBuiltin(): bool
    {
        return $this->source === 'builtin';
    }

    public function toAdminArray(TranslatorInterface $translator, bool $enabled, bool $approved): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title($translator),
            'description' => $this->description($translator),
            'permission' => $this->permission,
            'source' => $this->source,
            'extensionId' => $this->extensionId,
            'isBuiltin' => $this->isBuiltin(),
            'enabled' => $enabled,
            'approved' => $approved,
            'requiresApproval' => $this->requiresApproval,
            'inputSchema' => $this->inputSchema,
            'outputSchema' => $this->outputSchema,
        ];
    }
}
