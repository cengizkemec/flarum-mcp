<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Api\Controller\Admin;

use Carbon\Carbon;
use CengizKemec\FlarumMcp\Mcp\TokenService;
use CengizKemec\FlarumMcp\Model\McpApproval;
use CengizKemec\FlarumMcp\Model\McpConnection;
use CengizKemec\FlarumMcp\Permission\PermissionService;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ConnectionsController extends AdminController
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly PermissionService $permissions,
        private readonly SettingsRepositoryInterface $settings
    ) {
    }

    public function handle(Request $request): Response
    {
        return $this->run($request, function () use ($request): Response {
            return match ($request->getAttribute('routeName')) {
                'cengizkemec-mcp.admin.connections.index' => $this->index(),
                'cengizkemec-mcp.admin.connections.create' => $this->create($request),
                'cengizkemec-mcp.admin.connections.update' => $this->update($request),
                'cengizkemec-mcp.admin.connections.delete' => $this->delete($request),
                'cengizkemec-mcp.admin.connections.rotate' => $this->rotate($request),
                'cengizkemec-mcp.admin.connections.revoke' => $this->revoke($request),
                default => $this->fail('Unknown action.', 404, 'not_found'),
            };
        });
    }

    private function index(): Response
    {
        $connections = McpConnection::query()->with('user')->orderBy('name')->get();

        return $this->ok([
            'connections' => $connections->map(fn (McpConnection $connection): array => $connection->toAdminArray())->all(),
        ]);
    }

    private function create(Request $request): Response
    {
        $input = $this->input($request);
        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '' || mb_strlen($name) > 100) {
            throw new ValidationException(['name' => 'A name between 1 and 100 characters is required.']);
        }

        $user = $this->user($input);
        $profile = $this->profile($input);
        $custom = $this->customPermissions($input, $profile);
        $expiresInDays = $this->expiresInDays($input);
        $token = $this->tokens->generate();

        $connection = McpConnection::query()->create([
            'name' => $name,
            'description' => $this->nullableString($input['description'] ?? null, 1000),
            'user_id' => $user->id,
            'permission_profile' => $profile,
            'custom_permissions' => $custom,
            'token_hash' => $token['hash'],
            'token_prefix' => $token['prefix'],
            'token_expires_at' => $expiresInDays !== null ? Carbon::now()->addDays($expiresInDays) : null,
            'status' => 'active',
            'rate_limit_per_minute' => $this->rateLimit($input, 'rateLimitPerMinute', 'default_rate_limit_per_minute', 60),
            'rate_limit_per_hour' => $this->rateLimit($input, 'rateLimitPerHour', 'default_rate_limit_per_hour', 1000),
            'rate_limit_per_day' => $this->rateLimit($input, 'rateLimitPerDay', 'default_rate_limit_per_day', 10000),
            'require_approval' => $this->bool($input['requireApproval'] ?? null, false),
        ]);

        $connection->load('user');

        return $this->ok([
            'connection' => $connection->toAdminArray(),
            'token' => $token['plain'],
            'message' => 'Store this token now. It cannot be shown again.',
        ], 201);
    }

    private function update(Request $request): Response
    {
        $connection = $this->connection($request);
        $input = $this->input($request);

        if (array_key_exists('name', $input)) {
            $name = trim((string) $input['name']);

            if ($name === '' || mb_strlen($name) > 100) {
                throw new ValidationException(['name' => 'A name between 1 and 100 characters is required.']);
            }

            $connection->name = $name;
        }

        if (array_key_exists('description', $input)) {
            $connection->description = $this->nullableString($input['description'], 1000);
        }

        if (array_key_exists('userId', $input)) {
            $connection->user_id = $this->user($input)->id;
        }

        if (array_key_exists('permissionProfile', $input)) {
            $connection->permission_profile = $this->profile($input);
        }

        if (array_key_exists('customPermissions', $input)) {
            $connection->custom_permissions = $this->customPermissions($input, $connection->permission_profile);
        }

        if (array_key_exists('expiresInDays', $input)) {
            $expiresInDays = $this->expiresInDays($input);
            $connection->token_expires_at = $expiresInDays !== null ? Carbon::now()->addDays($expiresInDays) : null;
        }

        if (array_key_exists('rateLimitPerMinute', $input)) {
            $connection->rate_limit_per_minute = $this->rateLimit($input, 'rateLimitPerMinute', 'default_rate_limit_per_minute', 60);
        }

        if (array_key_exists('rateLimitPerHour', $input)) {
            $connection->rate_limit_per_hour = $this->rateLimit($input, 'rateLimitPerHour', 'default_rate_limit_per_hour', 1000);
        }

        if (array_key_exists('rateLimitPerDay', $input)) {
            $connection->rate_limit_per_day = $this->rateLimit($input, 'rateLimitPerDay', 'default_rate_limit_per_day', 10000);
        }

        if (array_key_exists('requireApproval', $input)) {
            $connection->require_approval = $this->bool($input['requireApproval']);
        }

        if (array_key_exists('status', $input)) {
            $status = (string) $input['status'];

            if (! in_array($status, ['active', 'disabled'], true)) {
                throw new ValidationException(['status' => 'Status must be active or disabled.']);
            }

            if ($status === 'active' && $connection->token_revoked_at !== null) {
                throw new ValidationException(['status' => 'Rotate the token to reactivate a revoked connection.']);
            }

            $connection->status = $status;
        }

        $connection->save();
        $connection->load('user');

        return $this->ok(['connection' => $connection->toAdminArray()]);
    }

    private function delete(Request $request): Response
    {
        $connection = $this->connection($request);

        McpApproval::query()
            ->where('connection_id', $connection->id)
            ->where('status', McpApproval::STATUS_PENDING)
            ->delete();

        $connection->delete();

        return $this->ok(['deleted' => true, 'id' => (int) $this->param($request, 'id')]);
    }

    private function rotate(Request $request): Response
    {
        $connection = $this->connection($request);
        $input = $this->input($request);
        $expiresInDays = array_key_exists('expiresInDays', $input) ? $this->expiresInDays($input) : null;

        $plain = $this->tokens->rotate($connection, $expiresInDays);
        $connection->save();
        $connection->load('user');

        return $this->ok([
            'connection' => $connection->toAdminArray(),
            'token' => $plain,
            'message' => 'Store this token now. It cannot be shown again.',
        ]);
    }

    private function revoke(Request $request): Response
    {
        $connection = $this->connection($request);

        $this->tokens->revoke($connection);
        $connection->save();
        $connection->load('user');

        return $this->ok(['connection' => $connection->toAdminArray()]);
    }

    private function connection(Request $request): McpConnection
    {
        return McpConnection::query()->findOrFail((int) $this->param($request, 'id'));
    }

    private function user(array $input): User
    {
        $id = (int) ($input['userId'] ?? 0);

        if ($id < 1) {
            throw new ValidationException(['userId' => 'A Flarum user is required.']);
        }

        return User::query()->findOrFail($id);
    }

    private function profile(array $input): string
    {
        $provided = array_key_exists('permissionProfile', $input);
        $profile = (string) (
            $input['permissionProfile']
            ?? $this->settings->get('cengizkemec-mcp.default_permission_profile', 'minimum')
        );

        if (! $provided && $this->permissions->profile($profile) === null) {
            // The configured default may point at a deleted custom profile.
            $profile = 'minimum';
        }

        if ($this->permissions->profile($profile) === null) {
            throw new ValidationException(['permissionProfile' => 'Unknown permission profile.']);
        }

        return $profile;
    }

    /**
     * Custom permissions are an allowlist intersected with the profile. They
     * can restrict a connection, never expand it.
     */
    private function customPermissions(array $input, string $profile): ?array
    {
        if (! array_key_exists('customPermissions', $input) || ! is_array($input['customPermissions'])) {
            return null;
        }

        $allowed = $this->permissions->profilePermissions($profile);
        $requested = $this->permissions->sanitizePermissions($input['customPermissions']);

        return array_values(array_intersect($allowed, $requested));
    }

    private function expiresInDays(array $input): ?int
    {
        if (! array_key_exists('expiresInDays', $input) || $input['expiresInDays'] === null || $input['expiresInDays'] === '') {
            return null;
        }

        $days = (int) $input['expiresInDays'];

        if ($days < 1 || $days > 3650) {
            throw new ValidationException(['expiresInDays' => 'Expiry must be between 1 and 3650 days, or empty for no expiry.']);
        }

        return $days;
    }

    private function rateLimit(array $input, string $key, string $defaultKey, int $default): int
    {
        $value = array_key_exists($key, $input)
            ? (int) $input[$key]
            : (int) $this->settings->get('cengizkemec-mcp.'.$defaultKey, $default);

        if ($value < 0 || $value > 1000000) {
            throw new ValidationException([$key => 'Rate limit must be between 0 and 1,000,000.']);
        }

        return $value;
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        if (mb_strlen($value) > $max) {
            throw new ValidationException(['description' => "Text must not exceed $max characters."]);
        }

        return $value;
    }
}
