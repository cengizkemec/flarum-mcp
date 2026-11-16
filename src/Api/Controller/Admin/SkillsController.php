<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Api\Controller\Admin;

use CengizKemec\FlarumMcp\Skill\SkillManager;
use Flarum\Foundation\ValidationException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SkillsController extends AdminController
{
    public function __construct(
        private readonly SkillManager $skills
    ) {
    }

    public function handle(Request $request): Response
    {
        return $this->run($request, function () use ($request): Response {
            return match ($request->getAttribute('routeName')) {
                'cengizkemec-mcp.admin.skills.index' => $this->index(),
                'cengizkemec-mcp.admin.skills.create' => $this->create($request),
                'cengizkemec-mcp.admin.skills.update' => $this->update($request),
                'cengizkemec-mcp.admin.skills.delete' => $this->delete($request),
                'cengizkemec-mcp.admin.skills.enable' => $this->enable($request),
                'cengizkemec-mcp.admin.skills.install_url' => $this->installUrl($request),
                'cengizkemec-mcp.admin.skills.install_zip' => $this->installZip($request),
                'cengizkemec-mcp.admin.skills.export' => $this->export($request),
                default => $this->fail('Unknown action.', 404, 'not_found'),
            };
        });
    }

    private function index(): Response
    {
        return $this->ok([
            'skills' => $this->skills->allForAdmin(),
        ]);
    }

    private function create(Request $request): Response
    {
        $input = $this->input($request);
        $content = (string) ($input['content'] ?? '');

        if (trim($content) === '') {
            throw new ValidationException(['content' => 'SKILL.md content is required.']);
        }

        $skill = $this->skills->installContent(
            $this->admin($request),
            $content,
            (string) ($input['sourceType'] ?? 'manual'),
            isset($input['sourceUrl']) ? (string) $input['sourceUrl'] : null,
            $this->bool($input['update'] ?? false)
        );

        return $this->ok(['skill' => $skill], 201);
    }

    private function update(Request $request): Response
    {
        $id = (int) $this->param($request, 'id');
        $input = $this->input($request);

        $skill = $this->skills->updateCustom(
            $this->admin($request),
            $id,
            isset($input['content']) ? (string) $input['content'] : null,
            isset($input['name']) ? (string) $input['name'] : null,
            isset($input['description']) ? (string) $input['description'] : null,
            isset($input['requiredPermission']) ? (string) $input['requiredPermission'] : null
        );

        return $this->ok(['skill' => $skill]);
    }

    private function delete(Request $request): Response
    {
        $id = (int) $this->param($request, 'id');
        $this->skills->deleteCustom($id);

        return $this->ok(['deleted' => true, 'id' => $id]);
    }

    private function enable(Request $request): Response
    {
        // Built-in Skill identifiers use the form "builtin:slug"; the colon
        // arrives percent-encoded from the admin frontend.
        $id = rawurldecode((string) $this->param($request, 'id'));
        $input = $this->input($request);
        $enabled = array_key_exists('enabled', $input) ? $this->bool($input['enabled']) : true;

        return $this->ok(['skill' => $this->skills->setEnabled($id, $enabled)]);
    }

    private function installUrl(Request $request): Response
    {
        $input = $this->input($request);
        $url = trim((string) ($input['url'] ?? ''));

        if ($url === '') {
            throw new ValidationException(['url' => 'A URL is required.']);
        }

        $skill = $this->skills->installFromUrl(
            $this->admin($request),
            $url,
            $this->bool($input['update'] ?? false)
        );

        return $this->ok(['skill' => $skill], 201);
    }

    private function installZip(Request $request): Response
    {
        $input = $this->input($request);
        $archive = (string) ($input['archive'] ?? '');

        if ($archive === '') {
            throw new ValidationException(['archive' => 'A base64-encoded ZIP archive is required.']);
        }

        $skill = $this->skills->installFromZip(
            $this->admin($request),
            $archive,
            $this->bool($input['update'] ?? false)
        );

        return $this->ok(['skill' => $skill], 201);
    }

    private function export(Request $request): Response
    {
        $id = rawurldecode((string) $this->param($request, 'id'));

        return $this->ok($this->skills->export($id));
    }
}
