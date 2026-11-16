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
 * @property string $name
 * @property string|null $description
 * @property array $permissions
 * @property int|null $created_by_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class McpCustomProfile extends AbstractModel
{
    protected $table = 'mcp_custom_profiles';

    protected $guarded = [];

    public $timestamps = true;

    protected $casts = [
        'permissions' => 'array',
        'created_by_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
