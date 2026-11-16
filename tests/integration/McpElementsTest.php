<?php

namespace CengizKemec\FlarumMcp\Tests\integration;

use Carbon\Carbon;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class McpElementsTest extends TestCase
{
    private const MIN_TOKEN = 'fmcp_minminminminminminminminminminminminminminminminminminminminminmin';
    private const FULL_TOKEN = 'fmcp_fullfullfullfullfullfullfullfullfullfullfullfullfullfullfullfullfull';

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('cengizkemec-mcp');

        $this->prepareDatabase([
            'mcp_connections' => [
                [
                    'id' => 20,
                    'name' => 'Minimum reader',
                    'user_id' => 1,
                    'permission_profile' => 'minimum',
                    'token_hash' => hash('sha256', self::MIN_TOKEN),
                    'token_prefix' => 'fmcp_minmin',
                    'status' => 'active',
                    'rate_limit_per_minute' => 1000,
                    'rate_limit_per_hour' => 10000,
                    'rate_limit_per_day' => 100000,
                    'require_approval' => 0,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'id' => 21,
                    'name' => 'Full reader',
                    'user_id' => 1,
                    'permission_profile' => 'full',
                    'token_hash' => hash('sha256', self::FULL_TOKEN),
                    'token_prefix' => 'fmcp_fullfu',
                    'status' => 'active',
                    'rate_limit_per_minute' => 1000,
                    'rate_limit_per_hour' => 10000,
                    'rate_limit_per_day' => 100000,
                    'require_approval' => 0,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
            ],
        ]);
    }

    #[Test]
    public function resources_are_filtered_by_permission(): void
    {
        $session = $this->initialize(self::MIN_TOKEN);

        $list = $this->rpc(self::MIN_TOKEN, $session, 'resources/list', (object) []);
        $uris = array_column($list['resources'], 'uri');

        $this->assertContains('flarum://forum/info', $uris);
        $this->assertContains('flarum://skills', $uris);
        $this->assertNotContains('flarum://forum/configuration', $uris);

        $read = $this->rpc(self::MIN_TOKEN, $session, 'resources/read', ['uri' => 'flarum://forum/info']);
        $this->assertNotEmpty($read['contents']);
        $info = json_decode($read['contents'][0]['text'], true);
        $this->assertArrayHasKey('title', $info);

        $denied = $this->rpcExpectError(self::MIN_TOKEN, $session, 'resources/read', ['uri' => 'flarum://forum/configuration']);
        $this->assertArrayHasKey('error', $denied);
    }

    #[Test]
    public function empty_json_objects_survive_flarum_body_parsing(): void
    {
        $session = $this->initialize(self::FULL_TOKEN);

        $result = $this->rpc(self::FULL_TOKEN, $session, 'tools/call', [
            'name' => 'get_forum_info',
            'arguments' => (object) [],
        ]);

        $this->assertFalse($result['isError'] ?? false);
        $info = json_decode($result['content'][0]['text'], true);
        $this->assertArrayHasKey('title', $info);
    }

    #[Test]
    public function skills_are_exposed_as_resources(): void
    {
        $session = $this->initialize(self::FULL_TOKEN);

        $templates = $this->rpc(self::FULL_TOKEN, $session, 'resources/templates/list', (object) []);
        $templateUris = array_column($templates['resourceTemplates'], 'uriTemplate');
        $this->assertContains('flarum://skills/{slug}', $templateUris);

        $read = $this->rpc(self::FULL_TOKEN, $session, 'resources/read', ['uri' => 'flarum://skills/flarum-core-development']);
        $this->assertSame('text/markdown', $read['contents'][0]['mimeType']);
        $this->assertStringContainsString('Flarum Core Development', $read['contents'][0]['text']);

        $list = $this->rpc(self::FULL_TOKEN, $session, 'resources/read', ['uri' => 'flarum://skills']);
        $skills = json_decode($list['contents'][0]['text'], true);
        $this->assertGreaterThanOrEqual(15, count($skills['skills']));
    }

    #[Test]
    public function prompts_are_discovered_and_generated(): void
    {
        $session = $this->initialize(self::FULL_TOKEN);

        $list = $this->rpc(self::FULL_TOKEN, $session, 'prompts/list', (object) []);
        $names = array_column($list['prompts'], 'name');

        $this->assertContains('summarize_discussion', $names);
        $this->assertContains('improve_discussion_title', $names);
        $this->assertContains('draft_forum_announcement', $names);

        $generated = $this->rpc(self::FULL_TOKEN, $session, 'prompts/get', [
            'name' => 'improve_discussion_title',
            'arguments' => ['title' => 'help me pls'],
        ]);

        $this->assertNotEmpty($generated['messages']);
        $this->assertStringContainsString('help me pls', $generated['messages'][0]['content']['text']);
    }

    private function initialize(string $token): string
    {
        $response = $this->send($this->request('POST', '/mcp', [
            'json' => [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-11-25',
                    'capabilities' => (object) [],
                    'clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
                ],
            ],
        ])
            ->withHeader('Host', 'localhost')
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer '.$token));

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return $response->getHeaderLine('Mcp-Session-Id');
    }

    private function rpc(string $token, string $session, string $method, mixed $params): array
    {
        $response = $this->send($this->request('POST', '/mcp', [
            'json' => [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => $method,
                'params' => $params,
            ],
        ])
            ->withHeader('Host', 'localhost')
            ->withHeader('Accept', 'application/json')
            ->withHeader('Mcp-Session-Id', $session)
            ->withHeader('Authorization', 'Bearer '.$token));

        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayNotHasKey('error', $body, json_encode($body));

        return $body['result'];
    }

    private function rpcExpectError(string $token, string $session, string $method, mixed $params): array
    {
        $response = $this->send($this->request('POST', '/mcp', [
            'json' => [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => $method,
                'params' => $params,
            ],
        ])
            ->withHeader('Host', 'localhost')
            ->withHeader('Accept', 'application/json')
            ->withHeader('Mcp-Session-Id', $session)
            ->withHeader('Authorization', 'Bearer '.$token));

        return json_decode((string) $response->getBody(), true);
    }
}
