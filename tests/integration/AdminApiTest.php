<?php

namespace CengizKemec\FlarumMcp\Tests\integration;

use Flarum\Discussion\Discussion;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AdminApiTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('cengizkemec-mcp');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
        ]);
    }

    #[Test]
    public function non_admins_cannot_access_the_admin_api(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/mcp/overview', ['authenticatedAs' => 2])
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function admin_can_create_a_connection_and_the_token_is_hashed(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/mcp/connections', [
                'authenticatedAs' => 1,
                'json' => [
                    'name' => 'Production AI',
                    'userId' => 1,
                    'permissionProfile' => 'minimum',
                    'expiresInDays' => 30,
                ],
            ])
        );

        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertMatchesRegularExpression('/^fmcp_[a-f0-9]{64}$/', $body['token']);
        $this->assertNotEmpty($body['connection']['createdAt']);

        $row = $this->database()->table('mcp_connections')->where('id', $body['connection']['id'])->first();
        $this->assertNotSame($body['token'], $row->token_hash);
        $this->assertSame(hash('sha256', $body['token']), $row->token_hash);
        $this->assertSame('minimum', $row->permission_profile);
    }

    #[Test]
    public function connection_index_serializes_timestamps_after_a_fresh_query(): void
    {
        $create = $this->send(
            $this->request('POST', '/api/mcp/connections', [
                'authenticatedAs' => 1,
                'json' => [
                    'name' => 'Timestamp connection',
                    'userId' => 1,
                    'permissionProfile' => 'minimum',
                ],
            ])
        );

        $this->assertSame(201, $create->getStatusCode(), (string) $create->getBody());
        $created = json_decode((string) $create->getBody(), true)['connection'];

        $list = $this->send(
            $this->request('GET', '/api/mcp/connections', ['authenticatedAs' => 1])
        );

        $this->assertSame(200, $list->getStatusCode(), (string) $list->getBody());
        $connections = json_decode((string) $list->getBody(), true)['connections'];

        $listed = null;

        foreach ($connections as $connection) {
            if ($connection['id'] === $created['id']) {
                $listed = $connection;
                break;
            }
        }

        $this->assertNotNull($listed);
        $this->assertNotNull($listed['createdAt']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $listed['createdAt']);
    }

    #[Test]
    public function connections_can_be_rotated_revoked_and_deleted(): void
    {
        $create = $this->send(
            $this->request('POST', '/api/mcp/connections', [
                'authenticatedAs' => 1,
                'json' => [
                    'name' => 'Lifecycle connection',
                    'userId' => 1,
                    'permissionProfile' => 'minimum',
                ],
            ])
        );

        $this->assertSame(201, $create->getStatusCode(), (string) $create->getBody());
        $id = json_decode((string) $create->getBody(), true)['connection']['id'];

        $rotate = $this->send(
            $this->request('POST', '/api/mcp/connections/'.$id.'/rotate', [
                'authenticatedAs' => 1,
                'json' => ['expiresInDays' => 7],
            ])
        );

        $this->assertSame(200, $rotate->getStatusCode(), (string) $rotate->getBody());
        $rotated = json_decode((string) $rotate->getBody(), true);
        $this->assertMatchesRegularExpression('/^fmcp_[a-f0-9]{64}$/', $rotated['token']);
        $this->assertNotNull($rotated['connection']['tokenExpiresAt']);

        $revoke = $this->send(
            $this->request('POST', '/api/mcp/connections/'.$id.'/revoke', [
                'authenticatedAs' => 1,
            ])
        );

        $this->assertSame(200, $revoke->getStatusCode(), (string) $revoke->getBody());
        $revoked = json_decode((string) $revoke->getBody(), true)['connection'];
        $this->assertSame('disabled', $revoked['status']);
        $this->assertNotNull($revoked['tokenRevokedAt']);

        $reactivate = $this->send(
            $this->request('PATCH', '/api/mcp/connections/'.$id, [
                'authenticatedAs' => 1,
                'json' => ['status' => 'active'],
            ])
        );

        $this->assertSame(422, $reactivate->getStatusCode(), (string) $reactivate->getBody());

        $delete = $this->send(
            $this->request('DELETE', '/api/mcp/connections/'.$id, [
                'authenticatedAs' => 1,
            ])
        );

        $this->assertSame(200, $delete->getStatusCode(), (string) $delete->getBody());
        $this->assertTrue(json_decode((string) $delete->getBody(), true)['deleted']);
        $this->assertSame(0, $this->database()->table('mcp_connections')->where('id', $id)->count());
    }

    #[Test]
    public function new_connections_use_the_default_profile_and_rate_limits(): void
    {
        $settings = $this->send(
            $this->request('PATCH', '/api/mcp/settings', [
                'authenticatedAs' => 1,
                'json' => [
                    'defaultPermissionProfile' => 'standard',
                    'defaultRateLimitPerMinute' => 5,
                    'defaultRateLimitPerHour' => 6,
                    'defaultRateLimitPerDay' => 7,
                ],
            ])
        );

        $this->assertSame(200, $settings->getStatusCode(), (string) $settings->getBody());

        $connection = $this->send(
            $this->request('POST', '/api/mcp/connections', [
                'authenticatedAs' => 1,
                'json' => [
                    'name' => 'Defaulted connection',
                    'userId' => 1,
                ],
            ])
        );

        $this->assertSame(201, $connection->getStatusCode(), (string) $connection->getBody());
        $data = json_decode((string) $connection->getBody(), true)['connection'];
        $this->assertSame('standard', $data['permissionProfile']);
        $this->assertSame(5, $data['rateLimitPerMinute']);
        $this->assertSame(6, $data['rateLimitPerHour']);
        $this->assertSame(7, $data['rateLimitPerDay']);
    }

    #[Test]
    public function connection_settings_can_be_updated(): void
    {
        $create = $this->send(
            $this->request('POST', '/api/mcp/connections', [
                'authenticatedAs' => 1,
                'json' => [
                    'name' => 'Editable connection',
                    'userId' => 1,
                    'permissionProfile' => 'minimum',
                    'expiresInDays' => 30,
                ],
            ])
        );

        $this->assertSame(201, $create->getStatusCode(), (string) $create->getBody());
        $id = json_decode((string) $create->getBody(), true)['connection']['id'];

        $update = $this->send(
            $this->request('PATCH', '/api/mcp/connections/'.$id, [
                'authenticatedAs' => 1,
                'json' => [
                    'name' => 'Updated connection',
                    'expiresInDays' => 5,
                    'rateLimitPerMinute' => 9,
                    'status' => 'disabled',
                    'requireApproval' => true,
                ],
            ])
        );

        $this->assertSame(200, $update->getStatusCode(), (string) $update->getBody());
        $updated = json_decode((string) $update->getBody(), true)['connection'];
        $this->assertSame('Updated connection', $updated['name']);
        $this->assertSame(9, $updated['rateLimitPerMinute']);
        $this->assertSame('disabled', $updated['status']);
        $this->assertTrue($updated['requireApproval']);
        $this->assertNotNull($updated['tokenExpiresAt']);

        $clear = $this->send(
            $this->request('PATCH', '/api/mcp/connections/'.$id, [
                'authenticatedAs' => 1,
                'json' => ['expiresInDays' => ''],
            ])
        );

        $this->assertSame(200, $clear->getStatusCode(), (string) $clear->getBody());
        $this->assertNull(json_decode((string) $clear->getBody(), true)['connection']['tokenExpiresAt']);
    }

    #[Test]
    public function profiles_can_be_created_duplicated_and_exported(): void
    {
        $create = $this->send(
            $this->request('POST', '/api/mcp/profiles', [
                'authenticatedAs' => 1,
                'json' => [
                    'name' => 'Support AI',
                    'description' => 'Read and reply only',
                    'permissions' => ['discussions.read', 'posts.create'],
                ],
            ])
        );

        $this->assertSame(201, $create->getStatusCode(), (string) $create->getBody());
        $profile = json_decode((string) $create->getBody(), true)['profile'];
        $this->assertSame(['discussions.read', 'posts.create'], $profile['permissions']);

        $duplicate = $this->send(
            $this->request('POST', '/api/mcp/profiles/'.$profile['key'].'/duplicate', [
                'authenticatedAs' => 1,
            ])
        );

        $this->assertSame(201, $duplicate->getStatusCode(), (string) $duplicate->getBody());

        $export = $this->send(
            $this->request('GET', '/api/mcp/profiles/'.$profile['key'].'/export', [
                'authenticatedAs' => 1,
            ])
        );

        $this->assertSame(200, $export->getStatusCode());
        $exported = json_decode((string) $export->getBody(), true);
        $this->assertSame('flarum-mcp-profile', $exported['format']);
        $this->assertSame(['discussions.read', 'posts.create'], $exported['permissions']);
    }

    #[Test]
    public function tools_can_be_disabled_globally(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/mcp/tools/search_discussions', [
                'authenticatedAs' => 1,
                'json' => ['enabled' => false],
            ])
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        $tool = json_decode((string) $response->getBody(), true)['tool'];
        $this->assertFalse($tool['enabled']);
        $this->assertSame(1, $this->database()->table('mcp_tool_states')->where('tool_name', 'search_discussions')->count());
    }

    #[Test]
    public function built_in_skills_are_listed_and_custom_skills_are_validated(): void
    {
        $list = $this->send(
            $this->request('GET', '/api/mcp/skills', ['authenticatedAs' => 1])
        );

        $this->assertSame(200, $list->getStatusCode());
        $skills = json_decode((string) $list->getBody(), true)['skills'];
        $this->assertGreaterThanOrEqual(15, count($skills));
        $this->assertTrue((bool) $skills[0]['isBuiltin']);

        $install = $this->send(
            $this->request('POST', '/api/mcp/skills', [
                'authenticatedAs' => 1,
                'json' => [
                    'content' => "# Support Reply Guide\n\nUse a calm, factual tone when replying to support questions.",
                ],
            ])
        );

        $this->assertSame(201, $install->getStatusCode(), (string) $install->getBody());
        $installed = json_decode((string) $install->getBody(), true)['skill'];
        $this->assertSame('support-reply-guide', $installed['slug']);
        $this->assertFalse($installed['isBuiltin']);

        $unsafe = $this->send(
            $this->request('POST', '/api/mcp/skills', [
                'authenticatedAs' => 1,
                'json' => [
                    'content' => "# Unsafe\n\n<script>alert(1)</script>",
                ],
            ])
        );

        $this->assertSame(422, $unsafe->getStatusCode());
    }

    #[Test]
    public function encoded_builtin_skill_identifiers_work_for_enable_and_export(): void
    {
        $id = rawurlencode('builtin:flarum-mithriljs');

        $disable = $this->send(
            $this->request('POST', '/api/mcp/skills/'.$id.'/enable', [
                'authenticatedAs' => 1,
                'json' => ['enabled' => false],
            ])
        );

        $this->assertSame(200, $disable->getStatusCode(), (string) $disable->getBody());
        $this->assertFalse(json_decode((string) $disable->getBody(), true)['skill']['enabled']);

        $export = $this->send(
            $this->request('GET', '/api/mcp/skills/'.$id.'/export', ['authenticatedAs' => 1])
        );

        $this->assertSame(200, $export->getStatusCode(), (string) $export->getBody());
        $exported = json_decode((string) $export->getBody(), true);
        $this->assertSame('flarum-mithriljs.md', $exported['filename']);
        $this->assertStringContainsString('Flarum Mithril.js', $exported['content']);

        $enable = $this->send(
            $this->request('POST', '/api/mcp/skills/'.$id.'/enable', [
                'authenticatedAs' => 1,
                'json' => ['enabled' => true],
            ])
        );

        $this->assertSame(200, $enable->getStatusCode(), (string) $enable->getBody());
        $this->assertTrue(json_decode((string) $enable->getBody(), true)['skill']['enabled']);
    }

    #[Test]
    public function custom_prompts_are_generated_for_mcp_clients(): void
    {
        $connection = $this->send(
            $this->request('POST', '/api/mcp/connections', [
                'authenticatedAs' => 1,
                'json' => [
                    'name' => 'Prompt connection',
                    'userId' => 1,
                    'permissionProfile' => 'full',
                ],
            ])
        );

        $this->assertSame(201, $connection->getStatusCode(), (string) $connection->getBody());
        $token = json_decode((string) $connection->getBody(), true)['token'];

        $prompt = $this->send(
            $this->request('POST', '/api/mcp/prompts', [
                'authenticatedAs' => 1,
                'json' => [
                    'name' => 'custom_smoke_prompt',
                    'description' => 'A custom prompt used by the integration test.',
                    'content' => 'Answer the following topic: {{topic}}',
                    'arguments' => [
                        ['name' => 'topic', 'description' => 'Topic to answer', 'required' => true],
                    ],
                    'requiredPermission' => 'prompts.read',
                ],
            ])
        );

        $this->assertSame(201, $prompt->getStatusCode(), (string) $prompt->getBody());

        $session = $this->initialize($token);
        $generated = $this->rpc($token, $session, 'prompts/get', [
            'name' => 'custom_smoke_prompt',
            'arguments' => ['topic' => 'Flarum permissions'],
        ]);

        $this->assertStringContainsString(
            'Answer the following topic: Flarum permissions',
            $generated['messages'][0]['content']['text']
        );
    }

    #[Test]
    public function approval_workflow_executes_only_after_approval(): void
    {
        $connection = $this->send(
            $this->request('POST', '/api/mcp/connections', [
                'authenticatedAs' => 1,
                'json' => [
                    'name' => 'Approval connection',
                    'userId' => 1,
                    'permissionProfile' => 'full',
                    'requireApproval' => true,
                ],
            ])
        );

        $connectionBody = json_decode((string) $connection->getBody(), true);
        $token = $connectionBody['token'];

        $session = $this->initialize($token);

        $created = $this->callTool($token, $session, 'create_discussion', [
            'title' => 'Approval end to end',
            'content' => 'This discussion must be deleted only after approval.',
        ]);
        $discussion = json_decode($created['content'][0]['text'], true);

        $delete = $this->callTool($token, $session, 'delete_discussion', ['id' => $discussion['id']]);
        $deleteResult = json_decode($delete['content'][0]['text'], true);
        $this->assertSame('approval_required', $deleteResult['status']);

        $approve = $this->send(
            $this->request('POST', '/api/mcp/approvals/'.$deleteResult['approval_id'].'/approve', [
                'authenticatedAs' => 1,
                'json' => ['note' => 'Approved by integration test'],
            ])
        );

        $this->assertSame(200, $approve->getStatusCode(), (string) $approve->getBody());
        $approved = json_decode((string) $approve->getBody(), true);
        $this->assertFalse($approved['result']['isError']);
        $this->assertNull(Discussion::query()->find($discussion['id']));
    }

    private function initialize(string $token): string
    {
        $response = $this->send(
            $this->request('POST', '/mcp', [
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
                ->withHeader('Authorization', 'Bearer '.$token)
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return $response->getHeaderLine('Mcp-Session-Id');
    }

    private function rpc(string $token, string $session, string $method, mixed $params): array
    {
        $response = $this->send(
            $this->request('POST', '/mcp', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 3,
                    'method' => $method,
                    'params' => $params,
                ],
            ])
                ->withHeader('Host', 'localhost')
                ->withHeader('Accept', 'application/json')
                ->withHeader('Mcp-Session-Id', $session)
                ->withHeader('Authorization', 'Bearer '.$token)
        );

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode(), json_encode($body));
        $this->assertArrayNotHasKey('error', $body, json_encode($body));

        return $body['result'];
    }

    private function callTool(string $token, string $session, string $name, array $arguments): array
    {
        $response = $this->send(
            $this->request('POST', '/mcp', [
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
                ->withHeader('Authorization', 'Bearer '.$token)
        );

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode(), json_encode($body));
        $this->assertArrayNotHasKey('error', $body, json_encode($body));

        return $body['result'];
    }
}
