<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Mcp;

use CengizKemec\FlarumMcp\Model\McpApproval;
use CengizKemec\FlarumMcp\Permission\PermissionService;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * The only place a tool handler is invoked.
 *
 * It enforces the effective permission again at execution time, creates
 * approval requests when required, converts failures into MCP tool errors,
 * and writes an audit record for every attempt.
 */
final class ToolExecutor
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly ApprovalService $approvals,
        private readonly AuditLogger $audit,
        private readonly ToolRegistry $tools,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function execute(
        ToolDefinition $tool,
        array $arguments,
        McpRequestContext $context,
        ?McpApproval $approval = null
    ): CallToolResult {
        $started = hrtime(true);

        if (! $this->permissions->canUseTool($context->connection, $tool)) {
            return $this->fail(
                $tool,
                $context,
                $started,
                'permission_denied',
                $this->trans('cengizkemec-mcp.lib.error.permission_denied'),
                $approval
            );
        }

        if ($approval === null && $this->permissions->requiresApproval($context->connection, $tool)) {
            $approval = $this->approvals->create(
                $context->connection,
                $context->actor,
                $tool,
                $arguments,
                $context
            );

            $result = $this->result([
                'status' => 'approval_required',
                'approval_id' => $approval->id,
                'tool' => $tool->name,
                'expires_at' => $approval->expires_at?->toIso8601String(),
                'message' => $this->trans('cengizkemec-mcp.lib.error.approval_required'),
            ]);

            $this->audit->toolCall(
                $context,
                $tool,
                'pending',
                'approval_required',
                null,
                null,
                $this->elapsedMs($started),
                $this->auditMeta($arguments),
                $approval
            );

            return $result;
        }

        try {
            $raw = ($tool->handler)($arguments, $context);
            $result = $this->normalize($raw);

            $this->audit->toolCall(
                $context,
                $tool,
                $result->isError ? 'error' : 'success',
                'allowed',
                $result->isError ? 'tool_error' : null,
                $result->isError ? $this->firstText($result) : null,
                $this->elapsedMs($started),
                $this->auditMeta($arguments),
                $approval
            );

            return $result;
        } catch (PermissionDeniedException) {
            return $this->fail(
                $tool,
                $context,
                $started,
                'permission_denied',
                $this->trans('cengizkemec-mcp.lib.error.permission_denied'),
                $approval
            );
        } catch (ModelNotFoundException) {
            return $this->fail(
                $tool,
                $context,
                $started,
                'not_found',
                $this->trans('cengizkemec-mcp.lib.error.not_found'),
                $approval
            );
        } catch (ValidationException $e) {
            $details = array_merge($e->getAttributes(), $e->getRelationships());
            $message = $this->trans('cengizkemec-mcp.lib.error.validation_failed');

            if ($details !== []) {
                $message .= ' '.implode(' ', array_map('strval', $details));
            }

            return $this->fail(
                $tool,
                $context,
                $started,
                'validation_failed',
                $message,
                $approval
            );
        } catch (\Throwable $e) {
            $this->logger->error('MCP tool execution failed.', [
                'tool' => $tool->name,
                'connection_id' => $context->connection->id,
                'exception' => $e,
            ]);

            return $this->fail(
                $tool,
                $context,
                $started,
                'execution_failed',
                $this->trans('cengizkemec-mcp.lib.error.execution_failed'),
                $approval
            );
        }
    }

    /**
     * Execute an approved request. The approval decision is re-validated
     * against the current permissions and connection state.
     */
    public function executeApproved(McpApproval $approval, ServerRequestInterface $request, User $admin): CallToolResult
    {
        $admin->assertAdmin();

        $approval = $this->approvals->findForExecution($approval->id);
        $tool = $this->tools->get($approval->tool_name);

        if ($tool === null) {
            $this->approvals->markFailed($approval, 'Tool is no longer registered.');

            return $this->result([
                'status' => 'failed',
                'message' => $this->trans('cengizkemec-mcp.lib.error.not_found'),
            ], true);
        }

        $connection = $approval->mcpConnection;

        if ($connection === null || ! $connection->isUsable()) {
            $this->approvals->markFailed($approval, 'Connection is no longer active.');

            return $this->result([
                'status' => 'failed',
                'message' => $this->trans('cengizkemec-mcp.lib.error.permission_denied'),
            ], true);
        }

        $connection->loadMissing('user');

        if ($connection->user === null) {
            $this->approvals->markFailed($approval, 'Connection user is missing.');

            return $this->result([
                'status' => 'failed',
                'message' => $this->trans('cengizkemec-mcp.lib.error.permission_denied'),
            ], true);
        }

        $context = new McpRequestContext(
            $connection,
            $connection->user,
            $request,
            $approval->ip_address ?? '0.0.0.0',
            'approval',
            $approval
        );

        $result = $this->execute($tool, $approval->arguments ?? [], $context, $approval);

        if ($result->isError) {
            $this->approvals->markFailed($approval, $this->firstText($result) ?? 'Tool execution failed.');
        } else {
            $this->approvals->markExecuted($approval);
        }

        return $result;
    }

    private function fail(
        ToolDefinition $tool,
        McpRequestContext $context,
        int $started,
        string $code,
        string $message,
        ?McpApproval $approval
    ): CallToolResult {
        $this->audit->toolCall(
            $context,
            $tool,
            'error',
            $code === 'permission_denied' ? 'denied' : 'allowed',
            $code,
            $message,
            $this->elapsedMs($started),
            [],
            $approval
        );

        return $this->result([
            'status' => 'error',
            'code' => $code,
            'message' => $message,
        ], true);
    }

    private function normalize(mixed $raw): CallToolResult
    {
        if ($raw instanceof CallToolResult) {
            return $raw;
        }

        if (is_array($raw)) {
            $json = $this->json($raw);

            if ($raw !== [] && ! array_is_list($raw)) {
                return new CallToolResult([new TextContent($json)], structuredContent: $raw);
            }

            return new CallToolResult([new TextContent($json)]);
        }

        if ($raw === null) {
            return new CallToolResult([new TextContent('null')]);
        }

        if (is_bool($raw)) {
            return new CallToolResult([new TextContent($raw ? 'true' : 'false')]);
        }

        if (is_scalar($raw)) {
            return new CallToolResult([new TextContent((string) $raw)]);
        }

        return new CallToolResult([new TextContent($this->json($raw))]);
    }

    private function result(array $data, bool $isError = false): CallToolResult
    {
        return new CallToolResult([new TextContent($this->json($data))], $isError);
    }

    private function json(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    private function firstText(CallToolResult $result): ?string
    {
        foreach ($result->content as $content) {
            if ($content instanceof TextContent) {
                return is_string($content->text) ? $content->text : null;
            }
        }

        return null;
    }

    private function elapsedMs(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }

    private function trans(string $key): string
    {
        return (string) $this->translator->trans($key);
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function auditMeta(array $arguments): array
    {
        $meta = ['argument_keys' => array_keys($arguments)];

        foreach (['id', 'discussion_id', 'post_id', 'user_id', 'tag_id'] as $key) {
            if (isset($arguments[$key]) && is_scalar($arguments[$key])) {
                $meta['target_id'] = $arguments[$key];
                break;
            }
        }

        return $meta;
    }
}
