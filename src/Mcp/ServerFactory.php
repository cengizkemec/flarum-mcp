<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use CengizKemec\FlarumMcp\Permission\PermissionService;
use Flarum\Foundation\Config;
use Flarum\Foundation\Paths;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Mcp\Schema\Prompt as McpPrompt;
use Mcp\Schema\PromptArgument;
use Mcp\Schema\ResourceDefinition as McpResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\Server;
use Mcp\Server\Session\FileSessionLock;
use Mcp\Server\Session\FileSessionStore;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class ServerFactory
{
    public const VERSION = '1.0.0';

    public function __construct(
        private readonly ToolRegistry $tools,
        private readonly ResourceRegistry $resources,
        private readonly PromptRegistry $prompts,
        private readonly PermissionService $permissions,
        private readonly ToolExecutor $executor,
        private readonly AuditLogger $audit,
        private readonly TranslatorInterface $translator,
        private readonly SettingsRepositoryInterface $settings,
        private readonly Config $config,
        private readonly Paths $paths,
        private readonly ContainerInterface $container,
        private readonly LoggerInterface $logger
    ) {
    }

    public function create(McpRequestContext $context): Server
    {
        try {
            $this->prompts->registerCustomPrompts();
        } catch (\Throwable $e) {
            $this->logger->warning('Could not load custom MCP prompts.', ['exception' => $e]);
        }

        $builder = Server::builder()
            ->setServerInfo(
                'Flarum MCP',
                self::VERSION,
                $this->translator->trans('cengizkemec-mcp.lib.mcp.server_description')
            )
            ->setInstructions((string) $this->translator->trans('cengizkemec-mcp.lib.mcp.instructions'))
            ->setContainer($this->container)
            ->setLogger($this->logger);

        $this->configureSessions($builder);

        foreach ($this->tools->availableFor($context->connection, $this->permissions) as $tool) {
            $builder->add($this->toolSchema($tool), new ToolHandlerAdapter($tool, $context, $this->executor));
        }

        foreach ($this->resources->availableFor($context->connection, $this->permissions) as $resource) {
            if ($resource->isTemplate()) {
                $builder->add(
                    new ResourceTemplate(
                        uriTemplate: (string) $resource->uriTemplate,
                        name: $resource->name,
                        title: $resource->title($this->translator),
                        description: $resource->description($this->translator),
                        mimeType: $resource->mimeType,
                        meta: ['flarum/permission' => $resource->permission]
                    ),
                    new ResourceTemplateHandlerAdapter($resource, $context, $this->audit, $this->logger)
                );
            } else {
                $builder->add(
                    new McpResourceDefinition(
                        uri: $resource->uri,
                        name: $resource->name,
                        title: $resource->title($this->translator),
                        description: $resource->description($this->translator),
                        mimeType: $resource->mimeType,
                        meta: ['flarum/permission' => $resource->permission]
                    ),
                    new ResourceHandlerAdapter($resource, $context, $this->audit, $this->logger)
                );
            }
        }

        foreach ($this->prompts->availableFor($context->connection, $this->permissions) as $prompt) {
            $arguments = array_map(
                fn (array $argument): PromptArgument => new PromptArgument(
                    name: (string) $argument['name'],
                    description: $argument['description'] ?? null,
                    required: (bool) ($argument['required'] ?? false)
                ),
                $prompt->arguments
            );

            $builder->add(
                new McpPrompt(
                    name: $prompt->name,
                    title: $prompt->title($this->translator),
                    description: $prompt->description($this->translator),
                    arguments: $arguments === [] ? null : $arguments,
                    meta: ['flarum/permission' => $prompt->permission]
                ),
                new PromptHandlerAdapter($prompt, $context, $this->audit, $this->logger)
            );
        }

        return $builder->build();
    }

    public function maxBodyBytes(): int
    {
        $value = (int) $this->settings->get('cengizkemec-mcp.max_request_bytes', 4194304);

        return max(16384, min(16777216, $value));
    }

    public function forumUrl(): string
    {
        return (string) $this->config->url();
    }

    /**
     * @return string[]
     */
    public function allowedHosts(): array
    {
        $hosts = ['localhost', '127.0.0.1', '[::1]'];
        $forumHost = $this->config->url()->getHost();

        if ($forumHost !== '') {
            $hosts[] = $forumHost;
        }

        foreach ($this->jsonArraySetting('cengizkemec-mcp.allowed_hosts') as $host) {
            $host = strtolower(trim($host));

            if ($host !== '' && $host !== '*' && preg_match('/^[a-z0-9.\-\[\]:]+$/', $host)) {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * @return string[]
     */
    public function allowedOrigins(): array
    {
        $origins = [];

        foreach ($this->jsonArraySetting('cengizkemec-mcp.allowed_origins') as $origin) {
            if (! is_string($origin)) {
                continue;
            }

            $origin = rtrim(trim($origin), '/');

            if (preg_match('#^https?://[a-z0-9.\-\[\]:]+$#i', $origin)) {
                $origins[] = $origin;
            }
        }

        return array_values(array_unique($origins));
    }

    private function configureSessions(\Mcp\Server\Builder $builder): void
    {
        $ttl = max(300, min(86400, (int) $this->settings->get('cengizkemec-mcp.session_ttl', 3600)));
        $directory = rtrim((string) $this->paths->storage, '/\\').'/mcp-sessions';

        try {
            $builder->setSession(
                new FileSessionStore($directory, $ttl),
                gcProbability: 1,
                gcDivisor: 100
            );
            $builder->setSessionLock(new FileSessionLock($directory));
        } catch (\Throwable $e) {
            // Stateless handshake-era clients still work; sessions and
            // server-initiated requests are unavailable until storage is fixed.
            $this->logger->warning('MCP session storage is unavailable; continuing without sessions.', [
                'directory' => $directory,
                'exception' => $e,
            ]);
        }
    }

    private function toolSchema(ToolDefinition $tool): Tool
    {
        $annotations = new ToolAnnotations(
            readOnlyHint: $this->isReadOnly($tool),
            destructiveHint: $this->isDestructive($tool),
            idempotentHint: $this->isIdempotent($tool),
            openWorldHint: false
        );

        return new Tool(
            name: $tool->name,
            title: $tool->title($this->translator),
            inputSchema: $tool->inputSchema,
            description: $tool->description($this->translator),
            annotations: $annotations,
            meta: ['flarum/permission' => $tool->permission],
            outputSchema: $tool->outputSchema
        );
    }

    private function isReadOnly(ToolDefinition $tool): bool
    {
        return in_array($tool->permission, [
            'discussions.search',
            'discussions.read',
            'posts.read',
            'users.search',
            'users.read',
            'tags.list',
            'tags.read',
            'notifications.read',
            'system.info.read',
            'system.statistics.read',
            'system.settings.read',
            'audit.read',
            'approvals.read',
            'skills.read',
            'prompts.read',
        ], true);
    }

    private function isDestructive(ToolDefinition $tool): bool
    {
        return in_array($tool->permission, [
            'discussions.delete',
            'discussions.hide',
            'discussions.restore',
            'discussions.lock',
            'discussions.unlock',
            'discussions.pin',
            'discussions.unpin',
            'posts.delete',
            'posts.hide',
            'posts.restore',
            'users.edit',
            'users.suspend',
            'system.settings.edit',
            'system.extensions.manage',
            'tags.manage',
            'skills.manage',
            'prompts.manage',
        ], true);
    }

    private function isIdempotent(ToolDefinition $tool): bool
    {
        return str_starts_with($tool->name, 'get_')
            || str_starts_with($tool->name, 'list_')
            || str_starts_with($tool->name, 'search_')
            || in_array($tool->name, ['hide_discussion', 'restore_discussion', 'hide_post', 'restore_post', 'lock_discussion', 'unlock_discussion', 'sticky_discussion', 'unsticky_discussion'], true);
    }

    /**
     * @return array<int, mixed>
     */
    private function jsonArraySetting(string $key): array
    {
        $raw = $this->settings->get($key);

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
