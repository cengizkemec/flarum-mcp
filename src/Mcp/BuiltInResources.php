<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use CengizKemec\FlarumMcp\Skill\SkillManager;

final class BuiltInResources
{
    public static function register(ResourceRegistry $registry): void
    {
        $registry->registerMany([
            new ResourceDefinition(
                uri: 'flarum://forum/info',
                name: 'forum_info',
                permission: 'system.info.read',
                handler: fn (McpRequestContext $context): array => resolve(FlarumDataService::class)->forumInfo($context->actor),
                descriptionKey: 'cengizkemec-mcp.lib.resource.forum_info.description',
                titleKey: 'cengizkemec-mcp.lib.resource.forum_info.title',
                extensionId: 'cengizkemec-mcp'
            ),
            new ResourceDefinition(
                uri: 'flarum://forum/statistics',
                name: 'forum_statistics',
                permission: 'system.statistics.read',
                handler: fn (McpRequestContext $context): array => resolve(FlarumDataService::class)->forumStatistics($context->actor),
                descriptionKey: 'cengizkemec-mcp.lib.resource.forum_statistics.description',
                titleKey: 'cengizkemec-mcp.lib.resource.forum_statistics.title',
                extensionId: 'cengizkemec-mcp'
            ),
            new ResourceDefinition(
                uri: 'flarum://forum/configuration',
                name: 'forum_configuration',
                permission: 'system.settings.read',
                handler: fn (McpRequestContext $context): array => resolve(FlarumDataService::class)->forumConfiguration($context->actor),
                descriptionKey: 'cengizkemec-mcp.lib.resource.forum_configuration.description',
                titleKey: 'cengizkemec-mcp.lib.resource.forum_configuration.title',
                extensionId: 'cengizkemec-mcp'
            ),
            new ResourceDefinition(
                uri: 'flarum://discussions/recent',
                name: 'recent_discussions',
                permission: 'discussions.read',
                handler: fn (McpRequestContext $context): array => resolve(FlarumDataService::class)->searchDiscussions(
                    $context->actor,
                    [],
                    20,
                    0,
                    'newest'
                ),
                descriptionKey: 'cengizkemec-mcp.lib.resource.recent_discussions.description',
                titleKey: 'cengizkemec-mcp.lib.resource.recent_discussions.title',
                extensionId: 'cengizkemec-mcp'
            ),
            new ResourceDefinition(
                uri: 'flarum://tags',
                name: 'tags',
                permission: 'tags.list',
                handler: fn (McpRequestContext $context): array => resolve(FlarumDataService::class)->listTags($context->actor),
                descriptionKey: 'cengizkemec-mcp.lib.resource.tags.description',
                titleKey: 'cengizkemec-mcp.lib.resource.tags.title',
                extensionId: 'cengizkemec-mcp',
                requiresExtension: 'flarum-tags'
            ),
            new ResourceDefinition(
                uri: 'flarum://users/me',
                name: 'current_user',
                permission: 'users.read',
                handler: fn (McpRequestContext $context): array => resolve(FlarumDataService::class)->currentUser($context->actor),
                descriptionKey: 'cengizkemec-mcp.lib.resource.current_user.description',
                titleKey: 'cengizkemec-mcp.lib.resource.current_user.title',
                extensionId: 'cengizkemec-mcp'
            ),
            new ResourceDefinition(
                uri: 'flarum://notifications',
                name: 'notifications',
                permission: 'notifications.read',
                handler: fn (McpRequestContext $context): array => resolve(FlarumDataService::class)->notifications($context->actor, 25),
                descriptionKey: 'cengizkemec-mcp.lib.resource.notifications.description',
                titleKey: 'cengizkemec-mcp.lib.resource.notifications.title',
                extensionId: 'cengizkemec-mcp'
            ),
            new ResourceDefinition(
                uri: 'flarum://skills',
                name: 'skills',
                permission: 'skills.read',
                handler: fn (McpRequestContext $context): array => resolve(SkillManager::class)->listForContext($context),
                descriptionKey: 'cengizkemec-mcp.lib.resource.skills.description',
                titleKey: 'cengizkemec-mcp.lib.resource.skills.title',
                extensionId: 'cengizkemec-mcp'
            ),
            new ResourceDefinition(
                uri: '',
                name: 'discussion_by_id',
                permission: 'posts.read',
                handler: fn (McpRequestContext $context, array $variables): array => resolve(FlarumDataService::class)->getDiscussion(
                    $context->actor,
                    (int) ($variables['id'] ?? 0),
                    true,
                    20
                ),
                uriTemplate: 'flarum://discussions/{id}',
                descriptionKey: 'cengizkemec-mcp.lib.resource.discussion_by_id.description',
                titleKey: 'cengizkemec-mcp.lib.resource.discussion_by_id.title',
                extensionId: 'cengizkemec-mcp'
            ),
            new ResourceDefinition(
                uri: '',
                name: 'user_by_id',
                permission: 'users.read',
                handler: fn (McpRequestContext $context, array $variables): array => resolve(FlarumDataService::class)->getUser(
                    $context->actor,
                    (int) ($variables['id'] ?? 0),
                    null
                ),
                uriTemplate: 'flarum://users/{id}',
                descriptionKey: 'cengizkemec-mcp.lib.resource.user_by_id.description',
                titleKey: 'cengizkemec-mcp.lib.resource.user_by_id.title',
                extensionId: 'cengizkemec-mcp'
            ),
            new ResourceDefinition(
                uri: '',
                name: 'tag_by_slug',
                permission: 'tags.read',
                handler: fn (McpRequestContext $context, array $variables): array => resolve(FlarumDataService::class)->getTag(
                    $context->actor,
                    null,
                    (string) ($variables['slug'] ?? '')
                ),
                uriTemplate: 'flarum://tags/{slug}',
                descriptionKey: 'cengizkemec-mcp.lib.resource.tag_by_slug.description',
                titleKey: 'cengizkemec-mcp.lib.resource.tag_by_slug.title',
                extensionId: 'cengizkemec-mcp',
                requiresExtension: 'flarum-tags'
            ),
            new ResourceDefinition(
                uri: '',
                name: 'skill_by_slug',
                permission: 'skills.read',
                handler: fn (McpRequestContext $context, array $variables): array => resolve(SkillManager::class)->findForContext(
                    $context,
                    (string) ($variables['slug'] ?? '')
                ),
                uriTemplate: 'flarum://skills/{slug}',
                descriptionKey: 'cengizkemec-mcp.lib.resource.skill_by_slug.description',
                titleKey: 'cengizkemec-mcp.lib.resource.skill_by_slug.title',
                mimeType: 'text/markdown',
                extensionId: 'cengizkemec-mcp'
            ),
        ]);
    }
}
