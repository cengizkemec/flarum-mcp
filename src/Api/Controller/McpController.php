<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Api\Controller;

use CengizKemec\FlarumMcp\Exception\McpAuthenticationException;
use CengizKemec\FlarumMcp\Exception\McpRateLimitException;
use CengizKemec\FlarumMcp\Http\Middleware\CaptureRawMcpBody;
use CengizKemec\FlarumMcp\Mcp\Authenticator;
use CengizKemec\FlarumMcp\Mcp\McpRequestContext;
use CengizKemec\FlarumMcp\Mcp\RateLimiter;
use CengizKemec\FlarumMcp\Mcp\ServerFactory;
use CengizKemec\FlarumMcp\Permission\PermissionService;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Stream;
use Mcp\Server\Authorization\AccessToken;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class McpController implements RequestHandlerInterface
{
    public function __construct(
        private readonly Authenticator $authenticator,
        private readonly RateLimiter $rateLimiter,
        private readonly ServerFactory $serverFactory,
        private readonly PermissionService $permissions,
        private readonly SettingsRepositoryInterface $settings,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            if ($request->getMethod() === 'OPTIONS') {
                return $this->optionsResponse($request);
            }

            if (! (bool) $this->settings->get('cengizkemec-mcp.enabled', true)) {
                return $this->jsonRpcError(503, -32000, 'The MCP server is disabled.');
            }

            if (! in_array($request->getMethod(), ['POST', 'GET', 'DELETE'], true)) {
                return $this->jsonRpcError(405, -32600, 'Method not allowed.', [
                    'Allow' => 'GET, POST, DELETE, OPTIONS',
                ]);
            }

            if ($request->getMethod() === 'POST') {
                $contentType = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));

                if ($contentType === '' || ($contentType !== 'application/json' && ! str_ends_with($contentType, '+json'))) {
                    return $this->jsonRpcError(415, -32600, 'Content-Type must be application/json.');
                }

                $length = (int) $request->getHeaderLine('Content-Length');

                if ($length > 0 && $length > $this->serverFactory->maxBodyBytes()) {
                    return $this->jsonRpcError(413, -32600, 'Request body is too large.');
                }
            }

            $auth = $this->authenticator->authenticate($request);
            $rate = $this->rateLimiter->consume($auth->connection, Authenticator::clientIp($request));

            if (! $auth->actor->can('viewForum')) {
                return $this->jsonRpcError(403, -32002, 'This user cannot access the forum.');
            }

            $context = new McpRequestContext(
                $auth->connection,
                $auth->actor,
                $request,
                Authenticator::clientIp($request),
                'mcp'
            );

            $transportRequest = $this->withRestoredBody($request)
                ->withAttribute(
                    AccessToken::class,
                    new AccessToken(
                        $this->scopes($context),
                        [
                            'iss' => (string) $this->serverFactory->forumUrl(),
                            'client_id' => 'connection-'.$auth->connection->id,
                            'sub' => 'connection-'.$auth->connection->id,
                            'token_prefix' => $auth->tokenPrefix,
                            'user_id' => $auth->actor->id,
                        ]
                    )
                )
                ->withAttribute(StreamableHttpTransport::PRINCIPAL_ATTRIBUTE, 'connection-'.$auth->connection->id);

            $server = $this->serverFactory->create($context);

            $transport = new StreamableHttpTransport(
                $transportRequest,
                logger: $this->logger,
                middleware: [
                    new CorsMiddleware($this->serverFactory->allowedOrigins()),
                    new DnsRebindingProtectionMiddleware(
                        $this->serverFactory->allowedHosts(),
                        $this->serverFactory->allowedOrigins()
                    ),
                ],
                maxBodyBytes: $this->serverFactory->maxBodyBytes()
            );

            $response = $server->run($transport);

            return $response
                ->withHeader('X-Content-Type-Options', 'nosniff')
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('X-RateLimit-Limit', (string) $rate->limit)
                ->withHeader('X-RateLimit-Remaining', (string) $rate->remaining)
                ->withHeader('X-RateLimit-Reset', (string) $rate->resetAt);
        } catch (McpAuthenticationException $e) {
            $headers = [];

            if ($e->wwwAuthenticate !== null) {
                $headers['WWW-Authenticate'] = $e->wwwAuthenticate;
            }

            return $this->jsonRpcError($e->status, $e->errorCode === 'invalid_request' ? -32600 : -32001, $e->getMessage(), $headers);
        } catch (McpRateLimitException $e) {
            return $this->jsonRpcError(429, -32003, 'Too many requests.', [
                'Retry-After' => (string) $e->retryAfter,
                'X-RateLimit-Limit' => (string) $e->limit,
                'X-RateLimit-Remaining' => '0',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Unhandled MCP endpoint error.', ['exception' => $e]);

            return $this->jsonRpcError(500, -32603, 'Internal server error.');
        }
    }

    /**
     * Flarum's forum middleware parses JSON bodies and consumes the stream.
     * Rebuild the body for the MCP transport, which must read the raw JSON-RPC
     * envelope itself.
     */
    private function withRestoredBody(Request $request): Request
    {
        if ($request->getMethod() !== 'POST') {
            return $request;
        }

        // CaptureRawMcpBody stores the exact JSON before Flarum decodes it, so
        // empty objects and arrays keep their original meaning.
        $raw = $request->getAttribute(CaptureRawMcpBody::ATTRIBUTE);

        if (is_string($raw)) {
            $stream = new Stream('php://temp', 'wb+');

            if ($raw !== '') {
                $stream->write($raw);
            }

            $stream->rewind();

            return $request
                ->withBody($stream)
                ->withHeader('Content-Length', (string) strlen($raw));
        }

        $parsed = $request->getParsedBody();

        if (! is_array($parsed) || $parsed === []) {
            return $request;
        }

        $json = json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($json === false) {
            return $request;
        }

        $stream = new Stream('php://temp', 'wb+');
        $stream->write($json);
        $stream->rewind();

        return $request
            ->withBody($stream)
            ->withHeader('Content-Length', (string) strlen($json));
    }

    private function optionsResponse(Request $request): Response
    {
        $headers = [
            'Allow' => 'GET, POST, DELETE, OPTIONS',
            'Access-Control-Allow-Methods' => 'GET, POST, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, MCP-Protocol-Version, Mcp-Session-Id, Mcp-Method, Mcp-Name, Mcp-Param-*, Last-Event-ID',
            'Access-Control-Max-Age' => '600',
            'Vary' => 'Origin',
        ];

        $origin = $request->getHeaderLine('Origin');

        if ($origin !== '') {
            $allowed = $this->serverFactory->allowedOrigins();

            if (in_array($origin, $allowed, true)) {
                $headers['Access-Control-Allow-Origin'] = $origin;
            }
        }

        return new EmptyResponse(204, $headers);
    }

    /**
     * @return string[]
     */
    private function scopes(McpRequestContext $context): array
    {
        return $this->permissions->effectivePermissions($context->connection);
    }

    private function jsonRpcError(int $status, int $code, string $message, array $headers = []): Response
    {
        return new JsonResponse([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status, array_merge([
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ], $headers));
    }
}
