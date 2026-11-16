<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use Carbon\Carbon;
use CengizKemec\FlarumMcp\Model\McpApproval;
use CengizKemec\FlarumMcp\Model\McpAuditLog;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final class AuditLogger
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function toolCall(
        McpRequestContext $context,
        ToolDefinition $tool,
        string $status,
        string $decision,
        ?string $errorCode,
        ?string $errorMessage,
        int $durationMs,
        array $meta = [],
        ?McpApproval $approval = null
    ): void {
        $this->record([
            'connection_id' => $context->connection->id,
            'user_id' => $context->actor->id,
            'operation' => $context->isApprovalExecution() ? 'approved_tool' : 'tool',
            'tool_name' => $tool->name,
            'permission' => $tool->permission,
            'permission_decision' => $decision,
            'status' => $status,
            'error_code' => $errorCode,
            'error_message' => $this->sanitize($errorMessage),
            'duration_ms' => $durationMs,
            'approval_id' => $approval?->id ?? $context->approval?->id,
            'ip_address' => $context->ipAddress,
            'request_id' => $this->requestId($context->request),
            'meta' => $meta,
        ]);
    }

    public function authFailure(string $reason, string $ipAddress, ?int $connectionId = null): void
    {
        $this->record([
            'connection_id' => $connectionId,
            'user_id' => null,
            'operation' => 'auth',
            'tool_name' => null,
            'permission' => null,
            'permission_decision' => 'denied',
            'status' => 'auth_failed',
            'error_code' => $reason,
            'error_message' => null,
            'duration_ms' => null,
            'approval_id' => null,
            'ip_address' => $ipAddress,
            'request_id' => null,
            'meta' => null,
        ]);
    }

    public function elementRead(
        McpRequestContext $context,
        string $operation,
        string $identifier,
        string $permission,
        string $status,
        int $durationMs,
        ?string $error = null
    ): void {
        $this->record([
            'connection_id' => $context->connection->id,
            'user_id' => $context->actor->id,
            'operation' => $operation,
            'tool_name' => $identifier,
            'permission' => $permission,
            'permission_decision' => $status === 'denied' ? 'denied' : 'allowed',
            'status' => $status,
            'error_code' => $error === null ? null : 'element_error',
            'error_message' => $this->sanitize($error),
            'duration_ms' => $durationMs,
            'approval_id' => null,
            'ip_address' => $context->ipAddress,
            'request_id' => $this->requestId($context->request),
            'meta' => null,
        ]);
    }

    public function approvalEvent(McpApproval $approval, string $action, ?int $adminId, ?string $error = null): void
    {        $this->record([
            'connection_id' => $approval->connection_id,
            'user_id' => $approval->user_id,
            'operation' => 'approval',
            'tool_name' => $approval->tool_name,
            'permission' => $approval->permission,
            'permission_decision' => $action,
            'status' => $error === null ? 'success' : 'error',
            'error_code' => $error === null ? null : 'approval_execution_failed',
            'error_message' => $this->sanitize($error),
            'duration_ms' => null,
            'approval_id' => $approval->id,
            'ip_address' => $approval->ip_address,
            'request_id' => null,
            'meta' => $adminId === null ? null : ['admin_id' => $adminId],
        ]);
    }

    public function securityEvent(string $operation, string $status, string $ipAddress, array $meta = []): void
    {
        $this->record([
            'connection_id' => null,
            'user_id' => null,
            'operation' => $operation,
            'tool_name' => null,
            'permission' => null,
            'permission_decision' => 'allowed',
            'status' => $status,
            'error_code' => null,
            'error_message' => null,
            'duration_ms' => null,
            'approval_id' => null,
            'ip_address' => $ipAddress,
            'request_id' => null,
            'meta' => $meta,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function record(array $data): ?McpAuditLog
    {
        try {
            $data['created_at'] = Carbon::now();

            return McpAuditLog::query()->create($data);
        } catch (\Throwable $e) {
            // Audit logging must never take the MCP endpoint down.
            $this->logger->error('Could not write MCP audit log.', ['exception' => $e]);

            return null;
        }
    }

    public function requestId(ServerRequestInterface $request): string
    {
        $provided = trim($request->getHeaderLine('X-Request-Id'));

        if ($provided !== '' && preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $provided)) {
            return $provided;
        }

        return bin2hex(random_bytes(12));
    }

    private function sanitize(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        $message = preg_replace('/fmcp_[A-Za-z0-9]+/', '[redacted-token]', $message) ?? $message;
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $message) ?? $message;

        if (function_exists('mb_substr')) {
            return mb_substr($message, 0, 2000);
        }

        return substr($message, 0, 2000);
    }
}
