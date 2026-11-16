<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use Carbon\Carbon;
use CengizKemec\FlarumMcp\Model\McpConnection;

/**
 * Generates and hashes bearer tokens.
 *
 * Tokens use 256 bits of entropy from random_bytes(). Only the SHA-256 hash is
 * stored; the plaintext value is returned exactly once to the administrator.
 */
final class TokenService
{
    public const PREFIX = 'fmcp_';

    /**
     * @return array{plain: string, hash: string, prefix: string}
     */
    public function generate(): array
    {
        $plain = self::PREFIX.bin2hex(random_bytes(32));

        return [
            'plain' => $plain,
            'hash' => $this->hash($plain),
            'prefix' => substr($plain, 0, 12),
        ];
    }

    public function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Rotate the token of a connection. Returns the plaintext token, which must
     * be shown to the administrator immediately and never stored.
     */
    public function rotate(McpConnection $connection, ?int $expiresInDays = null): string
    {
        $token = $this->generate();

        $connection->token_hash = $token['hash'];
        $connection->token_prefix = $token['prefix'];
        $connection->token_revoked_at = null;
        $connection->token_expires_at = $expiresInDays !== null
            ? Carbon::now()->addDays(max(1, $expiresInDays))
            : null;
        $connection->status = 'active';

        return $token['plain'];
    }

    public function revoke(McpConnection $connection): void
    {
        // Rotating the hash invalidates the old token even if the revocation
        // timestamp is later ignored by an old cache entry.
        $connection->token_hash = $this->hash(self::PREFIX.bin2hex(random_bytes(32)));
        $connection->token_prefix = self::PREFIX.'revoked';
        $connection->token_revoked_at = Carbon::now();
        $connection->status = 'disabled';
    }
}
