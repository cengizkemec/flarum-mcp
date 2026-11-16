<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Skill;

use CengizKemec\FlarumMcp\Exception\McpSkillException;

/**
 * Fetches a remote SKILL.md with SSRF, redirect and size protections.
 *
 * The host is resolved and its address is pinned with CURLOPT_RESOLVE, so a
 * second DNS answer cannot redirect the request to a private address. Only
 * HTTPS is accepted. Redirects are followed manually, at most three times, and
 * each hop is validated again.
 */
final class RemoteFetcher
{
    private const MAX_REDIRECTS = 3;

    public function fetch(string $url, int $maxBytes = SkillParser::MAX_BYTES): string
    {
        if (! extension_loaded('curl')) {
            throw new McpSkillException('Remote Skill installation requires the PHP cURL extension.', 'curl_missing');
        }

        $current = $this->validateUrl($url);

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            [$body, $status, $location, $contentType] = $this->request($current, $maxBytes);

            if ($status >= 300 && $status < 400) {
                if ($location === null || $location === '') {
                    throw new McpSkillException('Remote Skill server returned a redirect without a location.', 'invalid_redirect');
                }

                $current = $this->validateUrl($this->resolveUrl($current, $location));

                continue;
            }

            if ($status !== 200) {
                throw new McpSkillException("Remote Skill server returned HTTP $status.", 'remote_fetch_failed');
            }

            if ($contentType !== null && ! $this->isAllowedContentType($contentType)) {
                throw new McpSkillException('Remote Skill URL did not return Markdown or plain text.', 'invalid_content_type');
            }

            if ($body === '') {
                throw new McpSkillException('Remote SKILL.md was empty.', 'empty_skill');
            }

            if (strlen($body) > $maxBytes) {
                throw new McpSkillException('Remote SKILL.md exceeds the size limit.', 'skill_too_large');
            }

            return $body;
        }

        throw new McpSkillException('Too many redirects while fetching SKILL.md.', 'too_many_redirects');
    }

    private function validateUrl(string $url): string
    {
        if (strlen($url) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            throw new McpSkillException('Invalid Skill URL.', 'invalid_url');
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https') {
            throw new McpSkillException('Only HTTPS Skill URLs are allowed.', 'invalid_url');
        }

        if (empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new McpSkillException('Invalid Skill URL host.', 'invalid_url');
        }

        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            throw new McpSkillException('Skill URLs may only use port 443.', 'invalid_url');
        }

        $host = $parts['host'];

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        $this->assertPublicHost($host);

        return $url;
    }

    private function assertPublicHost(string $host): void
    {
        $addresses = [];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses[] = $host;
        } else {
            $ipv4 = gethostbynamel($host);

            if (is_array($ipv4)) {
                $addresses = array_merge($addresses, $ipv4);
            }

            if (function_exists('dns_get_record')) {
                $ipv6 = @dns_get_record($host, DNS_AAAA);

                if (is_array($ipv6)) {
                    foreach ($ipv6 as $record) {
                        if (isset($record['ipv6'])) {
                            $addresses[] = $record['ipv6'];
                        }
                    }
                }
            }
        }

        $addresses = array_values(array_unique($addresses));

        if ($addresses === []) {
            throw new McpSkillException('Could not resolve Skill URL host.', 'dns_failed');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicIp($address)) {
                throw new McpSkillException('Skill URL resolves to a private or reserved address.', 'ssrf_blocked');
            }
        }
    }

    private function isPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * @return array{0: string, 1: int, 2: string|null, 3: string|null}
     */
    private function request(string $url, int $maxBytes): array
    {
        $parts = parse_url($url);
        $host = $parts['host'];
        $bareHost = str_starts_with($host, '[') ? substr($host, 1, -1) : $host;

        $ip = $this->firstPublicAddress($bareHost);

        if ($ip === null) {
            throw new McpSkillException('Could not resolve Skill URL host.', 'dns_failed');
        }

        $resolve = str_contains($ip, ':')
            ? sprintf('%s:443:[%s]', $bareHost, $ip)
            : sprintf('%s:443:%s', $bareHost, $ip);

        $body = '';
        $status = 0;
        $location = null;
        $contentType = null;
        $tooLarge = false;

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'Flarum-MCP/1.0 (+https://github.com/cengizkemec/flarum-mcp)',
            CURLOPT_HTTPHEADER => ['Accept: text/markdown, text/plain;q=0.9, application/octet-stream;q=0.5'],
            CURLOPT_RESOLVE => [$resolve],
            CURLOPT_HEADERFUNCTION => function ($curl, string $header) use (&$location, &$contentType): int {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, 9));
                } elseif (stripos($header, 'Content-Type:') === 0) {
                    $contentType = trim(substr($header, 13));
                }

                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;

                    return 0;
                }

                $body .= $chunk;

                return strlen($chunk);
            },
        ]);

        curl_exec($ch);

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        curl_close($ch);

        if ($tooLarge) {
            throw new McpSkillException('Remote SKILL.md exceeds the size limit.', 'skill_too_large');
        }

        if ($errno !== 0 && $status === 0) {
            throw new McpSkillException("Could not fetch remote SKILL.md: $error", 'remote_fetch_failed');
        }

        return [$body, $status, $location, $contentType];
    }

    private function firstPublicAddress(string $host): ?string
    {
        $addresses = [];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses[] = $host;
        } else {
            $ipv4 = gethostbynamel($host);

            if (is_array($ipv4)) {
                $addresses = array_merge($addresses, $ipv4);
            }

            if (function_exists('dns_get_record')) {
                $ipv6 = @dns_get_record($host, DNS_AAAA);

                if (is_array($ipv6)) {
                    foreach ($ipv6 as $record) {
                        if (isset($record['ipv6'])) {
                            $addresses[] = $record['ipv6'];
                        }
                    }
                }
            }
        }

        foreach (array_unique($addresses) as $address) {
            if ($this->isPublicIp($address)) {
                return $address;
            }
        }

        return null;
    }

    private function resolveUrl(string $base, string $location): string
    {
        if (str_starts_with($location, 'https://')) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $path = $parts['path'] ?? '/';
        $directory = substr($path, 0, strrpos($path, '/') + 1);

        return $origin.$directory.$location;
    }

    private function isAllowedContentType(string $contentType): bool
    {
        $contentType = strtolower(trim(explode(';', $contentType)[0]));

        return in_array($contentType, [
            'text/plain',
            'text/markdown',
            'text/x-markdown',
            'application/octet-stream',
        ], true);
    }
}
