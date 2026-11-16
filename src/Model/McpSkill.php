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
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string $version
 * @property string $source_type
 * @property string|null $source_url
 * @property string $content
 * @property string $checksum
 * @property bool $enabled
 * @property bool $is_builtin
 * @property string|null $required_permission
 * @property array|null $metadata
 * @property int|null $installed_by_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class McpSkill extends AbstractModel
{
    protected $table = 'mcp_skills';

    protected $guarded = [];

    public $timestamps = true;

    protected $casts = [
        'enabled' => 'boolean',
        'is_builtin' => 'boolean',
        'metadata' => 'array',
        'installed_by_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function toAdminArray(bool $includeContent = false): array
    {
        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'sourceType' => $this->source_type,
            'sourceUrl' => $this->source_url,
            'checksum' => $this->checksum,
            'enabled' => $this->enabled,
            'isBuiltin' => $this->is_builtin,
            'requiredPermission' => $this->required_permission,
            'metadata' => $this->metadata,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];

        if ($includeContent) {
            $data['content'] = $this->content;
        }

        return $data;
    }
}
