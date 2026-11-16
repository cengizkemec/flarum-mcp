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
use CengizKemec\FlarumMcp\Model\McpApproval;
use CengizKemec\FlarumMcp\Model\McpConnection;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

final class ApprovalService
{
    public const STATUS_PENDING = McpApproval::STATUS_PENDING;
    public const STATUS_APPROVED = McpApproval::STATUS_APPROVED;
    public const STATUS_REJECTED = McpApproval::STATUS_REJECTED;
    public const STATUS_EXPIRED = McpApproval::STATUS_EXPIRED;
    public const STATUS_EXECUTED = McpApproval::STATUS_EXECUTED;
    public const STATUS_FAILED = McpApproval::STATUS_FAILED;

    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly AuditLogger $audit
    ) {
    }

    public function create(
        McpConnection $connection,
        User $user,
        ToolDefinition $tool,
        array $arguments,
        McpRequestContext $context
    ): McpApproval {
        $ttlHours = max(1, (int) $this->settings->get('cengizkemec-mcp.approval_ttl_hours', 24));

        /** @var McpApproval $approval */
        $approval = McpApproval::query()->create([
            'connection_id' => $connection->id,
            'user_id' => $user->id,
            'tool_name' => $tool->name,
            'permission' => $tool->permission,
            'arguments' => $arguments,
            'status' => self::STATUS_PENDING,
            'ip_address' => $context->ipAddress,
            'requested_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addHours($ttlHours),
        ]);

        $this->audit->approvalEvent($approval, 'requested', null);

        return $approval;
    }

    public function approve(McpApproval $approval, User $admin, ?string $note = null): void
    {
        $this->assertPending($approval);
        $admin->assertAdmin();

        $approval->status = self::STATUS_APPROVED;
        $approval->decided_at = Carbon::now();
        $approval->decided_by_id = $admin->id;
        $approval->decision_note = $note;
        $approval->save();

        $this->audit->approvalEvent($approval, 'approved', $admin->id);
    }

    public function reject(McpApproval $approval, User $admin, ?string $note = null): void
    {
        $this->assertPending($approval);
        $admin->assertAdmin();

        $approval->status = self::STATUS_REJECTED;
        $approval->decided_at = Carbon::now();
        $approval->decided_by_id = $admin->id;
        $approval->decision_note = $note;
        $approval->save();

        $this->audit->approvalEvent($approval, 'rejected', $admin->id);
    }

    public function markExecuted(McpApproval $approval): void
    {
        $approval->status = self::STATUS_EXECUTED;
        $approval->executed_at = Carbon::now();
        $approval->error = null;
        $approval->save();

        $this->audit->approvalEvent($approval, 'executed', $approval->decided_by_id);
    }

    public function markFailed(McpApproval $approval, string $error): void
    {
        $approval->status = self::STATUS_FAILED;
        $approval->executed_at = Carbon::now();
        $approval->error = substr($error, 0, 2000);
        $approval->save();

        $this->audit->approvalEvent($approval, 'failed', $approval->decided_by_id, $error);
    }

    public function expirePending(): int
    {
        return McpApproval::query()
            ->where('status', self::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->update(['status' => self::STATUS_EXPIRED, 'updated_at' => Carbon::now()]);
    }

    public function pendingCount(): int
    {
        return McpApproval::query()->where('status', self::STATUS_PENDING)->count();
    }

    public function findForExecution(int $id): McpApproval
    {
        /** @var McpApproval|null $approval */
        $approval = McpApproval::query()->with(['mcpConnection', 'user'])->find($id);

        if ($approval === null) {
            throw new McpAuthenticationException('Approval request not found.', 404, 'not_found', null);
        }

        if ($approval->status !== self::STATUS_APPROVED) {
            throw new McpAuthenticationException('Approval request is not approved.', 409, 'not_approved', null);
        }

        if ($approval->isExpired()) {
            throw new McpAuthenticationException('Approval request has expired.', 410, 'expired', null);
        }

        return $approval;
    }

    private function assertPending(McpApproval $approval): void
    {
        if ($approval->status !== self::STATUS_PENDING) {
            throw new McpAuthenticationException('Approval request is not pending.', 409, 'not_pending', null);
        }

        if ($approval->isExpired()) {
            $approval->status = self::STATUS_EXPIRED;
            $approval->save();

            throw new McpAuthenticationException('Approval request has expired.', 410, 'expired', null);
        }
    }
}
