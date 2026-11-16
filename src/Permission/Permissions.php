<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Permission;

/**
 * Central catalogue of every MCP permission identifier.
 *
 * Permission identifiers are stable API. Third-party extensions may add their
 * own identifiers through the Mcp extender; they are validated separately.
 */
final class Permissions
{
    public const GROUP_FORUM = 'forum';
    public const GROUP_DISCUSSIONS = 'discussions';
    public const GROUP_POSTS = 'posts';
    public const GROUP_USERS = 'users';
    public const GROUP_TAGS = 'tags';
    public const GROUP_NOTIFICATIONS = 'notifications';
    public const GROUP_MODERATION = 'moderation';
    public const GROUP_SYSTEM = 'system';
    public const GROUP_SKILLS = 'skills';
    public const GROUP_PROMPTS = 'prompts';
    public const GROUP_AUDIT = 'audit';
    public const GROUP_INTEGRATIONS = 'integrations';

    public const PROFILE_MINIMUM = 'minimum';
    public const PROFILE_STANDARD = 'standard';
    public const PROFILE_ADVANCED = 'advanced';
    public const PROFILE_FULL = 'full';

    /**
     * @return array<string, array{group: string, risk: string, admin_only?: bool, description?: string}>
     */
    public static function definitions(): array
    {
        return [
            'system.info.read' => ['group' => self::GROUP_SYSTEM, 'risk' => 'low'],
            'system.statistics.read' => ['group' => self::GROUP_SYSTEM, 'risk' => 'low'],
            'system.settings.read' => ['group' => self::GROUP_SYSTEM, 'risk' => 'low'],
            'system.settings.edit' => ['group' => self::GROUP_SYSTEM, 'risk' => 'high'],
            'system.extensions.read' => ['group' => self::GROUP_SYSTEM, 'risk' => 'low'],
            'system.extensions.manage' => ['group' => self::GROUP_SYSTEM, 'risk' => 'high'],

            'discussions.search' => ['group' => self::GROUP_DISCUSSIONS, 'risk' => 'low'],
            'discussions.read' => ['group' => self::GROUP_DISCUSSIONS, 'risk' => 'low'],
            'discussions.create' => ['group' => self::GROUP_DISCUSSIONS, 'risk' => 'medium'],
            'discussions.edit' => ['group' => self::GROUP_DISCUSSIONS, 'risk' => 'medium'],
            'discussions.delete' => ['group' => self::GROUP_DISCUSSIONS, 'risk' => 'high'],
            'discussions.hide' => ['group' => self::GROUP_MODERATION, 'risk' => 'high'],
            'discussions.restore' => ['group' => self::GROUP_MODERATION, 'risk' => 'high'],
            'discussions.lock' => ['group' => self::GROUP_MODERATION, 'risk' => 'high'],
            'discussions.unlock' => ['group' => self::GROUP_MODERATION, 'risk' => 'high'],
            'discussions.pin' => ['group' => self::GROUP_MODERATION, 'risk' => 'high'],
            'discussions.unpin' => ['group' => self::GROUP_MODERATION, 'risk' => 'high'],

            'posts.read' => ['group' => self::GROUP_POSTS, 'risk' => 'low'],
            'posts.create' => ['group' => self::GROUP_POSTS, 'risk' => 'medium'],
            'posts.edit' => ['group' => self::GROUP_POSTS, 'risk' => 'medium'],
            'posts.delete' => ['group' => self::GROUP_POSTS, 'risk' => 'high'],
            'posts.hide' => ['group' => self::GROUP_MODERATION, 'risk' => 'high'],
            'posts.restore' => ['group' => self::GROUP_MODERATION, 'risk' => 'high'],

            'users.search' => ['group' => self::GROUP_USERS, 'risk' => 'low'],
            'users.read' => ['group' => self::GROUP_USERS, 'risk' => 'low'],
            'users.edit' => ['group' => self::GROUP_USERS, 'risk' => 'high'],
            'users.suspend' => ['group' => self::GROUP_USERS, 'risk' => 'high'],

            'tags.list' => ['group' => self::GROUP_TAGS, 'risk' => 'low'],
            'tags.read' => ['group' => self::GROUP_TAGS, 'risk' => 'low'],
            'tags.manage' => ['group' => self::GROUP_TAGS, 'risk' => 'high'],

            'notifications.read' => ['group' => self::GROUP_NOTIFICATIONS, 'risk' => 'low'],
            'notifications.update' => ['group' => self::GROUP_NOTIFICATIONS, 'risk' => 'low'],

            'skills.read' => ['group' => self::GROUP_SKILLS, 'risk' => 'low'],
            'skills.install' => ['group' => self::GROUP_SKILLS, 'risk' => 'medium'],
            'skills.manage' => ['group' => self::GROUP_SKILLS, 'risk' => 'high'],

            'prompts.read' => ['group' => self::GROUP_PROMPTS, 'risk' => 'low'],
            'prompts.manage' => ['group' => self::GROUP_PROMPTS, 'risk' => 'high'],

            'audit.read' => ['group' => self::GROUP_AUDIT, 'risk' => 'medium'],
            'approvals.read' => ['group' => self::GROUP_INTEGRATIONS, 'risk' => 'low'],
            'approvals.manage' => ['group' => self::GROUP_INTEGRATIONS, 'risk' => 'high', 'admin_only' => true],
            'integrations.read' => ['group' => self::GROUP_INTEGRATIONS, 'risk' => 'low'],
            'integrations.manage' => ['group' => self::GROUP_INTEGRATIONS, 'risk' => 'high', 'admin_only' => true],
            'tools.manage' => ['group' => self::GROUP_INTEGRATIONS, 'risk' => 'high', 'admin_only' => true],
        ];
    }

    /**
     * @return string[]
     */
    public static function ids(): array
    {
        return array_keys(self::definitions());
    }

    public static function exists(string $permission): bool
    {
        return isset(self::definitions()[$permission]);
    }

    public static function group(string $permission): string
    {
        return self::definitions()[$permission]['group'] ?? self::GROUP_INTEGRATIONS;
    }

    public static function risk(string $permission): string
    {
        return self::definitions()[$permission]['risk'] ?? 'high';
    }

    public static function requiresApproval(string $permission): bool
    {
        return self::risk($permission) === 'high';
    }

    public static function isAdminOnly(string $permission): bool
    {
        return (bool) (self::definitions()[$permission]['admin_only'] ?? false);
    }

    public static function labelKey(string $permission): string
    {
        return 'cengizkemec-mcp.admin.permissions.'.str_replace('.', '_', $permission);
    }

    public static function groupLabelKey(string $group): string
    {
        return 'cengizkemec-mcp.admin.permission_groups.'.$group;
    }

    /**
     * Built-in profiles are immutable definitions shipped with the extension.
     * They are never stored in the database and can never be overwritten.
     *
     * @return array<string, array{name_key: string, description_key: string, permissions: string[]}>
     */
    public static function builtInProfiles(): array
    {
        $minimum = [
            'system.info.read',
            'system.statistics.read',
            'discussions.search',
            'discussions.read',
            'posts.read',
            'users.search',
            'users.read',
            'tags.list',
            'tags.read',
            'notifications.read',
            'skills.read',
            'prompts.read',
        ];

        $standard = array_values(array_unique(array_merge($minimum, [
            'discussions.create',
            'discussions.edit',
            'posts.create',
            'posts.edit',
            'notifications.update',
        ])));

        $advanced = array_values(array_unique(array_merge($standard, [
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
            'tags.manage',
            'approvals.read',
        ])));

        $full = array_values(array_filter(
            self::ids(),
            fn (string $id): bool => ! self::isAdminOnly($id)
        ));

        return [
            self::PROFILE_MINIMUM => [
                'name_key' => 'cengizkemec-mcp.admin.profiles.minimum_title',
                'description_key' => 'cengizkemec-mcp.admin.profiles.minimum_description',
                'permissions' => $minimum,
            ],
            self::PROFILE_STANDARD => [
                'name_key' => 'cengizkemec-mcp.admin.profiles.standard_title',
                'description_key' => 'cengizkemec-mcp.admin.profiles.standard_description',
                'permissions' => $standard,
            ],
            self::PROFILE_ADVANCED => [
                'name_key' => 'cengizkemec-mcp.admin.profiles.advanced_title',
                'description_key' => 'cengizkemec-mcp.admin.profiles.advanced_description',
                'permissions' => $advanced,
            ],
            self::PROFILE_FULL => [
                'name_key' => 'cengizkemec-mcp.admin.profiles.full_title',
                'description_key' => 'cengizkemec-mcp.admin.profiles.full_description',
                'permissions' => $full,
            ],
        ];
    }

    /**
     * @return string[]
     */
    public static function builtInProfileKeys(): array
    {
        return array_keys(self::builtInProfiles());
    }
}
