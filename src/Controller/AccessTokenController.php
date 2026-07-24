<?php
declare(strict_types=1);

namespace Crustum\Tessera\Controller;

use Cake\Controller\Controller;
use Cake\Http\Response;
use Crustum\Tessera\Controller\Trait\ConvertsPsrResponsesTrait;
use Crustum\Tessera\Controller\Trait\HandlesOAuthErrorsTrait;
use Laminas\Diactoros\Response as PsrResponse;
use League\OAuth2\Server\AuthorizationServer;

/**
 * Issues OAuth access tokens via the authorization server.
 */
class AccessTokenController extends Controller
{
    use ConvertsPsrResponsesTrait;
    use HandlesOAuthErrorsTrait;

    /**
     * Respond to an access token request for the current grant type.
     *
     * @param \League\OAuth2\Server\AuthorizationServer $server Authorization server
     * @return \Cake\Http\Response
     */
    public function issueToken(AuthorizationServer $server): Response
    {
        return $this->withErrorHandling(fn(): Response => $this->convertResponse(
            $server->respondToAccessTokenRequest($this->getRequest(), new PsrResponse()),
        ));
    }
}
