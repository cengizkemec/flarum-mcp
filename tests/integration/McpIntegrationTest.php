<?php

namespace CengizKemec\FlarumMcp\Tests\integration;

use Carbon\Carbon;
use CengizKemec\FlarumMcp\Extend\Mcp;
use CengizKemec\FlarumMcp\Mcp\McpRequestContext;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class McpIntegrationTest extends TestCase
{
    private const TOKEN = 'fmcp_extensionextensionextensionextensionextensionextensionextensionextension';

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('cengizkemec-mcp');

        $this->extend(
            (new Mcp())
                ->permission('test.echo', [
                    'group' => 'integrations',
                    'risk' => 'low',
                    'label' => 'Echo test capability',
                ])
                ->tool(
                    'extension_echo',
                    'test.echo',
                    [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string'],
                        ],
                        'required' => ['message'],
                        'additionalProperties' => false,
                    ],
                    fn (array $arguments, McpRequestContext $context): array => [
                        'echo' => (string) ($arguments['message'] ?? ''),
                    ],
                    'Echoes a message from a third-party extension.'
                )
                ->resource(
                    'flarum://test/echo',
                    'extension_echo_resource',
                    'test.echo',
                    fn (McpRequestContext $context, array $variables): array => ['echo' => 'resource'],
                    'A test resource from a third-party extension.'
                )
                ->prompt(
                    'extension_echo_prompt',
                    'test.echo',
                    fn (array $arguments, McpRequestContext $context): array => [
                        [
                            'role' => 'user',
                            'content' => [
                                'type' => 'text',
                                'text' => 'Echo prompt '.($arguments['message'] ?? ''),
                            ],
                        ],
                    ],
                    'A test prompt from a third-party extension.',
                    [
                        ['name' => 'message', 'description' => 'Message to echo', 'required' => true],
                    ]
                )
        );

        $this->prepareDatabase([
            'mcp_connections' => [
                [
                    'id' => 30,
                    'name' => 'Extension tester',
                    'user_id' => 1,
                    'permission_profile' => 'full',
                    'token_hash' => hash('sha256', self::TOKEN),
                    'token_prefix' => 'fmcp_extens',
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
    public function extension_capabilities_require_admin_approval_and_are_disabled_again(): void
    {
        $session = $this->initialize(self::TOKEN);

        $toolsBefore = $this->rpc(self::TOKEN, $session, 'tools/list', (object) []);
        $this->assertNotContains('extension_echo', array_column($toolsBefore['tools'], 'name'));

        $resourcesBefore = $this->rpc(self::TOKEN, $session, 'resources/list', (object) []);
        $this->assertNotContains('flarum://test/echo', array_column($resourcesBefore['resources'], 'uri'));

        $promptsBefore = $this->rpc(self::TOKEN, $session, 'prompts/list', (object) []);
        $this->assertNotContains('extension_echo_prompt', array_column($promptsBefore['prompts'], 'name'));

        $adminBefore = $this->adminIntegrations();
        $this->assertSame('unknown-extension', $adminBefore['extensionId']);
        $this->assertSame('pending', $adminBefore['status']);
        $this->assertFalse($adminBefore['approved']);
        $this->assertContains('extension_echo', $adminBefore['tools']);
        $this->assertContains('flarum://test/echo', $adminBefore['resources']);
        $this->assertContains('extension_echo_prompt', $adminBefore['prompts']);

        $approve = $this->send(
            $this->request('PATCH', '/api/mcp/integrations/unknown-extension', [
                'authenticatedAs' => 1,
                'json' => ['status' => 'approved'],
            ])
        );

        $this->assertSame(200, $approve->getStatusCode(), (string) $approve->getBody());
        $approved = json_decode((string) $approve->getBody(), true)['integration'];
        $this->assertSame('approved', $approved['status']);
        $this->assertTrue($approved['approved']);

        $toolsAfter = $this->rpc(self::TOKEN, $session, 'tools/list', (object) []);
        $this->assertContains('extension_echo', array_column($toolsAfter['tools'], 'name'));

        $echo = $this->rpc(self::TOKEN, $session, 'tools/call', [
            'name' => 'extension_echo',
            'arguments' => ['message' => 'hello from an extension'],
        ]);
        $this->assertFalse($echo['isError'] ?? false);
        $this->assertSame(['echo' => 'hello from an extension'], json_decode($echo['content'][0]['text'], true));

        $resourcesAfter = $this->rpc(self::TOKEN, $session, 'resources/list', (object) []);
        $this->assertContains('flarum://test/echo', array_column($resourcesAfter['resources'], 'uri'));

        $resource = $this->rpc(self::TOKEN, $session, 'resources/read', ['uri' => 'flarum://test/echo']);
        $this->assertNotEmpty($resource['contents']);
        $this->assertSame(['echo' => 'resource'], json_decode($resource['contents'][0]['text'], true));

        $promptsAfter = $this->rpc(self::TOKEN, $session, 'prompts/list', (object) []);
        $this->assertContains('extension_echo_prompt', array_column($promptsAfter['prompts'], 'name'));

        $prompt = $this->rpc(self::TOKEN, $session, 'prompts/get', [
            'name' => 'extension_echo_prompt',
            'arguments' => ['message' => 'hi'],
        ]);
        $this->assertStringContainsString('Echo prompt hi', $prompt['messages'][0]['content']['text']);

        $disable = $this->send(
            $this->request('PATCH', '/api/mcp/integrations/unknown-extension', [
                'authenticatedAs' => 1,
                'json' => ['status' => 'disabled'],
            ])
        );

        $this->assertSame(200, $disable->getStatusCode(), (string) $disable->getBody());
        $this->assertSame('disabled', json_decode((string) $disable->getBody(), true)['integration']['status']);

        $toolsDisabled = $this->rpc(self::TOKEN, $session, 'tools/list', (object) []);
        $this->assertNotContains('extension_echo', array_column($toolsDisabled['tools'], 'name'));

        $denied = $this->rpcExpectError(self::TOKEN, $session, 'tools/call', [
            'name' => 'extension_echo',
            'arguments' => ['message' => 'blocked'],
        ]);
        $this->assertArrayHasKey('error', $denied);

        $adminAfter = $this->adminIntegrations();
        $this->assertSame('disabled', $adminAfter['status']);
        $this->assertFalse($adminAfter['approved']);
    }

    private function adminIntegrations(): array
    {
        $response = $this->send(
            $this->request('GET', '/api/mcp/integrations', ['authenticatedAs' => 1])
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $integrations = json_decode((string) $response->getBody(), true)['integrations'];

        foreach ($integrations as $integration) {
            if ($integration['extensionId'] === 'unknown-extension') {
                return $integration;
            }
        }

        $this->fail('The third-party integration was not listed.');
    }

    private function initialize(string $token): string
    {
        $response = $this->send($this->request('POST', '/mcp', [
            'json' => [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2026-07-28',
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
