<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $user_id
 * @property string $permission_profile
 * @property array|null $custom_permissions
 * @property string $token_hash
 * @property string $token_prefix
 * @property \Carbon\Carbon|null $token_expires_at
 * @property \Carbon\Carbon|null $token_revoked_at
 * @property string $status
 * @property int $rate_limit_per_minute
 * @property int $rate_limit_per_hour
 * @property int $rate_limit_per_day
 * @property bool $require_approval
 * @property \Carbon\Carbon|null $last_used_at
 * @property string|null $last_used_ip
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read User|null $user
 */
class McpConnection extends AbstractModel
{
    protected $table = 'mcp_connections';

    protected $guarded = [];

    public $timestamps = true;

    protected $casts = [
        'user_id' => 'integer',
        'custom_permissions' => 'array',
        'rate_limit_per_minute' => 'integer',
        'rate_limit_per_hour' => 'integer',
        'rate_limit_per_day' => 'integer',
        'require_approval' => 'boolean',
        'token_expires_at' => 'datetime',
        'token_revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->token_revoked_at === null;
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->isActive() && ! $this->isExpired() && $this->user !== null;
    }

    /**
     * A safe representation for the admin frontend. Never includes the hash.
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'userId' => $this->user_id,
            'username' => $this->user?->username,
            'permissionProfile' => $this->permission_profile,
            'customPermissions' => $this->custom_permissions,
            'tokenPrefix' => $this->token_prefix,
            'tokenExpiresAt' => $this->token_expires_at?->toIso8601String(),
            'tokenRevokedAt' => $this->token_revoked_at?->toIso8601String(),
            'status' => $this->status,
            'rateLimitPerMinute' => $this->rate_limit_per_minute,
            'rateLimitPerHour' => $this->rate_limit_per_hour,
            'rateLimitPerDay' => $this->rate_limit_per_day,
            'requireApproval' => $this->require_approval,
            'lastUsedAt' => $this->last_used_at?->toIso8601String(),
            'lastUsedIp' => $this->last_used_ip,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
