<?php
declare(strict_types=1);

namespace Crustum\Tessera\Controller;

use Cake\Controller\Controller;
use Cake\Http\Response;
use Crustum\Tessera\Controller\Trait\ConvertsPsrResponsesTrait;
use Crustum\Tessera\Controller\Trait\HandlesOAuthErrorsTrait;
use Crustum\Tessera\Controller\Trait\RetrievesAuthRequestFromSessionTrait;
use Laminas\Diactoros\Response as PsrResponse;
use League\OAuth2\Server\AuthorizationServer;

/**
 * Completes a previously validated authorization request as approved.
 */
class ApproveAuthorizationController extends Controller
{
    use ConvertsPsrResponsesTrait;
    use HandlesOAuthErrorsTrait;
    use RetrievesAuthRequestFromSessionTrait;

    /**
     * Approve the pending authorization request stashed in the session.
     *
     * @param \League\OAuth2\Server\AuthorizationServer $server Authorization server
     * @return \Cake\Http\Response
     */
    public function approve(AuthorizationServer $server): Response
    {
        $authRequest = $this->getAuthRequestFromSession($this->getRequest());
        $authRequest->setAuthorizationApproved(true);

        return $this->withErrorHandling(
            fn(): Response => $this->convertResponse(
                $server->completeAuthorizationRequest($authRequest, new PsrResponse()),
            ),
            $authRequest->getGrantTypeId() === 'implicit',
        );
    }
}
