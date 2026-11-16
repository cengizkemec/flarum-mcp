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
 * @property int $connection_id
 * @property int|null $user_id
 * @property string $tool_name
 * @property string $permission
 * @property array $arguments
 * @property string $status
 * @property string|null $ip_address
 * @property \Carbon\Carbon|null $requested_at
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon|null $decided_at
 * @property int|null $decided_by_id
 * @property string|null $decision_note
 * @property \Carbon\Carbon|null $executed_at
 * @property string|null $error
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read McpConnection $connection
 * @property-read User|null $user
 * @property-read User|null $decidedBy
 */
class McpApproval extends AbstractModel
{
    protected $table = 'mcp_approvals';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_EXECUTED = 'executed';
    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    public $timestamps = true;

    protected $casts = [
        'connection_id' => 'integer',
        'user_id' => 'integer',
        'arguments' => 'array',
        'decided_by_id' => 'integer',
        'requested_at' => 'datetime',
        'expires_at' => 'datetime',
        'decided_at' => 'datetime',
        'executed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function mcpConnection(): BelongsTo
    {
        return $this->belongsTo(McpConnection::class, 'connection_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function toAdminArray(bool $includeArguments = false): array
    {
        $data = [
            'id' => $this->id,
            'connectionId' => $this->connection_id,
            'connectionName' => $this->mcpConnection?->name,
            'userId' => $this->user_id,
            'username' => $this->user?->username,
            'toolName' => $this->tool_name,
            'permission' => $this->permission,
            'status' => $this->status,
            'ipAddress' => $this->ip_address,
            'requestedAt' => $this->requested_at?->toIso8601String(),
            'expiresAt' => $this->expires_at?->toIso8601String(),
            'decidedAt' => $this->decided_at?->toIso8601String(),
            'decidedBy' => $this->decidedBy?->username,
            'decisionNote' => $this->decision_note,
            'executedAt' => $this->executed_at?->toIso8601String(),
            'error' => $this->error,
        ];

        if ($includeArguments) {
            $data['arguments'] = $this->arguments;
        }

        return $data;
    }
}
