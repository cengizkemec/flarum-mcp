<?php

namespace CengizKemec\FlarumMcp\Tests\integration;

use Carbon\Carbon;
use CengizKemec\FlarumMcp\Mcp\ApprovalService;
use CengizKemec\FlarumMcp\Mcp\ToolExecutor;
use CengizKemec\FlarumMcp\Model\McpApproval;
use Flarum\Discussion\Discussion;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class McpToolExecutionTest extends TestCase
{
    private const TOKEN = 'fmcp_abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd';

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('cengizkemec-mcp');

        $this->prepareDatabase([
            'mcp_connections' => [
                [
                    'id' => 10,
                    'name' => 'Admin full',
                    'user_id' => 1,
                    'permission_profile' => 'full',
                    'token_hash' => hash('sha256', self::TOKEN),
                    'token_prefix' => 'fmcp_abcdef',
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
    public function admin_connection_can_create_and_read_a_discussion(): void
    {
        $session = $this->initialize();

        $created = $this->callTool($session, 'create_discussion', [
            'title' => 'MCP integration test discussion',
            'content' => 'This discussion was created by an automated MCP test.',
        ]);

        $this->assertFalse($created['isError'] ?? false, json_encode($created));
        $data = json_decode($created['content'][0]['text'], true);

        $this->assertSame('MCP integration test discussion', $data['title']);
        $this->assertNotEmpty($data['id']);

        $read = $this->callTool($session, 'get_discussion', [
            'id' => $data['id'],
            'includePosts' => true,
        ]);

        $this->assertFalse($read['isError'] ?? false, json_encode($read));
        $readData = json_decode($read['content'][0]['text'], true);
        $this->assertSame($data['id'], $readData['id']);
        $this->assertNotEmpty($readData['posts']);

        $reply = $this->callTool($session, 'create_post', [
            'discussionId' => $data['id'],
            'content' => 'A reply from the MCP integration test.',
        ]);

        $this->assertFalse($reply['isError'] ?? false, json_encode($reply));
        $replyData = json_decode($reply['content'][0]['text'], true);
        $this->assertSame($data['id'], $replyData['discussionId']);
    }

    #[Test]
    public function approval_required_tool_is_not_executed(): void
    {
        $this->database()->table('mcp_connections')->where('id', 10)->update(['require_approval' => 1]);

        $session = $this->initialize();

        $created = $this->callTool($session, 'create_discussion', [
            'title' => 'Approval workflow discussion',
            'content' => 'This should remain until approval.',
        ]);

        $data = json_decode($created['content'][0]['text'], true);

        $result = $this->callTool($session, 'delete_discussion', ['id' => $data['id']]);
        $resultData = json_decode($result['content'][0]['text'], true);

        $this->assertSame('approval_required', $resultData['status']);
        $this->assertNotEmpty($resultData['approval_id']);
        $this->assertTrue(
            $this->database()->table('mcp_approvals')
                ->where('id', $resultData['approval_id'])
                ->where('status', 'pending')
                ->exists()
        );
        $this->assertNotNull(Discussion::query()->find($data['id']));

        $approval = McpApproval::query()->findOrFail($resultData['approval_id']);
        $admin = User::query()->findOrFail(1);

        $this->app()->getContainer()->make(ApprovalService::class)->approve($approval, $admin, 'Approved in test');
        $result = $this->app()->getContainer()->make(ToolExecutor::class)->executeApproved(
            $approval,
            $this->request('POST', '/mcp'),
            $admin
        );

        $this->assertFalse($result->isError);
        $this->assertNull(Discussion::query()->find($data['id']));
    }

    private function initialize(): string
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
            ->withHeader('Authorization', 'Bearer '.self::TOKEN));

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return $response->getHeaderLine('Mcp-Session-Id');
    }

    private function callTool(string $session, string $name, array $arguments): array
    {
        $response = $this->send($this->request('POST', '/mcp', [
            'json' => [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/call',
                'params' => ['name' => $name, 'arguments' => $arguments],
            ],
        ])
            ->withHeader('Host', 'localhost')
            ->withHeader('Accept', 'application/json')
            ->withHeader('Mcp-Session-Id', $session)
            ->withHeader('Authorization', 'Bearer '.self::TOKEN));

        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode(), json_encode($body));

        if (isset($body['error'])) {
            $this->fail('MCP error: '.json_encode($body['error']));
        }

        return $body['result'];
    }
}
