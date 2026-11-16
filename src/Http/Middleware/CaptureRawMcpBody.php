<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Http\Middleware;

use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Preserve the exact JSON body of MCP requests.
 *
 * Flarum's ParseJsonBody middleware decodes JSON with associative arrays. That
 * loses the distinction between an empty object (`{}`) and an empty array
 * (`[]`), which matters for JSON-RPC `arguments` and MCP input schemas. This
 * middleware runs before ParseJsonBody, stores the raw body on the request and
 * replaces the consumed stream so later middleware can still read it.
 */
final class CaptureRawMcpBody implements MiddlewareInterface
{
    public const ATTRIBUTE = 'cengizkemec-mcp.raw_body';

    public function process(Request $request, Handler $handler): Response
    {
        if (! $this->isMcpRequest($request)) {
            return $handler->handle($request);
        }

        $raw = (string) $request->getBody();
        $stream = new Stream('php://temp', 'wb+');

        if ($raw !== '') {
            $stream->write($raw);
        }

        $stream->rewind();

        return $handler->handle(
            $request
                ->withAttribute(self::ATTRIBUTE, $raw)
                ->withBody($stream)
                ->withHeader('Content-Length', (string) strlen($raw))
        );
    }

    private function isMcpRequest(Request $request): bool
    {
        if ($request->getMethod() !== 'POST') {
            return false;
        }

        // Flarum's BasePath middleware strips any subdirectory prefix before
        // the forum middleware stack runs, so the route path is always /mcp.
        return rtrim($request->getUri()->getPath(), '/') === '/mcp';
    }
}
