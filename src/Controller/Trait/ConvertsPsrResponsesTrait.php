<?php
declare(strict_types=1);

namespace Crustum\Tessera\Controller\Trait;

use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Converts a PSR-7 response returned by the League OAuth2 server into a Cake response.
 */
trait ConvertsPsrResponsesTrait
{
    /**
     * Copy the status, headers, and body of a PSR-7 response onto a new Cake response.
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

        return $response->withBody($psrResponse->getBody());
    }
}
