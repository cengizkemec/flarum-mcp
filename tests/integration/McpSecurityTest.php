<?php

namespace CengizKemec\FlarumMcp\Tests\integration;

use Carbon\Carbon;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class McpSecurityTest extends TestCase
{
    private const EXPIRED = 'fmcp_expiredexpiredexpiredexpiredexpiredexpiredexpiredexpiredexpired';
    private const REVOKED = 'fmcp_revokedrevokedrevokedrevokedrevokedrevokedrevokedrevokedrevokedrevoked';
    private const LIMITED = 'fmcp_limitedlimitedlimitedlimitedlimitedlimitedlimitedlimitedlimitedlimit';

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('cengizkemec-mcp');

        $this->prepareDatabase([
            'mcp_connections' => [
                [
                    'id' => 30,
                    'name' => 'Expired',
                    'user_id' => 1,
                    'permission_profile' => 'minimum',
                    'token_hash' => hash('sha256', self::EXPIRED),
                    'token_prefix' => 'fmcp_expire',
                    'token_expires_at' => Carbon::now()->subHour(),
                    'status' => 'active',
                    'rate_limit_per_minute' => 1000,
                    'rate_limit_per_hour' => 10000,
                    'rate_limit_per_day' => 100000,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'id' => 31,
                    'name' => 'Revoked',
                    'user_id' => 1,
                    'permission_profile' => 'minimum',
                    'token_hash' => hash('sha256', self::REVOKED),
                    'token_prefix' => 'fmcp_revoked',
                    'token_revoked_at' => Carbon::now(),
                    'status' => 'disabled',
                    'rate_limit_per_minute' => 1000,
                    'rate_limit_per_hour' => 10000,
                    'rate_limit_per_day' => 100000,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'id' => 32,
                    'name' => 'Limited',
                    'user_id' => 1,
                    'permission_profile' => 'minimum',
                    'token_hash' => hash('sha256', self::LIMITED),
                    'token_prefix' => 'fmcp_limited',
                    'status' => 'active',
                    'rate_limit_per_minute' => 1,
                    'rate_limit_per_hour' => 1000,
                    'rate_limit_per_day' => 10000,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
            ],
        ]);
    }

    #[Test]
    public function expired_and_revoked_tokens_are_rejected(): void
    {
        $this->assertSame(401, $this->initialize(self::EXPIRED)->getStatusCode());
        $this->assertSame(401, $this->initialize(self::REVOKED)->getStatusCode());
    }

    #[Test]
    public function token_in_query_string_is_ignored(): void
    {
        $response = $this->send(
            $this->request('POST', '/mcp?token='.self::LIMITED, [
                'json' => $this->initializePayload(),
            ])
                ->withHeader('Host', 'localhost')
                ->withHeader('Accept', 'application/json')
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function wrong_content_type_and_method_are_rejected(): void
    {
        $wrongType = $this->send(
            $this->request('POST', '/mcp', [
                'json' => $this->initializePayload(),
            ])
                ->withHeader('Host', 'localhost')
                ->withHeader('Content-Type', 'text/plain')
                ->withHeader('Authorization', 'Bearer '.self::LIMITED)
        );

        $this->assertSame(415, $wrongType->getStatusCode());

        $wrongMethod = $this->send(
            $this->request('PUT', '/mcp')
                ->withHeader('Host', 'localhost')
                ->withHeader('Authorization', 'Bearer '.self::LIMITED)
        );

        $this->assertSame(405, $wrongMethod->getStatusCode());
    }

    #[Test]
    public function connection_rate_limit_is_enforced(): void
    {
        $first = $this->initialize(self::LIMITED);
        $this->assertSame(200, $first->getStatusCode(), (string) $first->getBody());

        $session = $first->getHeaderLine('Mcp-Session-Id');

        $second = $this->send(
            $this->request('POST', '/mcp', [
                'json' => [
                    'jsonrpc' => '2.0',
                    'id' => 2,
                    'method' => 'tools/list',
                    'params' => (object) [],
                ],
            ])
                ->withHeader('Host', 'localhost')
                ->withHeader('Accept', 'application/json')
                ->withHeader('Mcp-Session-Id', $session)
                ->withHeader('Authorization', 'Bearer '.self::LIMITED)
        );

        $this->assertSame(429, $second->getStatusCode());
        $this->assertNotSame('', $second->getHeaderLine('Retry-After'));
    }

    private function initialize(string $token): \Psr\Http\Message\ResponseInterface
    {
        return $this->send(
            $this->request('POST', '/mcp', [
                'json' => $this->initializePayload(),
            ])
                ->withHeader('Host', 'localhost')
                ->withHeader('Accept', 'application/json')
                ->withHeader('Authorization', 'Bearer '.$token)
        );
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
