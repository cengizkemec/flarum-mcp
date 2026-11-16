<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Skill;

use CengizKemec\FlarumMcp\Exception\McpSkillException;
use ZipArchive;

/**
 * Reads one SKILL.md out of a ZIP archive without extracting anything to disk.
 * Protects against path traversal, symlinks, duplicate entries, oversized
 * entries and compression-ratio zip bombs.
 */
final class ZipInstaller
{
    public const MAX_ENTRIES = 200;
    public const MAX_TOTAL_BYTES = 5242880; // 5 MiB
    public const MAX_ENTRY_BYTES = 1048576; // 1 MiB

    /**
     * @return array{content: string, filename: string}
     */
    public function extractSkill(string $archivePath): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new McpSkillException('ZIP Skill installation requires the PHP zip extension.', 'zip_missing');
        }

        $zip = new ZipArchive();

        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw new McpSkillException('The uploaded file is not a readable ZIP archive.', 'invalid_zip');
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new McpSkillException('ZIP archive contains too many entries.', 'zip_too_many_entries');
            }

            $total = 0;
            $seen = [];
            $candidates = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);

                if ($stat === false) {
                    continue;
                }

                $name = str_replace('\\', '/', (string) $stat['name']);

                if ($name === '' || str_contains($name, "\0")) {
                    throw new McpSkillException('ZIP archive contains an invalid entry name.', 'zip_unsafe_path');
                }

                if (isset($seen[$name])) {
                    throw new McpSkillException('ZIP archive contains duplicate entries.', 'zip_duplicate_entry');
                }

                $seen[$name] = true;

                if (str_ends_with($name, '/')) {
                    continue;
                }

                if (preg_match('#(^/|(^|/)\.\.(/|$)|^[A-Za-z]:)#', $name)) {
                    throw new McpSkillException('ZIP archive contains a path traversal entry.', 'zip_unsafe_path');
                }

                $opsys = 0;
                $attributes = 0;

                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
                    $mode = ($attributes >> 16) & 0170000;

                    if ($mode === 0120000) {
                        throw new McpSkillException('ZIP archive contains a symbolic link.', 'zip_symlink');
                    }
                }

                $size = (int) $stat['size'];
                $compressed = (int) $stat['comp_size'];

                if ($size > self::MAX_ENTRY_BYTES) {
                    throw new McpSkillException('A ZIP entry exceeds the 1 MiB limit.', 'zip_entry_too_large');
                }

                if ($compressed === 0 && $size > 0) {
                    throw new McpSkillException('ZIP archive has an invalid compression ratio.', 'zip_bomb');
                }

                if ($compressed > 0 && $size > 100000 && ($size / $compressed) > 100) {
                    throw new McpSkillException('ZIP archive has a suspicious compression ratio.', 'zip_bomb');
                }

                $total += $size;

                if ($total > self::MAX_TOTAL_BYTES) {
                    throw new McpSkillException('ZIP archive expands beyond the 5 MiB limit.', 'zip_too_large');
                }

                if (preg_match('/\.md$/i', $name)) {
                    $candidates[] = $name;
                }
            }

            $skillFile = $this->chooseSkillFile($candidates);

            if ($skillFile === null) {
                throw new McpSkillException('ZIP archive must contain a SKILL.md file.', 'zip_no_skill');
            }

            $content = $zip->getFromName($skillFile);

            if ($content === false || $content === '') {
                throw new McpSkillException('Could not read SKILL.md from the ZIP archive.', 'zip_read_failed');
            }

            if (strlen($content) > SkillParser::MAX_BYTES) {
                throw new McpSkillException('SKILL.md exceeds the 512 KiB limit.', 'skill_too_large');
            }

            return ['content' => $content, 'filename' => $skillFile];
        } finally {
            $zip->close();
        }
    }

    /**
     * @param string[] $candidates
     */
    private function chooseSkillFile(array $candidates): ?string
    {
        $root = null;
        $nested = [];

        foreach ($candidates as $candidate) {
            if (strcasecmp($candidate, 'SKILL.md') === 0) {
                $root = $candidate;
            } elseif (preg_match('#^[^/]+/SKILL\.md$#i', $candidate)) {
                $nested[] = $candidate;
            }
        }

        if ($root !== null) {
            return $root;
        }

        if (count($nested) === 1) {
            return $nested[0];
        }

        return null;
    }
}
