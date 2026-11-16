<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Skill;

use CengizKemec\FlarumMcp\Exception\McpSkillException;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses and validates SKILL.md documents.
 *
 * Skill content is never executed. Validation focuses on size, encoding,
 * structure, unsafe HTML and file references. Markdown is stored as data and
 * only ever displayed as text by the admin UI.
 */
final class SkillParser
{
    public const MAX_BYTES = 524288; // 512 KiB

    public function parse(string $content, string $sourceType = 'manual', ?string $sourceUrl = null): ParsedSkill
    {
        if (strlen($content) > self::MAX_BYTES) {
            throw new McpSkillException('SKILL.md is larger than the 512 KiB limit.', 'skill_too_large');
        }

        if (str_contains($content, "\0")) {
            throw new McpSkillException('SKILL.md contains binary data.', 'skill_binary');
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Many editors save UTF-8 with a byte-order mark. Treat it as metadata
        // rather than as the first character of the front matter delimiter.
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            throw new McpSkillException('SKILL.md must be valid UTF-8.', 'skill_encoding');
        }

        $this->assertSafeMarkdown($content);

        [$meta, $body] = $this->splitFrontMatter($content);

        if (! preg_match('/^#\s+(.+)$/m', $body, $headingMatch)) {
            throw new McpSkillException('SKILL.md must contain a level-one heading.', 'skill_missing_heading');
        }

        $heading = trim($headingMatch[1]);
        $name = trim((string) ($meta['name'] ?? $heading));

        if ($name === '') {
            throw new McpSkillException('SKILL.md must have a name.', 'skill_missing_name');
        }

        $description = trim((string) ($meta['description'] ?? $this->firstParagraph($body)));

        if ($description === '') {
            throw new McpSkillException('SKILL.md must have a description.', 'skill_missing_description');
        }

        $version = trim((string) ($meta['version'] ?? '1.0.0'));

        if (! preg_match('/^[0-9A-Za-z][0-9A-Za-z._-]{0,39}$/', $version)) {
            throw new McpSkillException('SKILL.md version is invalid.', 'skill_invalid_version');
        }

        $slug = trim((string) ($meta['slug'] ?? Str::slug($name)));

        if ($slug === '') {
            $slug = 'skill-'.substr(hash('sha256', $content), 0, 12);
        }

        $slug = Str::slug($slug);

        if ($slug === '' || strlen($slug) > 120) {
            throw new McpSkillException('SKILL.md slug is invalid.', 'skill_invalid_slug');
        }

        if (mb_strlen($name) > 160) {
            throw new McpSkillException('SKILL.md name is too long.', 'skill_name_too_long');
        }

        if (mb_strlen($description) > 500) {
            $description = mb_substr($description, 0, 497).'...';
        }

        $tags = $meta['tags'] ?? [];

        if (is_string($tags)) {
            $tags = array_values(array_filter(array_map('trim', explode(',', $tags))));
        }

        if (! is_array($tags)) {
            $tags = [];
        }

        $tags = array_values(array_filter(array_map(
            fn ($tag): string => is_scalar($tag) ? mb_substr(trim((string) $tag), 0, 40) : '',
            $tags
        )));

        $metadata = $meta;
        unset($metadata['name'], $metadata['description'], $metadata['version'], $metadata['slug'], $metadata['license'], $metadata['tags']);
        $metadata['source_type'] = $sourceType;

        if ($sourceUrl !== null) {
            $metadata['source_url'] = $sourceUrl;
        }

        return new ParsedSkill(
            slug: $slug,
            name: $name,
            description: $description,
            version: $version,
            license: isset($meta['license']) ? mb_substr((string) $meta['license'], 0, 80) : null,
            tags: $tags,
            metadata: $metadata,
            body: $body,
            content: $content,
            checksum: hash('sha256', $content)
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function splitFrontMatter(string $content): array
    {
        if (! str_starts_with($content, "---\n")) {
            return [[], $content];
        }

        $end = strpos($content, "\n---\n", 4);

        if ($end === false) {
            throw new McpSkillException('SKILL.md front matter is not closed.', 'skill_invalid_front_matter');
        }

        $yaml = substr($content, 4, $end - 4);

        try {
            $meta = Yaml::parse($yaml);
        } catch (ParseException $e) {
            throw new McpSkillException('SKILL.md front matter is not valid YAML.', 'skill_invalid_front_matter');
        }

        if (! is_array($meta)) {
            throw new McpSkillException('SKILL.md front matter must be a mapping.', 'skill_invalid_front_matter');
        }

        return [$meta, substr($content, $end + 5)];
    }

    private function firstParagraph(string $body): string
    {
        $lines = preg_split('/\n{2,}/', trim($body)) ?? [];

        foreach ($lines as $block) {
            $block = trim($block);

            if ($block === '' || str_starts_with($block, '#') || str_starts_with($block, '```')) {
                continue;
            }

            return trim(preg_replace('/\s+/', ' ', strip_tags($block)) ?? '');
        }

        return '';
    }

    private function assertSafeMarkdown(string $content): void
    {
        // Fenced and inline code are documentation, not executable HTML. They
        // are still stored as text and never rendered as markup by this
        // extension. Scan only the prose outside code spans.
        $scan = preg_replace('/```.*?```/s', '', $content) ?? $content;
        $scan = preg_replace('/`[^`\n]*`/', '', $scan) ?? $scan;
        $lower = strtolower($scan);

        foreach (['<script', '<iframe', '<object', '<embed', '<?php', '<form'] as $forbidden) {
            if (str_contains($lower, $forbidden)) {
                throw new McpSkillException('SKILL.md contains unsafe executable HTML or URL content.', 'skill_unsafe_content');
            }
        }

        if (preg_match('/(\]\(\s*|href\s*=\s*["\']?\s*)javascript:/i', $scan)) {
            throw new McpSkillException('SKILL.md contains an unsafe JavaScript URL.', 'skill_unsafe_content');
        }

        // Reject file references that could be mistaken for local paths. They
        // are never resolved by this extension, but an AI client might follow
        // them if they were left in the document.
        if (preg_match('/\]\(\s*(file:|\.\.\/|\/|[a-zA-Z]:\\\\)/i', $scan)) {
            throw new McpSkillException('SKILL.md contains an unsafe local file reference.', 'skill_unsafe_reference');
        }
    }
}
