<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Api\Controller\Admin;

use CengizKemec\FlarumMcp\Exception\McpAuthenticationException;
use CengizKemec\FlarumMcp\Exception\McpSkillException;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\NotAuthenticatedException;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

abstract class AdminController implements RequestHandlerInterface
{
    protected function admin(Request $request): User
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        return $actor;
    }

    /**
     * @return array<string, mixed>
     */
    protected function input(Request $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    protected function param(Request $request, string $name, mixed $default = null): mixed
    {
        $query = $request->getQueryParams();

        if (array_key_exists($name, $query)) {
            return $query[$name];
        }

        return $request->getAttribute($name, $default);
    }

    protected function ok(array $data = [], int $status = 200): Response
    {
        return new JsonResponse($data, $status, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    protected function fail(string $message, int $status = 400, string $code = 'bad_request'): Response
    {
        return new JsonResponse([
            'errors' => [
                [
                    'status' => (string) $status,
                    'code' => $code,
                    'detail' => $message,
                ],
            ],
        ], $status, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    protected function run(Request $request, callable $callback): Response
    {
        try {
            $this->admin($request);

            return $callback();
        } catch (McpSkillException $e) {
            return $this->fail($e->getMessage(), 422, $e->errorCode);
        } catch (McpAuthenticationException $e) {
            return $this->fail($e->getMessage(), max(400, $e->status), $e->errorCode);
        } catch (ValidationException $e) {
            $details = array_merge($e->getAttributes(), $e->getRelationships());
            $message = implode(' ', array_map('strval', $details)) ?: 'Validation failed.';

            return $this->fail($message, 422, 'validation_error');
        } catch (ModelNotFoundException) {
            return $this->fail('Not found.', 404, 'not_found');
        } catch (NotAuthenticatedException) {
            return $this->fail('Authentication required.', 401, 'not_authenticated');
        } catch (PermissionDeniedException) {
            return $this->fail('You do not have permission to perform this action.', 403, 'permission_denied');
        } catch (\Throwable $e) {
            resolve(LoggerInterface::class)->error('Flarum MCP admin request failed.', ['exception' => $e]);

            return $this->fail('Internal server error.', 500, 'internal_error');
        }
    }

    protected function bool(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
