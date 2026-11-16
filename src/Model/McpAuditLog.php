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
 * @property int|null $connection_id
 * @property int|null $user_id
 * @property string $operation
 * @property string|null $tool_name
 * @property string|null $permission
 * @property string $permission_decision
 * @property string $status
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int|null $duration_ms
 * @property int|null $approval_id
 * @property string|null $ip_address
 * @property string|null $request_id
 * @property array|null $meta
 * @property \Carbon\Carbon $created_at
 * @property-read User|null $user
 * @property-read McpConnection|null $connection
 */
class McpAuditLog extends AbstractModel
{
    protected $table = 'mcp_audit_logs';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'connection_id' => 'integer',
        'user_id' => 'integer',
        'duration_ms' => 'integer',
        'approval_id' => 'integer',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mcpConnection(): BelongsTo
    {
        return $this->belongsTo(McpConnection::class, 'connection_id');
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'connectionId' => $this->connection_id,
            'connectionName' => $this->mcpConnection?->name,
            'userId' => $this->user_id,
            'username' => $this->user?->username,
            'operation' => $this->operation,
            'toolName' => $this->tool_name,
            'permission' => $this->permission,
            'permissionDecision' => $this->permission_decision,
            'status' => $this->status,
            'errorCode' => $this->error_code,
            'errorMessage' => $this->error_message,
            'durationMs' => $this->duration_ms,
            'approvalId' => $this->approval_id,
            'ipAddress' => $this->ip_address,
            'requestId' => $this->request_id,
            'meta' => $this->meta,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
