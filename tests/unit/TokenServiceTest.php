<?php

namespace CengizKemec\FlarumMcp\Tests\unit;

use CengizKemec\FlarumMcp\Mcp\TokenService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TokenServiceTest extends TestCase
{
    #[Test]
    public function tokens_are_random_and_hashable(): void
    {
        $service = new TokenService();

        $first = $service->generate();
        $second = $service->generate();

        $this->assertNotSame($first['plain'], $second['plain']);
        $this->assertStringStartsWith('fmcp_', $first['plain']);
        $this->assertSame(69, strlen($first['plain']));
        $this->assertSame(hash('sha256', $first['plain']), $first['hash']);
        $this->assertSame(substr($first['plain'], 0, 12), $first['prefix']);
        $this->assertSame(64, strlen($first['hash']));
    }
}
