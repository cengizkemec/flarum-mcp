<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Api\Controller\Admin;

use CengizKemec\FlarumMcp\Mcp\ApprovalService;
use CengizKemec\FlarumMcp\Mcp\PromptRegistry;
use CengizKemec\FlarumMcp\Mcp\ResourceRegistry;
use CengizKemec\FlarumMcp\Mcp\ServerFactory;
use CengizKemec\FlarumMcp\Mcp\ToolRegistry;
use CengizKemec\FlarumMcp\Model\McpAuditLog;
use CengizKemec\FlarumMcp\Model\McpConnection;
use CengizKemec\FlarumMcp\Model\McpExtensionApproval;
use CengizKemec\FlarumMcp\Model\McpSkill;
use CengizKemec\FlarumMcp\Skill\SkillManager;
use Flarum\Foundation\Config;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class OverviewController extends AdminController
{
    public function __construct(
        private readonly ToolRegistry $tools,
        private readonly ResourceRegistry $resources,
        private readonly PromptRegistry $prompts,
        private readonly SkillManager $skills,
        private readonly ApprovalService $approvals,
        private readonly SettingsRepositoryInterface $settings,
        private readonly Config $config
    ) {
    }

    public function handle(Request $request): Response
    {
        return $this->run($request, function (): Response {
            $this->approvals->expirePending();

            $recent = McpAuditLog::query()
                ->with(['mcpConnection', 'user'])
                ->orderByDesc('id')
                ->take(12)
                ->get();

            $enabled = (bool) $this->settings->get('cengizkemec-mcp.enabled', true);

            return $this->ok([
                'server' => [
                    'enabled' => $enabled,
                    'endpoint' => rtrim((string) $this->config->url(), '/').'/mcp',
                    'version' => ServerFactory::VERSION,
                    'flarumVersion' => app()->version(),
                    'phpVersion' => PHP_VERSION,
                ],
                'stats' => [
                    'connections' => McpConnection::query()->count(),
                    'activeConnections' => McpConnection::query()
                        ->where('status', 'active')
                        ->whereNull('token_revoked_at')
                        ->count(),
                    'requests' => McpAuditLog::query()->count(),
                    'toolExecutions' => McpAuditLog::query()
                        ->whereIn('operation', ['tool', 'approved_tool'])
                        ->count(),
                    'denied' => McpAuditLog::query()->where('permission_decision', 'denied')->count(),
                    'errors' => McpAuditLog::query()->where('status', 'error')->count(),
                    'pendingApprovals' => $this->approvals->pendingCount(),
                    'installedSkills' => count($this->skills->builtIns()) + McpSkill::query()->count(),
                    'tools' => count($this->tools->all()),
                    'resources' => count($this->resources->all()),
                    'prompts' => count($this->prompts->all()),
                    'approvedIntegrations' => McpExtensionApproval::query()
                        ->where('status', McpExtensionApproval::STATUS_APPROVED)
                        ->count(),
                ],
                'recentActivity' => $recent->map(fn (McpAuditLog $log): array => $log->toAdminArray())->all(),
                'health' => [
                    'curl' => extension_loaded('curl'),
                    'zip' => class_exists(\ZipArchive::class),
                    'sessionStorageWritable' => is_writable(resolve('flarum.paths')->storage.'/mcp-sessions')
                        || is_writable(resolve('flarum.paths')->storage),
                    'auditRetentionDays' => (int) $this->settings->get('cengizkemec-mcp.audit_retention_days', 90),
                ],
            ]);
        });
    }
}
