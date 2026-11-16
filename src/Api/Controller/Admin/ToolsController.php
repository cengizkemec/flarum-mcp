<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Api\Controller\Admin;

use CengizKemec\FlarumMcp\Mcp\ToolRegistry;
use CengizKemec\FlarumMcp\Model\McpAuditLog;
use CengizKemec\FlarumMcp\Model\McpToolState;
use CengizKemec\FlarumMcp\Permission\PermissionService;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ToolsController extends AdminController
{
    public function __construct(
        private readonly ToolRegistry $tools,
        private readonly PermissionService $permissions,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function handle(Request $request): Response
    {
        return $this->run($request, function () use ($request): Response {
            return match ($request->getAttribute('routeName')) {
                'cengizkemec-mcp.admin.tools.index' => $this->index(),
                'cengizkemec-mcp.admin.tools.update' => $this->update($request),
                default => $this->fail('Unknown action.', 404, 'not_found'),
            };
        });
    }

    private function index(): Response
    {
        $stats = McpAuditLog::query()
            ->selectRaw('tool_name, COUNT(*) as calls, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as errors, MAX(created_at) as last_used', ['error'])
            ->whereNotNull('tool_name')
            ->whereIn('operation', ['tool', 'approved_tool'])
            ->groupBy('tool_name')
            ->get()
            ->keyBy('tool_name');

        $tools = $this->tools->adminList($this->permissions, $this->translator);

        foreach ($tools as &$tool) {
            $stat = $stats->get($tool['name']);
            $tool['stats'] = [
                'calls' => (int) ($stat->calls ?? 0),
                'errors' => (int) ($stat->errors ?? 0),
                'lastUsedAt' => $stat->last_used ?? null,
            ];
        }
        unset($tool);

        return $this->ok(['tools' => $tools]);
    }

    private function update(Request $request): Response
    {
        $name = (string) $this->param($request, 'name');
        $tool = $this->tools->get($name);

        if ($tool === null) {
            throw new ValidationException(['tool' => 'Unknown tool.']);
        }

        $input = $this->input($request);

        if (! array_key_exists('enabled', $input)) {
            throw new ValidationException(['enabled' => 'The enabled field is required.']);
        }

        $enabled = $this->bool($input['enabled']);

        McpToolState::query()->updateOrCreate(
            ['tool_name' => $name],
            ['enabled' => $enabled]
        );

        $this->permissions->forgetCaches();

        return $this->ok([
            'tool' => $tool->toAdminArray(
                $this->translator,
                $enabled,
                $this->permissions->integrationApproved($tool->extensionId)
            ),
        ]);
    }
}
