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
 * @property string $resource_uri
 * @property bool $enabled
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class McpResourceState extends AbstractModel
{
    protected $table = 'mcp_resource_states';

    protected $guarded = [];

    public $timestamps = true;

    protected $casts = [
        'enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
