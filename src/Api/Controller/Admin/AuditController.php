<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Api\Controller\Admin;

use Carbon\Carbon;
use CengizKemec\FlarumMcp\Model\McpAuditLog;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuditController extends AdminController
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings
    ) {
    }

    public function handle(Request $request): Response
    {
        return $this->run($request, function () use ($request): Response {
            $query = McpAuditLog::query()->with(['mcpConnection', 'user'])->orderByDesc('id');

            if ($connectionId = $this->param($request, 'connectionId')) {
                $query->where('connection_id', (int) $connectionId);
            }

            if ($toolName = $this->param($request, 'toolName')) {
                $query->where('tool_name', (string) $toolName);
            }

            if ($status = $this->param($request, 'status')) {
                $query->where('status', (string) $status);
            }

            if ($operation = $this->param($request, 'operation')) {
                $query->where('operation', (string) $operation);
            }

            if ($search = $this->param($request, 'q')) {
                $search = trim((string) $search);
                $query->where(function ($query) use ($search): void {
                    $query->where('tool_name', 'like', '%'.$search.'%')
                        ->orWhere('error_message', 'like', '%'.$search.'%');
                });
            }

            if ($from = $this->param($request, 'from')) {
                $query->where('created_at', '>=', $this->date((string) $from, 'from'));
            }

            if ($to = $this->param($request, 'to')) {
                $query->where('created_at', '<=', $this->date((string) $to, 'to'));
            }

            $limit = max(1, min(100, (int) ($this->param($request, 'limit') ?? 25)));
            $offset = max(0, (int) ($this->param($request, 'offset') ?? 0));

            $total = (clone $query)->count();
            $logs = $query->skip($offset)->take($limit)->get();

            return $this->ok([
                'logs' => $logs->map(fn (McpAuditLog $log): array => $log->toAdminArray())->all(),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'retentionDays' => (int) $this->settings->get('cengizkemec-mcp.audit_retention_days', 90),
            ]);
        });
    }

    private function date(string $value, string $field): Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            throw new ValidationException([$field => 'Invalid date.']);
        }
    }
}
