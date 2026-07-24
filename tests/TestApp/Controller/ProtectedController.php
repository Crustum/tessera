<?php
declare(strict_types=1);

namespace TestApp\Controller;

use Authentication\IdentityInterface;
use Cake\Controller\Controller;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response;
use Crustum\Tessera\AccessToken;
use Crustum\Tessera\Contracts\OAuthenticatable;

/**
 * Minimal protected endpoint for Feature grant tests.
 */
class ProtectedController extends Controller
{
    /**
     * Return a plain success body.
     *
     * @return \Cake\Http\Response
     */
    public function index(): Response
    {
        return $this->response->withStringBody('response');
    }

    /**
     * Return the current access token attributes as JSON.
     *
     * @return \Cake\Http\Response
     * @throws \Cake\Http\Exception\UnauthorizedException
     */
    public function tokenInfo(): Response
    {
        $identity = $this->getRequest()->getAttribute('identity');
        if (!$identity instanceof IdentityInterface) {
            throw new UnauthorizedException();
        }

        $user = $identity->getOriginalData();
        if (!$user instanceof OAuthenticatable) {
            throw new UnauthorizedException();
        }

        $token = $user->currentAccessToken();
        if (!$token instanceof AccessToken) {
            throw new UnauthorizedException();
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody($token->toJson());
    }

    /**
     * Assert actingAs token fields and return a success body.
     *
     * @return \Cake\Http\Response
     * @throws \Cake\Http\Exception\UnauthorizedException
     */
    public function actingAsAuth(): Response
    {
        $identity = $this->getRequest()->getAttribute('identity');
        if (!$identity instanceof IdentityInterface) {
            throw new UnauthorizedException();
        }

        $user = $identity->getOriginalData();
        if (!$user instanceof OAuthenticatable) {
            throw new UnauthorizedException();
        }

        $token = $user->currentAccessToken();
        if (!$token instanceof AccessToken) {
            throw new UnauthorizedException();
        }

        if (
            $token->oauth_client_id !== 'client-1234'
            || (int)$token->oauth_user_id !== 1234
            || $token->oauth_scopes !== ['admin']
        ) {
            throw new UnauthorizedException();
        }

        return $this->response->withStringBody('bar');
    }
}
