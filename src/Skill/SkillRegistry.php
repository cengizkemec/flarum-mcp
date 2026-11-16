<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Skill;

/**
 * In-memory parity for Skills contributed by other extensions at boot time.
 * These are not database rows; the extension that registered them owns them.
 */
final class SkillRegistry
{
    /** @var array<string, array{skill: ParsedSkill, extension_id: string}> */
    private array $skills = [];

    public function register(ParsedSkill $skill, string $extensionId): void
    {
        if (isset($this->skills[$skill->slug])) {
            throw new \InvalidArgumentException("An MCP Skill with slug {$skill->slug} is already registered.");
        }

        $this->skills[$skill->slug] = [
            'skill' => $skill,
            'extension_id' => $extensionId,
        ];
    }

    /**
     * @return array<string, array{skill: ParsedSkill, extension_id: string}>
     */
    public function all(): array
    {
        return $this->skills;
    }

    public function get(string $slug): ?array
    {
        return $this->skills[$slug] ?? null;
    }
}
