<?php

namespace CengizKemec\FlarumMcp\Tests\unit;

use CengizKemec\FlarumMcp\Exception\McpSkillException;
use CengizKemec\FlarumMcp\Skill\SkillParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SkillParserTest extends TestCase
{
    private SkillParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SkillParser();
    }

    #[Test]
    public function it_parses_a_structured_skill(): void
    {
        $skill = $this->parser->parse(<<<'MD'
---
name: Example Skill
description: A useful example.
version: 2.1.0
license: MIT
tags: [flarum, example]
---
# Example Skill

Use this when testing.

```php
<?php echo 'allowed inside a code fence';
```
MD);

        $this->assertSame('example-skill', $skill->slug);
        $this->assertSame('Example Skill', $skill->name);
        $this->assertSame('2.1.0', $skill->version);
        $this->assertSame('MIT', $skill->license);
        $this->assertSame(['flarum', 'example'], $skill->tags);
        $this->assertSame(hash('sha256', $skill->content), $skill->checksum);
    }

    #[Test]
    public function it_derives_a_name_and_description_without_front_matter(): void
    {
        $skill = $this->parser->parse("# My Skill\n\nThis is a short description.");

        $this->assertSame('my-skill', $skill->slug);
        $this->assertSame('My Skill', $skill->name);
        $this->assertSame('This is a short description.', $skill->description);
    }

    #[Test]
    public function it_strips_a_utf8_byte_order_mark_before_parsing_front_matter(): void
    {
        $skill = $this->parser->parse("\xEF\xBB\xBF".<<<'MD'
---
name: BOM Skill
description: Front matter still parses.
version: 1.2.3
---
# BOM Skill

Body text.
MD);

        $this->assertSame('BOM Skill', $skill->name);
        $this->assertSame('Front matter still parses.', $skill->description);
        $this->assertSame('1.2.3', $skill->version);
        $this->assertStringStartsWith("---\n", $skill->content);
    }

    #[Test]
    public function it_rejects_executable_html(): void
    {
        $this->expectException(McpSkillException::class);

        $this->parser->parse("# Unsafe\n\n<script>alert(1)</script>");
    }

    #[Test]
    public function it_rejects_javascript_links(): void
    {
        $this->expectException(McpSkillException::class);

        $this->parser->parse("# Unsafe\n\n[click](javascript:alert(1))");
    }

    #[Test]
    public function it_rejects_local_file_references(): void
    {
        $this->expectException(McpSkillException::class);

        $this->parser->parse("# Unsafe\n\n[secret](../config.php)");
    }

    #[Test]
    public function it_rejects_binary_content(): void
    {
        $this->expectException(McpSkillException::class);

        $this->parser->parse("# Binary\n\nabc\0def");
    }

    #[Test]
    public function it_rejects_oversized_content(): void
    {
        $this->expectException(McpSkillException::class);

        $this->parser->parse("# Huge\n\n".str_repeat('a', SkillParser::MAX_BYTES + 1));
    }

    #[Test]
    public function it_requires_a_heading(): void
    {
        $this->expectException(McpSkillException::class);

        $this->parser->parse('Just text without a heading.');
    }
}
