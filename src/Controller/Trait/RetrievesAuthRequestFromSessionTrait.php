<?php
declare(strict_types=1);

namespace Crustum\Tessera\Controller\Trait;

use Cake\Http\ServerRequest;
use Crustum\Tessera\Bridge\Client as BridgeClient;
use Crustum\Tessera\Bridge\Scope as BridgeScope;
use Crustum\Tessera\Bridge\User as BridgeUser;
use Crustum\Tessera\Exception\InvalidAuthTokenException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use RuntimeException;

/**
 * Restores the pending authorization request stashed in the session by the consent screen.
 */
trait RetrievesAuthRequestFromSessionTrait
{
    /**
     * Read and clear the authorization request previously stashed in the session.
     *
     * @param \Cake\Http\ServerRequest $request Request carrying the `auth_token` confirmation field
     * @return \League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface
     * @throws \Crustum\Tessera\Exception\InvalidAuthTokenException
     * @throws \RuntimeException
     */
    protected function getAuthRequestFromSession(ServerRequest $request): AuthorizationRequestInterface
    {
        $session = $request->getSession();
        $submittedToken = $request->getData('auth_token');
        $sessionToken = $session->read('authToken');
        $session->delete('authToken');

        if (!is_string($submittedToken) || $submittedToken === '' || $sessionToken !== $submittedToken) {
            $session->delete('authRequest');

            throw InvalidAuthTokenException::different();
        }

        $serialized = $session->read('authRequest');
        $session->delete('authRequest');

        if (!is_string($serialized) || $serialized === '') {
            throw new RuntimeException('Authorization request was not present in the session.');
        }

        $authRequest = unserialize($serialized, ['allowed_classes' => [
            AuthorizationRequest::class,
            BridgeClient::class,
            BridgeScope::class,
            BridgeUser::class,
        ]]);

        if (!$authRequest instanceof AuthorizationRequestInterface) {
            throw new RuntimeException('Authorization request was not present in the session.');
        }

        return $authRequest;
    }
}
