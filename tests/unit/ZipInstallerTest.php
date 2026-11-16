<?php

namespace CengizKemec\FlarumMcp\Tests\unit;

use CengizKemec\FlarumMcp\Exception\McpSkillException;
use CengizKemec\FlarumMcp\Skill\ZipInstaller;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class ZipInstallerTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_reads_skill_md_from_the_root(): void
    {
        $path = $this->makeZip(['SKILL.md' => "# Skill\n\nDescription."]);

        $result = (new ZipInstaller())->extractSkill($path);

        $this->assertSame('SKILL.md', $result['filename']);
        $this->assertStringContainsString('# Skill', $result['content']);
    }

    #[Test]
    public function it_reads_skill_md_from_one_top_level_directory(): void
    {
        $path = $this->makeZip(['my-skill/SKILL.md' => "# Skill\n\nDescription."]);

        $result = (new ZipInstaller())->extractSkill($path);

        $this->assertSame('my-skill/SKILL.md', $result['filename']);
    }

    #[Test]
    public function it_rejects_path_traversal(): void
    {
        $path = $this->makeZip(['../evil.md' => '# Evil']);

        $this->expectException(McpSkillException::class);

        (new ZipInstaller())->extractSkill($path);
    }

    #[Test]
    public function it_rejects_an_archive_without_skill_md(): void
    {
        $path = $this->makeZip(['readme.md' => '# Not a skill']);

        $this->expectException(McpSkillException::class);

        (new ZipInstaller())->extractSkill($path);
    }

    #[Test]
    public function it_rejects_oversized_entries(): void
    {
        $path = $this->makeZip(['SKILL.md' => str_repeat('a', ZipInstaller::MAX_ENTRY_BYTES + 1)]);

        $this->expectException(McpSkillException::class);

        (new ZipInstaller())->extractSkill($path);
    }

    private function makeZip(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mcp-zip-test-');
        $this->files[] = $path;

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));

        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return $path;
    }
}
