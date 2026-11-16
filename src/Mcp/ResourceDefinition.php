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

final class ResourceDefinition
{
    /**
     * @param Closure(McpRequestContext, array<string, string>): mixed $handler
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $name,
        public readonly string $permission,
        public readonly Closure $handler,
        public readonly ?string $uriTemplate = null,
        public readonly ?string $descriptionKey = null,
        public readonly ?string $descriptionText = null,
        public readonly ?string $titleKey = null,
        public readonly ?string $titleText = null,
        public readonly string $mimeType = 'application/json',
        public readonly string $source = 'builtin',
        public readonly ?string $extensionId = null,
        public readonly bool $enabledByDefault = true,
        public readonly ?string $requiresExtension = null,
    ) {
    }

    public function isTemplate(): bool
    {
        return $this->uriTemplate !== null;
    }

    public function identifier(): string
    {
        return $this->uriTemplate ?? $this->uri;
    }

    public function isBuiltin(): bool
    {
        return $this->source === 'builtin';
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

    public function toAdminArray(TranslatorInterface $translator, bool $enabled, bool $approved): array
    {
        return [
            'uri' => $this->identifier(),
            'name' => $this->name,
            'title' => $this->title($translator),
            'description' => $this->description($translator),
            'permission' => $this->permission,
            'mimeType' => $this->mimeType,
            'type' => $this->isTemplate() ? 'template' : 'resource',
            'source' => $this->source,
            'extensionId' => $this->extensionId,
            'isBuiltin' => $this->isBuiltin(),
            'enabled' => $enabled,
            'approved' => $approved,
        ];
    }
}
