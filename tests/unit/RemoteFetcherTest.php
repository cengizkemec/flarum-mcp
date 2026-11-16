<?php

namespace CengizKemec\FlarumMcp\Tests\unit;

use CengizKemec\FlarumMcp\Exception\McpSkillException;
use CengizKemec\FlarumMcp\Skill\RemoteFetcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RemoteFetcherTest extends TestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('curl')) {
            $this->markTestSkipped('The cURL extension is required to test RemoteFetcher.');
        }
    }

    #[Test]
    #[DataProvider('unsafeUrls')]
    public function unsafe_urls_are_rejected_before_any_request(string $url, string $expectedCode): void
    {
        try {
            (new RemoteFetcher())->fetch($url);
            $this->fail('The URL should have been rejected.');
        } catch (McpSkillException $exception) {
            $this->assertSame($expectedCode, $exception->errorCode, $exception->getMessage());
        }
    }

    public static function unsafeUrls(): array
    {
        return [
            'plain http' => ['http://example.com/SKILL.md', 'invalid_url'],
            'credentials' => ['https://user:secret@example.com/SKILL.md', 'invalid_url'],
            'non-standard port' => ['https://example.com:8443/SKILL.md', 'invalid_url'],
            'ipv4 loopback' => ['https://127.0.0.1/SKILL.md', 'ssrf_blocked'],
            'ipv4 private' => ['https://10.0.0.5/SKILL.md', 'ssrf_blocked'],
            'ipv4 link local' => ['https://169.254.169.254/latest/meta-data/', 'ssrf_blocked'],
            'ipv6 loopback' => ['https://[::1]/SKILL.md', 'ssrf_blocked'],
            'localhost name' => ['https://localhost/SKILL.md', 'ssrf_blocked'],
            'control characters' => ["https://example.com/SKILL.md\nInjected: yes", 'invalid_url'],
            'too long' => ['https://example.com/'.str_repeat('a', 2100), 'invalid_url'],
        ];
    }
}
