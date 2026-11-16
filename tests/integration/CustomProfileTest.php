<?php

namespace CengizKemec\FlarumMcp\Tests\integration;

use Carbon\Carbon;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CustomProfileTest extends TestCase
{
    private const CUSTOM_TOKEN = 'fmcp_customcustomcustomcustomcustomcustomcustomcustomcustomcustomcustomcu';
    private const FULL_TOKEN = 'fmcp_fullfullfullfullfullfullfullfullfullfullfullfullfullfullfullfullfullfu';

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('cengizkemec-mcp');

        $this->prepareDatabase([
            'mcp_custom_profiles' => [
                [
                    'id' => 1,
                    'name' => 'Discussion reader',
                    'description' => 'Only discussion metadata',
                    'permissions' => json_encode(['discussions.read']),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
            ],
            'mcp_connections' => [
                [
                    'id' => 40,
                    'name' => 'Custom profile',
                    'user_id' => 1,
                    'permission_profile' => 'custom-1',
                    'token_hash' => hash('sha256', self::CUSTOM_TOKEN),
                    'token_prefix' => 'fmcp_custom',
                    'status' => 'active',
                    'rate_limit_per_minute' => 1000,
                    'rate_limit_per_hour' => 10000,
                    'rate_limit_per_day' => 100000,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'id' => 41,
                    'name' => 'Full profile',
                    'user_id' => 1,
                    'permission_profile' => 'full',
                    'token_hash' => hash('sha256', self::FULL_TOKEN),
                    'token_prefix' => 'fmcp_fullfu',
                    'status' => 'active',
                    'rate_limit_per_minute' => 1000,
                    'rate_limit_per_hour' => 10000,
                    'rate_limit_per_day' => 100000,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
            ],
        ]);
    }

    #[Test]
    public function custom_profile_is_intersected_with_tool_permissions(): void
    {
        $session = $this->initialize(self::CUSTOM_TOKEN);

        $list = $this->rpc(self::CUSTOM_TOKEN, $session, 'tools/list', (object) []);
        $names = array_column($list['tools'], 'name');

        $this->assertContains('get_discussion', $names);
        $this->assertNotContains('get_posts', $names);
        $this->assertNotContains('create_post', $names);
        $this->assertNotContains('search_discussions', $names);
        $this->assertNotContains('delete_discussion', $names);
    }

    #[Test]
    public function discussion_read_without_posts_permission_hides_posts(): void
    {
        $fullSession = $this->initialize(self::FULL_TOKEN);

        $created = $this->callTool(self::FULL_TOKEN, $fullSession, 'create_discussion', [
            'title' => 'Custom profile visibility',
            'content' => 'Post content that needs posts.read.',
        ]);
        $discussion = json_decode($created['content'][0]['text'], true);

        $customSession = $this->initialize(self::CUSTOM_TOKEN);

        $read = $this->callTool(self::CUSTOM_TOKEN, $customSession, 'get_discussion', [
            'id' => $discussion['id'],
            'includePosts' => true,
        ]);
        $data = json_decode($read['content'][0]['text'], true);

        $this->assertSame($discussion['id'], $data['id']);
        $this->assertArrayNotHasKey('posts', $data);
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

    private function callTool(string $token, string $session, string $name, array $arguments): array
    {
        return $this->rpc($token, $session, 'tools/call', ['name' => $name, 'arguments' => $arguments]);
    }
}
