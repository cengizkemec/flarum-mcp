<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use CengizKemec\FlarumMcp\Api\Controller\Admin\ApprovalsController;
use CengizKemec\FlarumMcp\Api\Controller\Admin\AuditController;
use CengizKemec\FlarumMcp\Api\Controller\Admin\ConnectionsController;
use CengizKemec\FlarumMcp\Api\Controller\Admin\IntegrationsController;
use CengizKemec\FlarumMcp\Api\Controller\Admin\OverviewController;
use CengizKemec\FlarumMcp\Api\Controller\Admin\ProfilesController;
use CengizKemec\FlarumMcp\Api\Controller\Admin\PromptsController;
use CengizKemec\FlarumMcp\Api\Controller\Admin\ResourcesController;
use CengizKemec\FlarumMcp\Api\Controller\Admin\SettingsController;
use CengizKemec\FlarumMcp\Api\Controller\Admin\SkillsController;
use CengizKemec\FlarumMcp\Api\Controller\Admin\ToolsController;
use CengizKemec\FlarumMcp\Api\Controller\McpController;
use CengizKemec\FlarumMcp\Console\PruneAuditCommand;
use CengizKemec\FlarumMcp\Http\Middleware\CaptureRawMcpBody;
use CengizKemec\FlarumMcp\ServiceProvider;
use Flarum\Extend;
use Flarum\Http\Middleware\ParseJsonBody;

return [
    (new Extend\ServiceProvider())->register(ServiceProvider::class),
    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Csrf())
        ->exemptRoute('cengizkemec-mcp.mcp.post')
        ->exemptRoute('cengizkemec-mcp.mcp.get')
        ->exemptRoute('cengizkemec-mcp.mcp.delete'),

    (new Extend\Middleware('forum'))
        ->insertBefore(ParseJsonBody::class, CaptureRawMcpBody::class),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    (new Extend\Routes('forum'))
        ->post('/mcp', 'cengizkemec-mcp.mcp.post', McpController::class)
        ->get('/mcp', 'cengizkemec-mcp.mcp.get', McpController::class)
        ->delete('/mcp', 'cengizkemec-mcp.mcp.delete', McpController::class),

    (new Extend\Routes('api'))
        ->get('/mcp/overview', 'cengizkemec-mcp.admin.overview', OverviewController::class)

        ->get('/mcp/connections', 'cengizkemec-mcp.admin.connections.index', ConnectionsController::class)
        ->post('/mcp/connections', 'cengizkemec-mcp.admin.connections.create', ConnectionsController::class)
        ->patch('/mcp/connections/{id}', 'cengizkemec-mcp.admin.connections.update', ConnectionsController::class)
        ->delete('/mcp/connections/{id}', 'cengizkemec-mcp.admin.connections.delete', ConnectionsController::class)
        ->post('/mcp/connections/{id}/rotate', 'cengizkemec-mcp.admin.connections.rotate', ConnectionsController::class)
        ->post('/mcp/connections/{id}/revoke', 'cengizkemec-mcp.admin.connections.revoke', ConnectionsController::class)

        ->get('/mcp/profiles', 'cengizkemec-mcp.admin.profiles.index', ProfilesController::class)
        ->post('/mcp/profiles', 'cengizkemec-mcp.admin.profiles.create', ProfilesController::class)
        ->patch('/mcp/profiles/{id}', 'cengizkemec-mcp.admin.profiles.update', ProfilesController::class)
        ->delete('/mcp/profiles/{id}', 'cengizkemec-mcp.admin.profiles.delete', ProfilesController::class)
        ->post('/mcp/profiles/{id}/duplicate', 'cengizkemec-mcp.admin.profiles.duplicate', ProfilesController::class)
        ->get('/mcp/profiles/{id}/export', 'cengizkemec-mcp.admin.profiles.export', ProfilesController::class)
        ->post('/mcp/profiles/import', 'cengizkemec-mcp.admin.profiles.import', ProfilesController::class)

        ->get('/mcp/tools', 'cengizkemec-mcp.admin.tools.index', ToolsController::class)
        ->patch('/mcp/tools/{name}', 'cengizkemec-mcp.admin.tools.update', ToolsController::class)

        ->get('/mcp/resources', 'cengizkemec-mcp.admin.resources.index', ResourcesController::class)
        ->patch('/mcp/resources', 'cengizkemec-mcp.admin.resources.update', ResourcesController::class)

        ->get('/mcp/prompts', 'cengizkemec-mcp.admin.prompts.index', PromptsController::class)
        ->post('/mcp/prompts', 'cengizkemec-mcp.admin.prompts.create', PromptsController::class)
        ->patch('/mcp/prompts/{id}', 'cengizkemec-mcp.admin.prompts.update', PromptsController::class)
        ->delete('/mcp/prompts/{id}', 'cengizkemec-mcp.admin.prompts.delete', PromptsController::class)

        ->get('/mcp/skills', 'cengizkemec-mcp.admin.skills.index', SkillsController::class)
        ->post('/mcp/skills', 'cengizkemec-mcp.admin.skills.create', SkillsController::class)
        ->patch('/mcp/skills/{id}', 'cengizkemec-mcp.admin.skills.update', SkillsController::class)
        ->delete('/mcp/skills/{id}', 'cengizkemec-mcp.admin.skills.delete', SkillsController::class)
        ->post('/mcp/skills/{id}/enable', 'cengizkemec-mcp.admin.skills.enable', SkillsController::class)
        ->post('/mcp/skills/install-url', 'cengizkemec-mcp.admin.skills.install_url', SkillsController::class)
        ->post('/mcp/skills/install-zip', 'cengizkemec-mcp.admin.skills.install_zip', SkillsController::class)
        ->get('/mcp/skills/{id}/export', 'cengizkemec-mcp.admin.skills.export', SkillsController::class)

        ->get('/mcp/integrations', 'cengizkemec-mcp.admin.integrations.index', IntegrationsController::class)
        ->patch('/mcp/integrations/{extensionId}', 'cengizkemec-mcp.admin.integrations.update', IntegrationsController::class)

        ->get('/mcp/audit', 'cengizkemec-mcp.admin.audit.index', AuditController::class)
        ->get('/mcp/approvals', 'cengizkemec-mcp.admin.approvals.index', ApprovalsController::class)
        ->post('/mcp/approvals/{id}/approve', 'cengizkemec-mcp.admin.approvals.approve', ApprovalsController::class)
        ->post('/mcp/approvals/{id}/reject', 'cengizkemec-mcp.admin.approvals.reject', ApprovalsController::class)

        ->get('/mcp/settings', 'cengizkemec-mcp.admin.settings.index', SettingsController::class)
        ->patch('/mcp/settings', 'cengizkemec-mcp.admin.settings.update', SettingsController::class),

    (new Extend\Settings())
        ->default('cengizkemec-mcp.max_request_bytes', 4194304)
        ->default('cengizkemec-mcp.session_ttl', 3600)
        ->default('cengizkemec-mcp.approval_ttl_hours', 24)
        ->default('cengizkemec-mcp.audit_retention_days', 90)
        ->default('cengizkemec-mcp.allowed_hosts', '[]')
        ->default('cengizkemec-mcp.allowed_origins', '[]')
        ->default('cengizkemec-mcp.enabled', 1)
        ->default('cengizkemec-mcp.default_permission_profile', 'minimum')
        ->default('cengizkemec-mcp.default_rate_limit_per_minute', 60)
        ->default('cengizkemec-mcp.default_rate_limit_per_hour', 1000)
        ->default('cengizkemec-mcp.default_rate_limit_per_day', 10000)
        ->default('cengizkemec-mcp.disabled_builtin_skills', '[]'),

    (new Extend\Console())
        ->command(PruneAuditCommand::class)
        ->schedule(PruneAuditCommand::class, fn ($event) => $event->daily()),
];
