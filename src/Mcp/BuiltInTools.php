<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use Carbon\Carbon;
use CengizKemec\FlarumMcp\Exception\McpSkillException;
use CengizKemec\FlarumMcp\Model\McpApproval;
use CengizKemec\FlarumMcp\Model\McpAuditLog;
use CengizKemec\FlarumMcp\Skill\SkillManager;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\ValidationException;

/**
 * Registers the first-party tool set. Descriptions and titles are translation
 * keys; schemas are deliberately strict (additionalProperties: false).
 */
final class BuiltInTools
{
    public static function register(ToolRegistry $registry): void
    {
        $registry->registerMany([
            self::searchDiscussions(),
            self::getDiscussion(),
            self::createDiscussion(),
            self::editDiscussion(),
            self::deleteDiscussion(),
            self::listRecentDiscussions(),

            self::getPosts(),
            self::getPost(),
            self::createPost(),
            self::editPost(),
            self::deletePost(),

            self::hideDiscussion(),
            self::restoreDiscussion(),
            self::hidePost(),
            self::restorePost(),

            self::lockDiscussion(),
            self::unlockDiscussion(),
            self::stickyDiscussion(),
            self::unstickyDiscussion(),

            self::searchUsers(),
            self::getUser(),
            self::getCurrentUser(),

            self::listTags(),
            self::getTag(),

            self::getNotifications(),
            self::markNotificationRead(),

            self::getForumInfo(),
            self::getForumStatistics(),
            self::getRecentActivity(),

            self::getSystemSettings(),
            self::setSystemSetting(),

            self::listSkills(),
            self::getSkill(),

            self::getAuditLogs(),
            self::getPendingApprovals(),
        ]);
    }

    private static function tool(
        string $name,
        string $permission,
        array $inputSchema,
        \Closure $handler,
        bool $requiresApproval = false,
        ?string $requiresExtension = null,
        ?array $outputSchema = null
    ): ToolDefinition {
        return new ToolDefinition(
            name: $name,
            inputSchema: $inputSchema,
            permission: $permission,
            handler: $handler,
            descriptionKey: "cengizkemec-mcp.lib.tool.$name.description",
            titleKey: "cengizkemec-mcp.lib.tool.$name.title",
            outputSchema: $outputSchema,
            requiresApproval: $requiresApproval,
            source: 'builtin',
            extensionId: 'cengizkemec-mcp',
            requiresExtension: $requiresExtension
        );
    }

    private static function schema(array $properties, array $required = []): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_values($required),
            'additionalProperties' => false,
        ];
    }

    private static function searchDiscussions(): ToolDefinition
    {
        return self::tool(
            'search_discussions',
            'discussions.search',
            self::schema([
                'query' => ['type' => 'string', 'maxLength' => 200, 'description' => 'Free-text search query.'],
                'tag' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Tag slug or id.'],
                'author' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Author username.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                'sort' => ['type' => 'string', 'enum' => ['newest', 'oldest', 'created', 'relevance'], 'default' => 'newest'],
            ]),
            function (array $args, McpRequestContext $context): array {
                $data = resolve(FlarumDataService::class);

                return $data->searchDiscussions(
                    $context->actor,
                    array_filter([
                        'q' => $args['query'] ?? null,
                        'tag' => $args['tag'] ?? null,
                        'author' => $args['author'] ?? null,
                    ], fn ($value): bool => $value !== null && $value !== ''),
                    (int) ($args['limit'] ?? 10),
                    (int) ($args['offset'] ?? 0),
                    (string) ($args['sort'] ?? 'newest')
                );
            }
        );
    }

    private static function getDiscussion(): ToolDefinition
    {
        return self::tool(
            'get_discussion',
            'discussions.read',
            self::schema([
                'id' => ['type' => 'integer', 'minimum' => 1],
                'includePosts' => ['type' => 'boolean', 'default' => true],
                'postLimit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'postOffset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                'postSort' => ['type' => 'string', 'enum' => ['oldest', 'newest'], 'default' => 'oldest'],
            ], ['id']),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->getDiscussion(
                $context->actor,
                (int) $args['id'],
                (bool) ($args['includePosts'] ?? true)
                    && resolve(\CengizKemec\FlarumMcp\Permission\PermissionService::class)->allows($context->connection, 'posts.read'),
                (int) ($args['postLimit'] ?? 20),
                (int) ($args['postOffset'] ?? 0),
                (string) ($args['postSort'] ?? 'oldest')
            )
        );
    }

    private static function createDiscussion(): ToolDefinition
    {
        return self::tool(
            'create_discussion',
            'discussions.create',
            self::schema([
                'title' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 80],
                'content' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 63000],
                'tags' => [
                    'type' => 'array',
                    'maxItems' => 20,
                    'items' => ['type' => 'string', 'maxLength' => 100],
                    'description' => 'Tag slugs or ids. Required when the forum requires tags.',
                ],
            ], ['title', 'content']),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->createDiscussion(
                $context->actor,
                $context->request,
                (string) $args['title'],
                (string) $args['content'],
                is_array($args['tags'] ?? null) ? $args['tags'] : []
            )
        );
    }

    private static function editDiscussion(): ToolDefinition
    {
        return self::tool(
            'edit_discussion',
            'discussions.edit',
            self::schema([
                'id' => ['type' => 'integer', 'minimum' => 1],
                'title' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 80],
                'isHidden' => ['type' => 'boolean'],
            ], ['id']),
            function (array $args, McpRequestContext $context): array {
                $title = isset($args['title']) ? (string) $args['title'] : null;
                $isHidden = array_key_exists('isHidden', $args) ? (bool) $args['isHidden'] : null;

                if ($title === null && $isHidden === null) {
                    throw new ValidationException(['attributes' => 'Provide title and/or isHidden.']);
                }

                return resolve(FlarumDataService::class)->updateDiscussion(
                    $context->actor,
                    $context->request,
                    (int) $args['id'],
                    $title,
                    $isHidden
                );
            }
        );
    }

    private static function deleteDiscussion(): ToolDefinition
    {
        return self::tool(
            'delete_discussion',
            'discussions.delete',
            self::schema(['id' => ['type' => 'integer', 'minimum' => 1]], ['id']),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->deleteDiscussion(
                $context->actor,
                $context->request,
                (int) $args['id']
            ),
            true
        );
    }

    private static function listRecentDiscussions(): ToolDefinition
    {
        return self::tool(
            'list_recent_discussions',
            'discussions.search',
            self::schema([
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
            ]),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->searchDiscussions(
                $context->actor,
                [],
                (int) ($args['limit'] ?? 10),
                (int) ($args['offset'] ?? 0),
                'newest'
            )
        );
    }

    private static function getPosts(): ToolDefinition
    {
        return self::tool(
            'get_posts',
            'posts.read',
            self::schema([
                'discussionId' => ['type' => 'integer', 'minimum' => 1],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                'sort' => ['type' => 'string', 'enum' => ['oldest', 'newest'], 'default' => 'oldest'],
            ], ['discussionId']),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->getPosts(
                $context->actor,
                (int) $args['discussionId'],
                (int) ($args['limit'] ?? 20),
                (int) ($args['offset'] ?? 0),
                (string) ($args['sort'] ?? 'oldest')
            )
        );
    }

    private static function getPost(): ToolDefinition
    {
        return self::tool(
            'get_post',
            'posts.read',
            self::schema(['id' => ['type' => 'integer', 'minimum' => 1]], ['id']),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->getPost(
                $context->actor,
                (int) $args['id']
            )
        );
    }

    private static function createPost(): ToolDefinition
    {
        return self::tool(
            'create_post',
            'posts.create',
            self::schema([
                'discussionId' => ['type' => 'integer', 'minimum' => 1],
                'content' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 63000],
            ], ['discussionId', 'content']),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->createPost(
                $context->actor,
                $context->request,
                (int) $args['discussionId'],
                (string) $args['content']
            )
        );
    }

    private static function editPost(): ToolDefinition
    {
        return self::tool(
            'edit_post',
            'posts.edit',
            self::schema([
                'id' => ['type' => 'integer', 'minimum' => 1],
                'content' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 63000],
                'isHidden' => ['type' => 'boolean'],
            ], ['id']),
            function (array $args, McpRequestContext $context): array {
                $content = isset($args['content']) ? (string) $args['content'] : null;
                $isHidden = array_key_exists('isHidden', $args) ? (bool) $args['isHidden'] : null;

                if ($content === null && $isHidden === null) {
                    throw new ValidationException(['attributes' => 'Provide content and/or isHidden.']);
                }

                return resolve(FlarumDataService::class)->updatePost(
                    $context->actor,
                    $context->request,
                    (int) $args['id'],
                    $content,
                    $isHidden
                );
            }
        );
    }

    private static function deletePost(): ToolDefinition
    {
        return self::tool(
            'delete_post',
            'posts.delete',
            self::schema(['id' => ['type' => 'integer', 'minimum' => 1]], ['id']),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->deletePost(
                $context->actor,
                $context->request,
                (int) $args['id']
            ),
            true
        );
    }

    private static function hideDiscussion(): ToolDefinition
    {
        return self::tool(
            'hide_discussion',
            'discussions.hide',
            self::schema(['id' => ['type' => 'integer', 'minimum' => 1]], ['id']),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->updateDiscussion(
                $context->actor,
                $context->request,
                (int) $args['id'],
                null,
                true
            ),
            true
        );
    }

    private static function restoreDiscussion(): ToolDefinition
    {
        return self::tool(
            'restore_discussion',
            'discussions.restore',
            self::schema(['id' => ['type' => 'integer', 'minimum' => 1]], ['id']),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->updateDiscussion(
                $context->actor,
                $context->request,
                (int) $args['id'],
                null,
                false
            ),
            true
        );
    }

    private static function hidePost(): ToolDefinition
    {
        return self::tool(
            'hide_post',
            'posts.hide',
            self::schema(['id' => ['type' => 'integer', 'minimum' => 1]], ['id']),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->updatePost(
                $context->actor,
                $context->request,
                (int) $args['id'],
                null,
                true
            ),
            true
        );
    }

    private static function restorePost(): ToolDefinition
    {
        return self::tool(
            'restore_post',
            'posts.restore',
            self::schema(['id' => ['type' => 'integer', 'minimum' => 1]], ['id']),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->updatePost(
                $context->actor,
                $context->request,
                (int) $args['id'],
                null,
                false
            ),
            true
        );
    }

    private static function lockDiscussion(): ToolDefinition
    {
        return self::tool(
            'lock_discussion',
            'discussions.lock',
            self::schema(['id' => ['type' => 'integer', 'minimum' => 1]], ['id']),
            function (array $args, McpRequestContext $context): array {
                $discussion = self::visibleDiscussion($context, (int) $args['id']);
                $context->actor->assertCan('lock', $discussion);

                return resolve(FlarumDataService::class)->updateDiscussionAttributes(
                    $context->actor,
                    $context->request,
                    $discussion->id,
                    ['isLocked' => true]
                );
            },
            true,
            'flarum-lock'
        );
    }

    private static function unlockDiscussion(): ToolDefinition
    {
        return self::tool(
            'unlock_discussion',
            'discussions.unlock',
            self::schema(['id' => ['type' => 'integer', 'minimum' => 1]], ['id']),
            function (array $args, McpRequestContext $context): array {
                $discussion = self::visibleDiscussion($context, (int) $args['id']);
                $context->actor->assertCan('lock', $discussion);

                return resolve(FlarumDataService::class)->updateDiscussionAttributes(
                    $context->actor,
                    $context->request,
                    $discussion->id,
                    ['isLocked' => false]
                );
            },
            true,
            'flarum-lock'
        );
    }

    private static function stickyDiscussion(): ToolDefinition
    {
        return self::tool(
            'sticky_discussion',
            'discussions.pin',
            self::schema(['id' => ['type' => 'integer', 'minimum' => 1]], ['id']),
            function (array $args, McpRequestContext $context): array {
                $discussion = self::visibleDiscussion($context, (int) $args['id']);
                $context->actor->assertCan('sticky', $discussion);

                return resolve(FlarumDataService::class)->updateDiscussionAttributes(
                    $context->actor,
                    $context->request,
                    $discussion->id,
                    ['isSticky' => true]
                );
            },
            true,
            'flarum-sticky'
        );
    }

    private static function unstickyDiscussion(): ToolDefinition
    {
        return self::tool(
            'unsticky_discussion',
            'discussions.unpin',
            self::schema(['id' => ['type' => 'integer', 'minimum' => 1]], ['id']),
            function (array $args, McpRequestContext $context): array {
                $discussion = self::visibleDiscussion($context, (int) $args['id']);
                $context->actor->assertCan('sticky', $discussion);

                return resolve(FlarumDataService::class)->updateDiscussionAttributes(
                    $context->actor,
                    $context->request,
                    $discussion->id,
                    ['isSticky' => false]
                );
            },
            true,
            'flarum-sticky'
        );
    }

    private static function searchUsers(): ToolDefinition
    {
        return self::tool(
            'search_users',
            'users.search',
            self::schema([
                'query' => ['type' => 'string', 'maxLength' => 150, 'default' => ''],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
            ]),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->searchUsers(
                $context->actor,
                (string) ($args['query'] ?? ''),
                (int) ($args['limit'] ?? 10),
                (int) ($args['offset'] ?? 0)
            )
        );
    }

    private static function getUser(): ToolDefinition
    {
        return self::tool(
            'get_user',
            'users.read',
            self::schema([
                'id' => ['type' => 'integer', 'minimum' => 1],
                'username' => ['type' => 'string', 'maxLength' => 100],
            ]),
            function (array $args, McpRequestContext $context): array {
                $id = isset($args['id']) ? (int) $args['id'] : null;
                $username = isset($args['username']) ? (string) $args['username'] : null;

                if ($id === null && ($username === null || $username === '')) {
                    throw new ValidationException(['user' => 'Provide an id or username.']);
                }

                return resolve(FlarumDataService::class)->getUser($context->actor, $id, $username);
            }
        );
    }

    private static function getCurrentUser(): ToolDefinition
    {
        return self::tool(
            'get_current_user',
            'users.read',
            self::schema([]),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->currentUser($context->actor)
        );
    }

    private static function listTags(): ToolDefinition
    {
        return self::tool(
            'list_tags',
            'tags.list',
            self::schema([]),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->listTags($context->actor),
            false,
            'flarum-tags'
        );
    }

    private static function getTag(): ToolDefinition
    {
        return self::tool(
            'get_tag',
            'tags.read',
            self::schema([
                'id' => ['type' => 'integer', 'minimum' => 1],
                'slug' => ['type' => 'string', 'maxLength' => 100],
            ]),
            function (array $args, McpRequestContext $context): array {
                $id = isset($args['id']) ? (int) $args['id'] : null;
                $slug = isset($args['slug']) ? (string) $args['slug'] : null;

                if ($id === null && ($slug === null || $slug === '')) {
                    throw new ValidationException(['tag' => 'Provide an id or slug.']);
                }

                return resolve(FlarumDataService::class)->getTag($context->actor, $id, $slug);
            },
            false,
            'flarum-tags'
        );
    }

    private static function getNotifications(): ToolDefinition
    {
        return self::tool(
            'get_notifications',
            'notifications.read',
            self::schema([
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                'unreadOnly' => ['type' => 'boolean', 'default' => false],
            ]),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->notifications(
                $context->actor,
                (int) ($args['limit'] ?? 20),
                (int) ($args['offset'] ?? 0),
                (bool) ($args['unreadOnly'] ?? false)
            )
        );
    }

    private static function markNotificationRead(): ToolDefinition
    {
        return self::tool(
            'mark_notification_read',
            'notifications.update',
            self::schema(['id' => ['type' => 'integer', 'minimum' => 1]], ['id']),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->markNotificationRead(
                $context->actor,
                (int) $args['id']
            )
        );
    }

    private static function getForumInfo(): ToolDefinition
    {
        return self::tool(
            'get_forum_info',
            'system.info.read',
            self::schema([]),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->forumInfo($context->actor)
        );
    }

    private static function getForumStatistics(): ToolDefinition
    {
        return self::tool(
            'get_forum_statistics',
            'system.statistics.read',
            self::schema([]),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->forumStatistics($context->actor)
        );
    }

    private static function getRecentActivity(): ToolDefinition
    {
        return self::tool(
            'get_recent_activity',
            'posts.read',
            self::schema([
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 15],
            ]),
            fn (array $args, McpRequestContext $context): array => resolve(FlarumDataService::class)->recentActivity(
                $context->actor,
                (int) ($args['limit'] ?? 15)
            )
        );
    }

    private static function getSystemSettings(): ToolDefinition
    {
        return self::tool(
            'get_system_settings',
            'system.settings.read',
            self::schema([]),
            function (array $args, McpRequestContext $context): array {
                $context->actor->assertAdmin();

                return resolve(FlarumDataService::class)->getSystemSettings($context->actor);
            }
        );
    }

    private static function setSystemSetting(): ToolDefinition
    {
        return self::tool(
            'set_system_setting',
            'system.settings.edit',
            self::schema([
                'key' => [
                    'type' => 'string',
                    'enum' => [
                        'forum_title',
                        'forum_description',
                        'welcome_title',
                        'default_locale',
                        'theme_primary_color',
                        'theme_secondary_color',
                        'allow_sign_up',
                    ],
                ],
                'value' => ['type' => ['string', 'boolean']],
            ], ['key', 'value']),
            function (array $args, McpRequestContext $context): array {
                $context->actor->assertAdmin();

                return resolve(FlarumDataService::class)->setSystemSetting(
                    $context->actor,
                    (string) $args['key'],
                    $args['value']
                );
            },
            true
        );
    }

    private static function listSkills(): ToolDefinition
    {
        return self::tool(
            'list_skills',
            'skills.read',
            self::schema([]),
            fn (array $args, McpRequestContext $context): array => resolve(SkillManager::class)->listForContext($context)
        );
    }

    private static function getSkill(): ToolDefinition
    {
        return self::tool(
            'get_skill',
            'skills.read',
            self::schema(['slug' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120]], ['slug']),
            function (array $args, McpRequestContext $context): array {
                $skill = resolve(SkillManager::class)->findForContext($context, (string) $args['slug']);

                if ($skill === null) {
                    throw new McpSkillException('Skill not found.');
                }

                return $skill;
            }
        );
    }

    private static function getAuditLogs(): ToolDefinition
    {
        return self::tool(
            'get_audit_logs',
            'audit.read',
            self::schema([
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                'connectionId' => ['type' => 'integer', 'minimum' => 1],
                'toolName' => ['type' => 'string', 'maxLength' => 120],
                'status' => ['type' => 'string', 'maxLength' => 20],
            ]),
            function (array $args, McpRequestContext $context): array {
                $query = McpAuditLog::query()->orderByDesc('id');

                if (! $context->actor->isAdmin()) {
                    $query->where('connection_id', $context->connection->id);
                } elseif (isset($args['connectionId'])) {
                    $query->where('connection_id', (int) $args['connectionId']);
                }

                if (isset($args['toolName'])) {
                    $query->where('tool_name', (string) $args['toolName']);
                }

                if (isset($args['status'])) {
                    $query->where('status', (string) $args['status']);
                }

                $total = (clone $query)->count();
                $logs = $query->skip((int) ($args['offset'] ?? 0))->take((int) ($args['limit'] ?? 25))->get();

                return [
                    'logs' => $logs->map(fn (McpAuditLog $log): array => $log->toAdminArray())->all(),
                    'total' => $total,
                ];
            }
        );
    }

    private static function getPendingApprovals(): ToolDefinition
    {
        return self::tool(
            'get_pending_approvals',
            'approvals.read',
            self::schema([
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
            ]),
            function (array $args, McpRequestContext $context): array {
                $query = McpApproval::query()
                    ->where('status', 'pending')
                    ->where('expires_at', '>', Carbon::now())
                    ->orderByDesc('id');

                if (! $context->actor->isAdmin()) {
                    $query->where('connection_id', $context->connection->id);
                }

                $total = (clone $query)->count();
                $approvals = $query->skip((int) ($args['offset'] ?? 0))->take((int) ($args['limit'] ?? 20))->get();

                return [
                    'approvals' => $approvals->map(fn (McpApproval $approval): array => $approval->toAdminArray())->all(),
                    'total' => $total,
                ];
            }
        );
    }

    private static function visibleDiscussion(McpRequestContext $context, int $id): Discussion
    {
        /** @var Discussion $discussion */
        $discussion = Discussion::query()->whereVisibleTo($context->actor)->findOrFail($id);

        return $discussion;
    }
}
