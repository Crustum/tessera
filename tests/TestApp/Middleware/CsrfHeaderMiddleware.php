<?php
declare(strict_types=1);

namespace TestApp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Expose the SPA CSRF header as the request attribute the token cookie
 * factory reads. Test-only: lets HTTP tests exercise cookie issuance the
 * same way a first-party client sending `X-CSRF-TOKEN` would.
 */
class CsrfHeaderMiddleware implements MiddlewareInterface
{
    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $csrfToken = $request->getHeaderLine('X-CSRF-TOKEN');
        if ($csrfToken !== '' && !$request->getAttribute('csrfToken')) {
            $request = $request->withAttribute('csrfToken', $csrfToken);
        }

        return $handler->handle($request);
    }
}
