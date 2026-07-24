<?php
declare(strict_types=1);

namespace Crustum\Tessera\Controller;

use Authentication\IdentityInterface;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\I18n\DateTime;
use Cake\Utility\Security;
use Crustum\Tessera\Bridge\User as BridgeUser;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Controller\Trait\ConvertsPsrResponsesTrait;
use Crustum\Tessera\Controller\Trait\HandlesOAuthErrorsTrait;
use Crustum\Tessera\Controller\Trait\RendersConfiguredViewsTrait;
use Crustum\Tessera\Controller\Trait\ResolvesTesseraScopesTrait;
use Crustum\Tessera\Exception\OAuthServerException;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Model\Entity\Token;
use Crustum\Tessera\Scope as ScopeDefinition;
use Crustum\Tessera\Support\UserId;
use Crustum\Tessera\Tessera;
use Laminas\Diactoros\Response as PsrResponse;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use RuntimeException;

/**
 * Validates OAuth authorization requests and drives the login/consent flow.
 */
class AuthorizationController extends AppController
{
    use ConvertsPsrResponsesTrait;
    use HandlesOAuthErrorsTrait;
    use RendersConfiguredViewsTrait;
    use ResolvesTesseraScopesTrait;

    /**
     * Authorize a client to access the identity's account, prompting for login or consent as needed.
     *
     * @param \League\OAuth2\Server\AuthorizationServer $server Authorization server
     * @param \Crustum\Tessera\ClientRepository $clients Client repository
     * @return \Cake\Http\Response
     * @throws \Crustum\Tessera\Exception\OAuthServerException
     */
    public function authorize(AuthorizationServer $server, ClientRepository $clients): Response
    {
        $request = $this->getRequest();

        $authRequest = $this->withErrorHandling(
            fn(): AuthorizationRequestInterface => $server->validateAuthorizationRequest($request),
            $request->getQuery('response_type') === 'token',
        );

        $prompt = $this->parsePrompt($request);
        $identity = $this->currentIdentity();

        if (!$identity instanceof IdentityInterface) {
            if (in_array('none', $prompt, true)) {
                throw OAuthServerException::loginRequired($authRequest);
            }

            return $this->promptForLogin();
        }

        $session = $request->getSession();

        if (in_array('login', $prompt, true) && !$session->read('promptedForLogin')) {
            if ($this->components()->has('Authentication')) {
                $authentication = $this->components()->get('Authentication');
                if (method_exists($authentication, 'logout')) {
                    $authentication->logout();
                }
            }

            $session->destroy();

            return $this->promptForLogin();
        }

        $session->delete('promptedForLogin');

        $identity = $this->currentIdentity();
        if (!$identity instanceof IdentityInterface) {
            return $this->promptForLogin();
        }

        $authRequest->setUser(new BridgeUser((string)$identity->getIdentifier()));

        $scopes = $this->tesseraScopesFrom($authRequest->getScopes());
        $client = $clients->find((string)$authRequest->getClient()->getIdentifier());

        if (!$client instanceof Client) {
            throw new RuntimeException('OAuth client not found for the given identifier.');
        }

        if (
            !in_array('consent', $prompt, true)
            && ($client->skipsAuthorization($identity, $scopes) || $this->hasGrantedScopes($identity, $client, $scopes))
        ) {
            return $this->approveRequest($server, $authRequest);
        }

        if (in_array('none', $prompt, true)) {
            throw OAuthServerException::consentRequired($authRequest);
        }

        $authToken = Security::randomString(40);
        $session->write('authToken', $authToken);
        $session->write('authRequest', serialize($authRequest));

        return $this->renderConfiguredView(Tessera::$authorizationView, [
            'client' => $client,
            'user' => $identity,
            'scopes' => $scopes,
            'request' => $request,
            'authToken' => $authToken,
        ]);
    }

    /**
     * Split the `prompt` query parameter into its individual values.
     *
     * @param \Cake\Http\ServerRequest $request Request
     * @return list<string>
     */
    protected function parsePrompt(ServerRequest $request): array
    {
        $prompt = array_values(array_filter(array_map(
            trim(...),
            explode(' ', (string)$request->getQuery('prompt')),
        )));

        return in_array('none', $prompt, true) ? ['none'] : $prompt;
    }

    /**
     * Resolve the currently authenticated identity for the consent flow.
     *
     * @return \Authentication\IdentityInterface|null
     */
    protected function currentIdentity(): ?IdentityInterface
    {
        if ($this->components()->has('Authentication')) {
            $authentication = $this->components()->get('Authentication');
            if (method_exists($authentication, 'getIdentity')) {
                $identity = $authentication->getIdentity();
                if ($identity instanceof IdentityInterface) {
                    return $identity;
                }
            }
        }

        $identity = $this->getRequest()->getAttribute('identity');

        return $identity instanceof IdentityInterface ? $identity : null;
    }

    /**
     * Determine if the identity has already granted the client the requested scopes.
     *
     * @param \Authentication\IdentityInterface $identity Consenting identity
     * @param \Crustum\Tessera\Model\Entity\Client $client Client requesting access
     * @param list<\Crustum\Tessera\Scope> $scopes Requested scopes
     * @return bool
     */
    protected function hasGrantedScopes(IdentityInterface $identity, Client $client, array $scopes): bool
    {
        $activeTokens = Tessera::tokensTable()->find()
            ->where([
                'client_id' => $client->id,
                'user_id' => UserId::normalize($identity->getIdentifier()),
                'revoked' => false,
                'expires >' => DateTime::now(),
            ]);

        if ($scopes === []) {
            return $activeTokens->count() > 0;
        }

        $grantedScopeIds = [];
        foreach ($activeTokens->all() as $token) {
            if ($token instanceof Token) {
                foreach ($token->scopes ?? [] as $scopeId) {
                    $grantedScopeIds[] = $scopeId;
                }
            }
        }

        $requestedScopeIds = array_map(
            static fn(ScopeDefinition $scope): string => $scope->id,
            $scopes,
        );

        return array_diff($requestedScopeIds, $grantedScopeIds) === [];
    }

    /**
     * Approve the authorization request and complete it against the authorization server.
     *
     * @param \League\OAuth2\Server\AuthorizationServer $server Authorization server
     * @param \League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface $authRequest Authorization request
     * @return \Cake\Http\Response
     */
    protected function approveRequest(
        AuthorizationServer $server,
        AuthorizationRequestInterface $authRequest,
    ): Response {
        $authRequest->setAuthorizationApproved(true);

        return $this->withErrorHandling(
            fn(): Response => $this->convertResponse(
                $server->completeAuthorizationRequest($authRequest, new PsrResponse()),
            ),
            $authRequest->getGrantTypeId() === 'implicit',
        );
    }

    /**
     * Send the user to the host login page, returning to this authorize request afterward.
     *
     * Configure `Tessera.loginUrl` (string|array URL) and optional
     * `Tessera.loginRedirectQueryParam` (default `redirect`). Defaults to `/login`.
     *
     * @return \Cake\Http\Response
     */
    protected function promptForLogin(): Response
    {
        $this->getRequest()->getSession()->write('promptedForLogin', true);

        $queryParam = (string)(Configure::read('Tessera.loginRedirectQueryParam') ?? 'redirect');
        $returnTo = $this->getRequest()->getRequestTarget();
        $loginUrl = Configure::read('Tessera.loginUrl');

        if ($loginUrl === null || $loginUrl === '') {
            $loginUrl = '/login';
        }

        if (is_array($loginUrl)) {
            $query = (array)($loginUrl['?'] ?? []);
            $query[$queryParam] = $returnTo;
            $loginUrl['?'] = $query;

            return $this->redirect($loginUrl);
        }

        $loginUrl = (string)$loginUrl;
        $separator = str_contains($loginUrl, '?') ? '&' : '?';

        return $this->redirect($loginUrl . $separator . http_build_query([$queryParam => $returnTo]));
    }
}
