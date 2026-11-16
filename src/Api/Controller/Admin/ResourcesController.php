<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Api\Controller\Admin;

use CengizKemec\FlarumMcp\Mcp\ResourceRegistry;
use CengizKemec\FlarumMcp\Model\McpResourceState;
use CengizKemec\FlarumMcp\Permission\PermissionService;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ResourcesController extends AdminController
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly PermissionService $permissions,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function handle(Request $request): Response
    {
        return $this->run($request, function () use ($request): Response {
            return match ($request->getAttribute('routeName')) {
                'cengizkemec-mcp.admin.resources.index' => $this->index(),
                'cengizkemec-mcp.admin.resources.update' => $this->update($request),
                default => $this->fail('Unknown action.', 404, 'not_found'),
            };
        });
    }

    private function index(): Response
    {
        return $this->ok([
            'resources' => $this->resources->adminList($this->permissions, $this->translator),
        ]);
    }

    private function update(Request $request): Response
    {
        $input = $this->input($request);
        $uri = trim((string) ($input['uri'] ?? ''));
        $resource = $this->resources->get($uri);

        if ($resource === null) {
            throw new ValidationException(['uri' => 'Unknown resource.']);
        }

        if (! array_key_exists('enabled', $input)) {
            throw new ValidationException(['enabled' => 'The enabled field is required.']);
        }

        $enabled = $this->bool($input['enabled']);

        McpResourceState::query()->updateOrCreate(
            ['resource_uri' => $uri],
            ['enabled' => $enabled]
        );

        $this->permissions->forgetCaches();

        return $this->ok([
            'resource' => $resource->toAdminArray(
                $this->translator,
                $enabled,
                $this->permissions->integrationApproved($resource->extensionId)
            ),
        ]);
    }
}
