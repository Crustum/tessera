<?php
declare(strict_types=1);

namespace Crustum\Tessera\Controller;

use Cake\Controller\Controller;
use Cake\Http\Response;
use Crustum\Tessera\Controller\Trait\HandlesOAuthErrorsTrait;
use Crustum\Tessera\Controller\Trait\RetrievesDeviceCodeFromSessionTrait;
use League\OAuth2\Server\AuthorizationServer;

/**
 * Approves a device authorization request stashed in the session.
 */
class ApproveDeviceAuthorizationController extends Controller
{
    use HandlesOAuthErrorsTrait;
    use RetrievesDeviceCodeFromSessionTrait;

    /**
     * Approve the pending device authorization request and redirect back to the device screen.
     *
     * @param \League\OAuth2\Server\AuthorizationServer $server Authorization server
     * @return \Cake\Http\Response
     */
    public function index(AuthorizationServer $server): Response
    {
        $deviceCode = $this->getDeviceCodeFromSession($this->getRequest());

        $this->withErrorHandling(fn() => $server->completeDeviceAuthorizationRequest(
            $deviceCode->getIdentifier(),
            (string)$deviceCode->getUserIdentifier(),
            true,
        ));

        return $this->redirect([
            '_name' => 'tessera:device',
            '?' => ['status' => 'authorization-approved'],
        ]);
    }
}
