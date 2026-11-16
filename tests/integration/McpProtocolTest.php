<?php

namespace CengizKemec\FlarumMcp\Tests\integration;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class McpProtocolTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private const TOKEN = 'fmcp_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('cengizkemec-mcp');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
            'mcp_connections' => [
                [
                    'id' => 1,
                    'name' => 'Test minimum',
                    'user_id' => 2,
                    'permission_profile' => 'minimum',
                    'token_hash' => hash('sha256', self::TOKEN),
                    'token_prefix' => 'fmcp_012345',
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
    public function modern_stateless_discovery_is_supported(): void
    {
        $response = $this->send(
            $this->request('POST', '/mcp', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'server/discover',
                    'params' => [
                        '_meta' => [
                            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                            'io.modelcontextprotocol/clientCapabilities' => (object) [],
                            'io.modelcontextprotocol/clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
                        ],
                    ],
                ],
            ])
                ->withHeader('Host', 'localhost')
                ->withHeader('Accept', 'application/json')
                ->withHeader('MCP-Protocol-Version', '2026-07-28')
                ->withHeader('Mcp-Method', 'server/discover')
                ->withHeader('Authorization', 'Bearer '.self::TOKEN)
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('result', $body, (string) $response->getBody());
    }

    #[Test]
    public function missing_bearer_token_is_rejected(): void
    {
        $response = $this->send(
            $this->request('POST', '/mcp', [
                'json' => $this->initializePayload(),
            ])->withHeader('Host', 'localhost')->withHeader('Accept', 'application/json')
        );

        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('2.0', $body['jsonrpc']);
        $this->assertArrayHasKey('error', $body);
    }

    #[Test]
    public function invalid_token_is_rejected(): void
    {
        $response = $this->send(
            $this->request('POST', '/mcp', [
                'json' => $this->initializePayload(),
            ])
                ->withHeader('Host', 'localhost')
                ->withHeader('Accept', 'application/json')
                ->withHeader('Authorization', 'Bearer fmcp_ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff')
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function initialize_and_tools_list_work(): void
    {
        $initialize = $this->send($this->authedRequest('/mcp', $this->initializePayload()));

        $this->assertSame(200, $initialize->getStatusCode(), (string) $initialize->getBody());
        $body = json_decode((string) $initialize->getBody(), true);
        $this->assertSame('2.0', $body['jsonrpc']);
        $this->assertSame('Flarum MCP', $body['result']['serverInfo']['name']);

        $session = $initialize->getHeaderLine('Mcp-Session-Id');
        $this->assertNotSame('', $session);

        $list = $this->send(
            $this->authedRequest('/mcp', [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/list',
                'params' => (object) [],
            ])->withHeader('Mcp-Session-Id', $session)
        );

        $this->assertSame(200, $list->getStatusCode(), (string) $list->getBody());
        $listBody = json_decode((string) $list->getBody(), true);
        $names = array_column($listBody['result']['tools'], 'name');

        $this->assertContains('search_discussions', $names);
        $this->assertContains('get_forum_info', $names);
        $this->assertNotContains('create_discussion', $names);
        $this->assertNotContains('delete_discussion', $names);
    }

    #[Test]
    public function unregistered_tool_cannot_be_executed(): void
    {
        $initialize = $this->send($this->authedRequest('/mcp', $this->initializePayload()));
        $session = $initialize->getHeaderLine('Mcp-Session-Id');

        $response = $this->send(
            $this->authedRequest('/mcp', [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'create_discussion',
                    'arguments' => ['title' => 'Hidden tool', 'content' => 'Should not run'],
                ],
            ])->withHeader('Mcp-Session-Id', $session)
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('error', $body);
        $this->assertSame(-32602, $body['error']['code']);
    }

    #[Test]
    public function a_session_can_be_terminated_with_delete(): void
    {
        $initialize = $this->send($this->authedRequest('/mcp', $this->initializePayload()));
        $session = $initialize->getHeaderLine('Mcp-Session-Id');
        $this->assertNotSame('', $session);

        $delete = $this->send(
            $this->request('DELETE', '/mcp')
                ->withHeader('Host', 'localhost')
                ->withHeader('Accept', 'application/json')
                ->withHeader('Authorization', 'Bearer '.self::TOKEN)
                ->withHeader('Mcp-Session-Id', $session)
        );

        $this->assertSame(200, $delete->getStatusCode(), (string) $delete->getBody());

        $after = $this->send(
            $this->authedRequest('/mcp', [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/list',
                'params' => (object) [],
            ])->withHeader('Mcp-Session-Id', $session)
        );

        $this->assertSame(404, $after->getStatusCode(), (string) $after->getBody());
        $body = json_decode((string) $after->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }

    private function authedRequest(string $path, array $payload)
    {
        return $this->request('POST', $path, ['json' => $payload])
            ->withHeader('Host', 'localhost')
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer '.self::TOKEN);
    }

    private function initializePayload(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => (object) [],
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
            ],
        ];
    }
}
