<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Api\Controller\Admin;

use Carbon\Carbon;
use CengizKemec\FlarumMcp\Mcp\PermissionRegistry;
use CengizKemec\FlarumMcp\Mcp\PromptRegistry;
use CengizKemec\FlarumMcp\Mcp\ResourceRegistry;
use CengizKemec\FlarumMcp\Mcp\ToolRegistry;
use CengizKemec\FlarumMcp\Model\McpExtensionApproval;
use CengizKemec\FlarumMcp\Permission\PermissionService;
use Flarum\Foundation\ValidationException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class IntegrationsController extends AdminController
{
    public function __construct(
        private readonly ToolRegistry $tools,
        private readonly ResourceRegistry $resources,
        private readonly PromptRegistry $prompts,
        private readonly PermissionRegistry $permissions,
        private readonly PermissionService $permissionService
    ) {
    }

    public function handle(Request $request): Response
    {
        return $this->run($request, function () use ($request): Response {
            return match ($request->getAttribute('routeName')) {
                'cengizkemec-mcp.admin.integrations.index' => $this->index(),
                'cengizkemec-mcp.admin.integrations.update' => $this->update($request),
                default => $this->fail('Unknown action.', 404, 'not_found'),
            };
        });
    }

    private function index(): Response
    {
        $integrations = [];

        $touch = function (string $extensionId) use (&$integrations): void {
            if (! isset($integrations[$extensionId])) {
                $integrations[$extensionId] = [
                    'extensionId' => $extensionId,
                    'status' => McpExtensionApproval::STATUS_PENDING,
                    'approved' => false,
                    'tools' => [],
                    'resources' => [],
                    'prompts' => [],
                    'permissions' => [],
                ];
            }
        };

        foreach ($this->tools->all() as $tool) {
            $id = $tool->extensionId ?? 'cengizkemec-mcp';
            $touch($id);
            $integrations[$id]['tools'][] = $tool->name;
            $integrations[$id]['permissions'][] = $tool->permission;
        }

        foreach ($this->resources->all() as $resource) {
            $id = $resource->extensionId ?? 'cengizkemec-mcp';
            $touch($id);
            $integrations[$id]['resources'][] = $resource->identifier();
            $integrations[$id]['permissions'][] = $resource->permission;
        }

        foreach ($this->prompts->all() as $prompt) {
            $id = $prompt->extensionId ?? 'cengizkemec-mcp';
            $touch($id);
            $integrations[$id]['prompts'][] = $prompt->name;
            $integrations[$id]['permissions'][] = $prompt->permission;
        }

        $approvals = McpExtensionApproval::query()
            ->get()
            ->keyBy('extension_id');

        foreach ($integrations as $id => &$integration) {
            $approval = $approvals->get($id);

            if ($id === 'cengizkemec-mcp') {
                $integration['status'] = McpExtensionApproval::STATUS_APPROVED;
                $integration['approved'] = true;
            } elseif ($approval !== null) {
                $integration['status'] = $approval->status;
                $integration['approved'] = $approval->status === McpExtensionApproval::STATUS_APPROVED;
            }

            $integration['permissions'] = array_values(array_unique($integration['permissions']));
        }
        unset($integration);

        ksort($integrations);

        return $this->ok([
            'integrations' => array_values($integrations),
            'permissionCatalog' => array_values(array_map(
                fn (string $id): array => [
                    'id' => $id,
                    'label' => $this->permissions->all()[$id]['label'] ?? $id,
                    'risk' => $this->permissions->risk($id),
                ],
                array_keys($this->permissions->all())
            )),
        ]);
    }

    private function update(Request $request): Response
    {
        $extensionId = (string) $this->param($request, 'extensionId');
        $input = $this->input($request);
        $status = (string) ($input['status'] ?? '');

        if ($extensionId === 'cengizkemec-mcp') {
            throw new ValidationException(['extensionId' => 'The built-in integration is always approved.']);
        }

        if (! in_array($status, [
            McpExtensionApproval::STATUS_PENDING,
            McpExtensionApproval::STATUS_APPROVED,
            McpExtensionApproval::STATUS_DISABLED,
        ], true)) {
            throw new ValidationException(['status' => 'Status must be pending, approved or disabled.']);
        }

        $approval = McpExtensionApproval::query()->updateOrCreate(
            ['extension_id' => $extensionId],
            [
                'status' => $status,
                'approved_by_id' => $this->admin($request)->id,
                'decided_at' => Carbon::now(),
            ]
        );

        $this->permissionService->forgetCaches();

        return $this->ok([
            'integration' => [
                'extensionId' => $approval->extension_id,
                'status' => $approval->status,
                'approved' => $approval->status === McpExtensionApproval::STATUS_APPROVED,
                'decidedAt' => $approval->decided_at?->toIso8601String(),
            ],
        ]);
    }
}
