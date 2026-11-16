<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Api\Controller\Admin;

use CengizKemec\FlarumMcp\Mcp\ApprovalService;
use CengizKemec\FlarumMcp\Mcp\ToolExecutor;
use CengizKemec\FlarumMcp\Model\McpApproval;
use Flarum\Foundation\ValidationException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ApprovalsController extends AdminController
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly ToolExecutor $executor
    ) {
    }

    public function handle(Request $request): Response
    {
        return $this->run($request, function () use ($request): Response {
            return match ($request->getAttribute('routeName')) {
                'cengizkemec-mcp.admin.approvals.index' => $this->index($request),
                'cengizkemec-mcp.admin.approvals.approve' => $this->approve($request),
                'cengizkemec-mcp.admin.approvals.reject' => $this->reject($request),
                default => $this->fail('Unknown action.', 404, 'not_found'),
            };
        });
    }

    private function index(Request $request): Response
    {
        $this->approvals->expirePending();

        $query = McpApproval::query()->with(['mcpConnection', 'user', 'decidedBy'])->orderByDesc('id');

        $status = $this->param($request, 'status');

        if ($status !== null && $status !== '') {
            $query->where('status', (string) $status);
        }

        $connectionId = $this->param($request, 'connectionId');

        if ($connectionId) {
            $query->where('connection_id', (int) $connectionId);
        }

        $limit = max(1, min(100, (int) ($this->param($request, 'limit') ?? 25)));
        $offset = max(0, (int) ($this->param($request, 'offset') ?? 0));

        $total = (clone $query)->count();
        $approvals = $query->skip($offset)->take($limit)->get();

        return $this->ok([
            'approvals' => $approvals->map(fn (McpApproval $approval): array => $approval->toAdminArray(true))->all(),
            'total' => $total,
            'pending' => $this->approvals->pendingCount(),
        ]);
    }

    private function approve(Request $request): Response
    {
        $admin = $this->admin($request);
        $approval = McpApproval::query()->with(['mcpConnection', 'user'])->findOrFail((int) $this->param($request, 'id'));
        $note = $this->input($request)['note'] ?? null;

        $this->approvals->approve($approval, $admin, $note === null ? null : (string) $note);

        $result = $this->executor->executeApproved($approval, $request, $admin);
        $approval->refresh();

        return $this->ok([
            'approval' => $approval->toAdminArray(true),
            'result' => $result->jsonSerialize(),
        ]);
    }

    private function reject(Request $request): Response
    {
        $admin = $this->admin($request);
        $approval = McpApproval::query()->with(['mcpConnection', 'user'])->findOrFail((int) $this->param($request, 'id'));
        $note = $this->input($request)['note'] ?? null;

        if ($note !== null && mb_strlen((string) $note) > 1000) {
            throw new ValidationException(['note' => 'The note must not exceed 1,000 characters.']);
        }

        $this->approvals->reject($approval, $admin, $note === null ? null : (string) $note);
        $approval->refresh();

        return $this->ok(['approval' => $approval->toAdminArray(true)]);
    }
}
