<?php
declare(strict_types=1);

namespace Crustum\Tessera\Middleware;

use Cake\Http\Response;
use Crustum\Tessera\Exception\OAuthServerException;
use Laminas\Diactoros\Response as PsrResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Converts Tessera OAuth server exceptions into HTTP responses.
 */
class ConvertOAuthExceptionsMiddleware implements MiddlewareInterface
{
    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (OAuthServerException $oAuthServerException) {
            return $this->convertResponse(
                $oAuthServerException->generateHttpResponse(new PsrResponse()),
            );
        }
    }

    /**
     * Copy the status, headers, and body of a PSR-7 response onto a Cake response.
     *
     * @param \Psr\Http\Message\ResponseInterface $psrResponse Source PSR-7 response
     * @return \Cake\Http\Response
     */
    protected function convertResponse(ResponseInterface $psrResponse): Response
    {
        $response = (new Response())->withStatus($psrResponse->getStatusCode());

        foreach ($psrResponse->getHeaders() as $name => $values) {
            $response = $response->withHeader($name, $values);
        }

        return $response->withStringBody((string)$psrResponse->getBody());
    }
}
