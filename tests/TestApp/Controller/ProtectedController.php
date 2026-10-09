<?php
declare(strict_types=1);

namespace TestApp\Controller;

use Authentication\IdentityInterface;
use Cake\Controller\Controller;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response;
use Crustum\Tessera\AccessToken;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Tessera;

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
     * Return the authenticated user identifier as JSON.
     *
     * @return \Cake\Http\Response
     * @throws \Cake\Http\Exception\UnauthorizedException
     */
    public function whoAmI(): Response
    {
        $identity = $this->getRequest()->getAttribute('identity');
        if ($identity instanceof IdentityInterface) {
            $identity = $identity->getOriginalData();
        }

        if (!$identity instanceof OAuthenticatable) {
            throw new UnauthorizedException();
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody((string)json_encode(['id' => $identity->getIdentifier()]));
    }

    /**
     * Return the guard user, token, and client identifiers as JSON.
     *
     * @return \Cake\Http\Response
     * @throws \Cake\Http\Exception\UnauthorizedException
     */
    public function guardInfo(): Response
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
            ->withStringBody((string)json_encode([
                'user' => $token->oauth_user_id,
                'token' => $token->oauth_access_token_id,
                'client' => $token->oauth_client_id,
            ]));
    }

    /**
     * Redirect to the fresh-token probe route.
     *
     * @return \Cake\Http\Response
     */
    public function redirectToFresh(): Response
    {
        return $this->redirect('/fresh');
    }

    /**
     * Return a response that already carries a Tessera API token cookie.
     *
     * @return \Cake\Http\Response
     */
    public function existingCookie(): Response
    {
        return $this->response
            ->withStringBody('response')
            ->withCookie(Cookie::create(Tessera::cookie(), 'existing'));
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
