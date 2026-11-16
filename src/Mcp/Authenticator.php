<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use Carbon\Carbon;
use CengizKemec\FlarumMcp\Exception\McpAuthenticationException;
use CengizKemec\FlarumMcp\Exception\McpRateLimitException;
use CengizKemec\FlarumMcp\Model\McpConnection;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final class Authenticator
{
    private const FAILURE_PREFIX = 'cengizkemec-mcp:authfail:';
    private const FAILURE_LIMIT = 20;
    private const FAILURE_TTL = 600;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly AuditLogger $audit,
        private readonly LoggerInterface $logger
    ) {
    }

    public function authenticate(ServerRequestInterface $request): AuthResult
    {
        $ip = self::clientIp($request);

        $this->assertNotLockedOut($ip);

        $header = trim($request->getHeaderLine('Authorization'));

        if ($header === '') {
            $this->recordFailure($ip, 'missing_token');

            throw new McpAuthenticationException(
                'A bearer token is required.',
                401,
                'invalid_token',
                $this->challenge('A bearer token is required.')
            );
        }

        if (! preg_match('/^Bearer\s+([A-Za-z0-9_\-.~+\/]+=*)$/i', $header, $matches)) {
            $this->recordFailure($ip, 'malformed_authorization');

            throw new McpAuthenticationException(
                'The Authorization header must use the Bearer scheme.',
                400,
                'invalid_request',
                $this->challenge('The Authorization header must use the Bearer scheme.')
            );
        }

        $plain = $matches[1];

        if (strlen($plain) < 32 || strlen($plain) > 256) {
            $this->recordFailure($ip, 'invalid_token_length');

            throw new McpAuthenticationException(
                'The bearer token is not valid.',
                401,
                'invalid_token',
                $this->challenge('The bearer token is not valid.')
            );
        }

        $hash = hash('sha256', $plain);

        /** @var McpConnection|null $connection */
        $connection = McpConnection::query()->where('token_hash', $hash)->first();

        if (! $connection || ! hash_equals($connection->token_hash, $hash)) {
            $this->recordFailure($ip, 'invalid_token');

            throw new McpAuthenticationException(
                'The bearer token is not valid.',
                401,
                'invalid_token',
                $this->challenge('The bearer token is not valid.')
            );
        }

        if (! $connection->isActive()) {
            $this->recordFailure($ip, 'inactive_connection');

            throw new McpAuthenticationException(
                'This MCP connection is disabled.',
                401,
                'invalid_token',
                $this->challenge('This MCP connection is disabled.')
            );
        }

        if ($connection->isExpired()) {
            $this->recordFailure($ip, 'expired_token');

            throw new McpAuthenticationException(
                'The bearer token has expired.',
                401,
                'invalid_token',
                $this->challenge('The bearer token has expired.')
            );
        }

        $connection->loadMissing('user');
        $user = $connection->user;

        if ($user === null) {
            $this->recordFailure($ip, 'missing_user');

            throw new McpAuthenticationException(
                'The MCP connection is not assigned to a valid user.',
                401,
                'invalid_token',
                $this->challenge('The MCP connection is not assigned to a valid user.')
            );
        }

        $suspendedUntil = $user->getAttribute('suspended_until');

        if ($suspendedUntil !== null && Carbon::parse($suspendedUntil)->isFuture()) {
            $this->recordFailure($ip, 'suspended_user', $connection->id);

            throw new McpAuthenticationException(
                'The MCP connection user is suspended.',
                403,
                'access_denied',
                null
            );
        }

        $this->clearFailures($ip);
        $this->touch($connection, $ip);

        return new AuthResult($connection, $user, $connection->token_prefix);
    }

    private function touch(McpConnection $connection, string $ip): void
    {
        $now = Carbon::now();

        if ($connection->last_used_at === null || $connection->last_used_at->lt($now->copy()->subMinute())) {
            McpConnection::query()->whereKey($connection->id)->update([
                'last_used_at' => $now,
                'last_used_ip' => $ip,
            ]);

            $connection->last_used_at = $now;
            $connection->last_used_ip = $ip;
        }
    }

    private function assertNotLockedOut(string $ip): void
    {
        try {
            $count = (int) $this->cache->get(self::FAILURE_PREFIX.$this->ipKey($ip), 0);
        } catch (\Throwable $e) {
            $this->logger->warning('MCP authentication failure cache unavailable.', ['exception' => $e]);

            return;
        }

        if ($count >= self::FAILURE_LIMIT) {
            throw new McpRateLimitException(self::FAILURE_TTL, self::FAILURE_LIMIT, 0);
        }
    }

    private function recordFailure(string $ip, string $reason, ?int $connectionId = null): void
    {
        try {
            $key = self::FAILURE_PREFIX.$this->ipKey($ip);

            if (! $this->cache->add($key, 1, self::FAILURE_TTL)) {
                $this->cache->increment($key);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('MCP authentication failure could not be recorded.', ['exception' => $e]);
        }

        $this->audit->authFailure($reason, $ip, $connectionId);
    }

    private function clearFailures(string $ip): void
    {
        try {
            $this->cache->forget(self::FAILURE_PREFIX.$this->ipKey($ip));
        } catch (\Throwable) {
            // Not fatal.
        }
    }

    private function ipKey(string $ip): string
    {
        return hash('sha256', $ip);
    }

    private function challenge(string $description): string
    {
        $description = str_replace(['"', '\\'], '', $description);

        return sprintf('Bearer realm="Flarum MCP", error="invalid_token", error_description="%s"', $description);
    }

    public static function clientIp(ServerRequestInterface $request): string
    {
        $server = $request->getServerParams();
        $ip = $server['REMOTE_ADDR'] ?? null;

        if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return '0.0.0.0';
    }
}
