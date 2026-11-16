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
 * @property string|null $title
 * @property string|null $description
 * @property string $content
 * @property array|null $arguments
 * @property bool $enabled
 * @property string $required_permission
 * @property string $locale
 * @property int|null $created_by_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class McpPrompt extends AbstractModel
{
    protected $table = 'mcp_prompts';

    protected $guarded = [];

    public $timestamps = true;

    protected $casts = [
        'arguments' => 'array',
        'enabled' => 'boolean',
        'created_by_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->content,
            'arguments' => $this->arguments ?? [],
            'enabled' => $this->enabled,
            'requiredPermission' => $this->required_permission,
            'locale' => $this->locale,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
