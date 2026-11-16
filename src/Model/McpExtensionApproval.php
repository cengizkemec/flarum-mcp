<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Model;

use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property string $extension_id
 * @property string $status
 * @property array|null $permissions
 * @property int|null $approved_by_id
 * @property \Carbon\Carbon|null $decided_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class McpExtensionApproval extends AbstractModel
{
    protected $table = 'mcp_extension_approvals';

    protected $guarded = [];

    public $timestamps = true;

    protected $casts = [
        'permissions' => 'array',
        'approved_by_id' => 'integer',
        'decided_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DISABLED = 'disabled';
}
