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
 * Issues a device code for the device authorization grant.
 */
class DeviceCodeController extends Controller
{
    use ConvertsPsrResponsesTrait;
    use HandlesOAuthErrorsTrait;

    /**
     * Respond to a device authorization request for the client.
     *
     * @param \League\OAuth2\Server\AuthorizationServer $server Authorization server
     * @return \Cake\Http\Response
     */
    public function index(AuthorizationServer $server): Response
    {
        return $this->withErrorHandling(fn(): Response => $this->convertResponse(
            $server->respondToDeviceAuthorizationRequest($this->getRequest(), new PsrResponse()),
        ));
    }
}
