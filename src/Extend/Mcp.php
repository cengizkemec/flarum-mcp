<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Extend;

use CengizKemec\FlarumMcp\Mcp\PermissionRegistry;
use CengizKemec\FlarumMcp\Mcp\PromptDefinition;
use CengizKemec\FlarumMcp\Mcp\PromptRegistry;
use CengizKemec\FlarumMcp\Mcp\ResourceDefinition;
use CengizKemec\FlarumMcp\Mcp\ResourceRegistry;
use CengizKemec\FlarumMcp\Mcp\ToolDefinition;
use CengizKemec\FlarumMcp\Mcp\ToolRegistry;
use CengizKemec\FlarumMcp\Skill\SkillParser;
use CengizKemec\FlarumMcp\Skill\SkillRegistry;
use Flarum\Extension\Extension;
use Flarum\Extend\ExtenderInterface;
use Illuminate\Contracts\Container\Container;

/**
 * Public integration API for third-party Flarum extensions.
 *
 * Every tool, resource and prompt registered here is tagged with the extension
 * that registered it. The administrator must approve that integration before
 * the capability becomes executable.
 */
class Mcp implements ExtenderInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $permissions = [];

    /** @var ToolDefinition[] */
    private array $tools = [];

    /** @var ResourceDefinition[] */
    private array $resources = [];

    /** @var PromptDefinition[] */
    private array $prompts = [];

    /** @var array<int, array{content: string, permission: string}> */
    private array $skills = [];

    public function permission(string $id, array $meta = []): self
    {
        $this->permissions[$id] = $meta;

        return $this;
    }

    public function tool(
        string $name,
        string $permission,
        array $inputSchema,
        callable $handler,
        ?string $description = null,
        ?string $title = null,
        bool $requiresApproval = false,
        ?array $outputSchema = null
    ): self {
        $this->tools[] = new ToolDefinition(
            name: $name,
            inputSchema: $inputSchema,
            permission: $permission,
            handler: \Closure::fromCallable($handler),
            descriptionText: $description,
            titleText: $title,
            outputSchema: $outputSchema,
            requiresApproval: $requiresApproval,
            source: 'extension',
            extensionId: 'pending',
            enabledByDefault: true
        );

        return $this;
    }

    public function resource(
        string $uri,
        string $name,
        string $permission,
        callable $handler,
        ?string $description = null,
        string $mimeType = 'application/json',
        ?string $title = null
    ): self {
        $this->resources[] = new ResourceDefinition(
            uri: $uri,
            name: $name,
            permission: $permission,
            handler: \Closure::fromCallable($handler),
            descriptionText: $description,
            titleText: $title,
            mimeType: $mimeType,
            source: 'extension',
            extensionId: 'pending'
        );

        return $this;
    }

    public function resourceTemplate(
        string $uriTemplate,
        string $name,
        string $permission,
        callable $handler,
        ?string $description = null,
        string $mimeType = 'application/json'
    ): self {
        $this->resources[] = new ResourceDefinition(
            uri: '',
            name: $name,
            permission: $permission,
            handler: \Closure::fromCallable($handler),
            uriTemplate: $uriTemplate,
            descriptionText: $description,
            titleText: null,
            mimeType: $mimeType,
            source: 'extension',
            extensionId: 'pending'
        );

        return $this;
    }

    public function prompt(
        string $name,
        string $permission,
        callable $handler,
        ?string $description = null,
        array $arguments = [],
        ?string $title = null
    ): self {
        $this->prompts[] = new PromptDefinition(
            name: $name,
            permission: $permission,
            handler: \Closure::fromCallable($handler),
            descriptionText: $description,
            arguments: $arguments,
            source: 'extension',
            extensionId: 'pending',
            titleText: $title
        );

        return $this;
    }

    /**
     * Register a SKILL.md document. The content is validated immediately, so an
     * invalid Skill fails the extension boot rather than the first MCP request.
     */
    public function skill(string $content, ?string $permission = null): self
    {
        $this->skills[] = [
            'content' => $content,
            'permission' => $permission ?? 'skills.read',
        ];

        return $this;
    }

    public function extend(Container $container, ?Extension $extension = null): void
    {
        $extensionId = $extension?->getId() ?? 'unknown-extension';

        /** @var PermissionRegistry $permissions */
        $permissions = $container->make(PermissionRegistry::class);

        foreach ($this->permissions as $id => $meta) {
            $permissions->register($id, array_merge($meta, ['extension_id' => $extensionId]));
        }

        /** @var ToolRegistry $tools */
        $tools = $container->make(ToolRegistry::class);

        foreach ($this->tools as $tool) {
            $tools->register($this->withToolExtension($tool, $extensionId));
        }

        /** @var ResourceRegistry $resources */
        $resources = $container->make(ResourceRegistry::class);

        foreach ($this->resources as $resource) {
            $resources->register($this->withResourceExtension($resource, $extensionId));
        }

        /** @var PromptRegistry $prompts */
        $prompts = $container->make(PromptRegistry::class);

        foreach ($this->prompts as $prompt) {
            $prompts->register($this->withPromptExtension($prompt, $extensionId));
        }

        /** @var SkillRegistry $skills */
        $skills = $container->make(SkillRegistry::class);
        /** @var SkillParser $parser */
        $parser = $container->make(SkillParser::class);

        foreach ($this->skills as $entry) {
            $content = $entry['content'];
            $parsed = $parser->parse($content, 'extension');
            $metadata = $parsed->metadata;
            $metadata['required_permission'] = $entry['permission'];
            $metadata['extension_id'] = $extensionId;

            $withPermission = new \CengizKemec\FlarumMcp\Skill\ParsedSkill(
                slug: $parsed->slug,
                name: $parsed->name,
                description: $parsed->description,
                version: $parsed->version,
                license: $parsed->license,
                tags: $parsed->tags,
                metadata: $metadata,
                body: $parsed->body,
                content: $parsed->content,
                checksum: $parsed->checksum
            );

            $skills->register($withPermission, $extensionId);
        }
    }

    private function withToolExtension(ToolDefinition $tool, string $extensionId): ToolDefinition
    {
        return new ToolDefinition(
            name: $tool->name,
            inputSchema: $tool->inputSchema,
            permission: $tool->permission,
            handler: $tool->handler,
            descriptionKey: $tool->descriptionKey,
            descriptionText: $tool->descriptionText,
            titleKey: $tool->titleKey,
            titleText: $tool->titleText,
            outputSchema: $tool->outputSchema,
            requiresApproval: $tool->requiresApproval,
            source: 'extension',
            extensionId: $extensionId,
            enabledByDefault: $tool->enabledByDefault,
            meta: $tool->meta
        );
    }

    private function withResourceExtension(ResourceDefinition $resource, string $extensionId): ResourceDefinition
    {
        return new ResourceDefinition(
            uri: $resource->uri,
            name: $resource->name,
            permission: $resource->permission,
            handler: $resource->handler,
            uriTemplate: $resource->uriTemplate,
            descriptionKey: $resource->descriptionKey,
            descriptionText: $resource->descriptionText,
            titleKey: $resource->titleKey,
            titleText: $resource->titleText,
            mimeType: $resource->mimeType,
            source: 'extension',
            extensionId: $extensionId,
            enabledByDefault: $resource->enabledByDefault
        );
    }

    private function withPromptExtension(PromptDefinition $prompt, string $extensionId): PromptDefinition
    {
        return new PromptDefinition(
            name: $prompt->name,
            permission: $prompt->permission,
            handler: $prompt->handler,
            descriptionKey: $prompt->descriptionKey,
            descriptionText: $prompt->descriptionText,
            arguments: $prompt->arguments,
            source: 'extension',
            extensionId: $extensionId,
            enabledByDefault: $prompt->enabledByDefault,
            titleKey: $prompt->titleKey,
            titleText: $prompt->titleText
        );
    }
}
