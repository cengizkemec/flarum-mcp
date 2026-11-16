<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Skill;

final class ParsedSkill
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description,
        public readonly string $version,
        public readonly ?string $license,
        public readonly array $tags,
        public readonly array $metadata,
        public readonly string $body,
        public readonly string $content,
        public readonly string $checksum,
    ) {
    }

    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'license' => $this->license,
            'tags' => $this->tags,
            'metadata' => $this->metadata,
            'checksum' => $this->checksum,
        ];
    }
}
