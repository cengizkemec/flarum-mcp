<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Api\Controller\Admin;

use CengizKemec\FlarumMcp\Permission\PermissionService;
use Flarum\Foundation\Config;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SettingsController extends AdminController
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly PermissionService $permissions,
        private readonly Config $config
    ) {
    }

    public function handle(Request $request): Response
    {
        return $this->run($request, function () use ($request): Response {
            return match ($request->getAttribute('routeName')) {
                'cengizkemec-mcp.admin.settings.index' => $this->ok(['settings' => $this->settingsData()]),
                'cengizkemec-mcp.admin.settings.update' => $this->update($request),
                default => $this->fail('Unknown action.', 404, 'not_found'),
            };
        });
    }

    private function update(Request $request): Response
    {
        $input = $this->input($request);

        foreach ([
            'enabled' => 'bool',
            'maxRequestBytes' => 'int',
            'sessionTtl' => 'int',
            'approvalTtlHours' => 'int',
            'auditRetentionDays' => 'int',
            'defaultRateLimitPerMinute' => 'int',
            'defaultRateLimitPerHour' => 'int',
            'defaultRateLimitPerDay' => 'int',
            'defaultPermissionProfile' => 'profile',
            'allowedHosts' => 'hosts',
            'allowedOrigins' => 'origins',
        ] as $key => $type) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $settingKey = 'cengizkemec-mcp.'.match ($key) {
                'maxRequestBytes' => 'max_request_bytes',
                'sessionTtl' => 'session_ttl',
                'approvalTtlHours' => 'approval_ttl_hours',
                'auditRetentionDays' => 'audit_retention_days',
                'defaultRateLimitPerMinute' => 'default_rate_limit_per_minute',
                'defaultRateLimitPerHour' => 'default_rate_limit_per_hour',
                'defaultRateLimitPerDay' => 'default_rate_limit_per_day',
                'defaultPermissionProfile' => 'default_permission_profile',
                'allowedHosts' => 'allowed_hosts',
                'allowedOrigins' => 'allowed_origins',
                'enabled' => 'enabled',
            };

            $value = match ($type) {
                'bool' => $this->bool($input[$key]) ? '1' : '0',
                'int' => (string) $this->integer($input[$key], $key),
                'profile' => $this->profile($input[$key]),
                'hosts' => json_encode($this->hosts($input[$key])),
                'origins' => json_encode($this->origins($input[$key])),
            };

            $this->settings->set($settingKey, $value);
        }

        return $this->ok(['settings' => $this->settingsData()]);
    }

    private function settingsData(): array
    {
        return [
            'enabled' => (bool) $this->settings->get('cengizkemec-mcp.enabled', true),
            'endpoint' => rtrim((string) $this->config->url(), '/').'/mcp',
            'maxRequestBytes' => (int) $this->settings->get('cengizkemec-mcp.max_request_bytes', 4194304),
            'sessionTtl' => (int) $this->settings->get('cengizkemec-mcp.session_ttl', 3600),
            'approvalTtlHours' => (int) $this->settings->get('cengizkemec-mcp.approval_ttl_hours', 24),
            'auditRetentionDays' => (int) $this->settings->get('cengizkemec-mcp.audit_retention_days', 90),
            'defaultPermissionProfile' => (string) $this->settings->get('cengizkemec-mcp.default_permission_profile', 'minimum'),
            'defaultRateLimits' => [
                'minute' => (int) $this->settings->get('cengizkemec-mcp.default_rate_limit_per_minute', 60),
                'hour' => (int) $this->settings->get('cengizkemec-mcp.default_rate_limit_per_hour', 1000),
                'day' => (int) $this->settings->get('cengizkemec-mcp.default_rate_limit_per_day', 10000),
            ],
            'allowedHosts' => $this->decodeArray('cengizkemec-mcp.allowed_hosts'),
            'allowedOrigins' => $this->decodeArray('cengizkemec-mcp.allowed_origins'),
        ];
    }

    private function integer(mixed $value, string $key): int
    {
        $int = (int) $value;

        $limits = [
            'maxRequestBytes' => [16384, 16777216],
            'sessionTtl' => [300, 86400],
            'approvalTtlHours' => [1, 720],
            'auditRetentionDays' => [1, 3650],
            'defaultRateLimitPerMinute' => [0, 1000000],
            'defaultRateLimitPerHour' => [0, 1000000],
            'defaultRateLimitPerDay' => [0, 1000000],
        ];

        [$min, $max] = $limits[$key] ?? [0, PHP_INT_MAX];

        if ($int < $min || $int > $max) {
            throw new ValidationException([$key => "Value must be between $min and $max."]);
        }

        return $int;
    }

    private function profile(mixed $value): string
    {
        $profile = (string) $value;

        if ($this->permissions->profile($profile) === null) {
            throw new ValidationException(['defaultPermissionProfile' => 'Unknown permission profile.']);
        }

        return $profile;
    }

    /**
     * @return string[]
     */
    private function hosts(mixed $value): array
    {
        if (! is_array($value)) {
            throw new ValidationException(['allowedHosts' => 'Allowed hosts must be an array.']);
        }

        $hosts = [];

        foreach ($value as $host) {
            $host = strtolower(trim((string) $host));

            if ($host === '') {
                continue;
            }

            if ($host === '*' || ! preg_match('/^[a-z0-9.\-\[\]:]+$/', $host)) {
                throw new ValidationException(['allowedHosts' => 'Invalid hostname.']);
            }

            $hosts[] = $host;
        }

        if (count($hosts) > 50) {
            throw new ValidationException(['allowedHosts' => 'At most 50 hosts may be listed.']);
        }

        return array_values(array_unique($hosts));
    }

    /**
     * @return string[]
     */
    private function origins(mixed $value): array
    {
        if (! is_array($value)) {
            throw new ValidationException(['allowedOrigins' => 'Allowed origins must be an array.']);
        }

        $origins = [];

        foreach ($value as $origin) {
            $origin = rtrim(trim((string) $origin), '/');

            if ($origin === '') {
                continue;
            }

            if (! preg_match('#^https?://[a-z0-9.\-\[\]:]+$#i', $origin)) {
                throw new ValidationException(['allowedOrigins' => 'Origins must include http(s):// and a host.']);
            }

            $origins[] = $origin;
        }

        if (count($origins) > 50) {
            throw new ValidationException(['allowedOrigins' => 'At most 50 origins may be listed.']);
        }

        return array_values(array_unique($origins));
    }

    /**
     * @return array<int, string>
     */
    private function decodeArray(string $key): array
    {
        $raw = $this->settings->get($key);
        $decoded = is_string($raw) ? json_decode($raw, true) : [];

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
