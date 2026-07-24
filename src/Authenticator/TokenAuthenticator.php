<?php
declare(strict_types=1);

namespace Crustum\Tessera\Authenticator;

use ArrayAccess;
use Authentication\Authenticator\AbstractAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Authenticator\ResultInterface;
use Authentication\Authenticator\StatelessInterface;
use Authentication\Identifier\IdentifierInterface;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Utility\Security;
use Crustum\Tessera\AccessToken;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;
use Crustum\Tessera\TransientToken;
use Crustum\Tessera\UserProvider;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Authenticate API requests via Bearer access tokens or the Tessera JWT cookie.
 */
class TokenAuthenticator extends AbstractAuthenticator implements StatelessInterface
{
    /**
     * @var \Crustum\Tessera\Model\Entity\Client|null
     */
    protected ?Client $client = null;

    /**
     * @var \Crustum\Tessera\Contracts\OAuthenticatable|null
     */
    protected ?OAuthenticatable $user = null;

    /**
     * @var bool
     */
    protected bool $resolved = false;

    /**
     * @var \League\OAuth2\Server\ResourceServer|null
     */
    protected ?ResourceServer $server = null;

    /**
     * @var \Crustum\Tessera\ClientRepository|null
     */
    protected ?ClientRepository $clients = null;

    /**
     * @var \Crustum\Tessera\UserProvider|null
     */
    protected ?UserProvider $users = null;

    /**
     * @param \Authentication\Identifier\IdentifierInterface $identifier Identifier
     * @param array<string, mixed> $config Config (`server`, `clients`, `users`, `providerName`)
     */
    public function __construct(IdentifierInterface $identifier, array $config = [])
    {
        parent::__construct($identifier, $config);

        if (isset($config['server']) && $config['server'] instanceof ResourceServer) {
            $this->server = $config['server'];
        }

        if (isset($config['clients']) && $config['clients'] instanceof ClientRepository) {
            $this->clients = $config['clients'];
        }

        if (isset($config['users']) && $config['users'] instanceof UserProvider) {
            $this->users = $config['users'];
        }
    }

    /**
     * @inheritDoc
     */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        $user = $this->user($request);
        if (!$user instanceof ArrayAccess) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        }

        return new Result($user, Result::SUCCESS);
    }

    /**
     * @inheritDoc
     */
    public function unauthorizedChallenge(ServerRequestInterface $request): void
    {
    }

    /**
     * Resolve the authenticated user for the request (cached per authenticator instance).
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return \Crustum\Tessera\Contracts\OAuthenticatable|null
     */
    public function user(ServerRequestInterface $request): ?OAuthenticatable
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;

        if (Tessera::$actingAsAllowed && Tessera::$actingAsUser instanceof OAuthenticatable) {
            return $this->user = Tessera::$actingAsUser;
        }

        if ($this->bearerToken($request) !== null) {
            return $this->user = $this->authenticateViaBearerToken($request);
        }

        if ($this->cookieToken($request) !== null) {
            return $this->user = $this->authenticateViaCookie($request);
        }

        return $this->user = null;
    }

    /**
     * OAuth client for the current request, if resolved.
     *
     * @param \Psr\Http\Message\ServerRequestInterface|null $request Request when client not yet resolved
     * @return \Crustum\Tessera\Model\Entity\Client|null
     */
    public function client(?ServerRequestInterface $request = null): ?Client
    {
        if ($this->client instanceof Client) {
            return $this->client;
        }

        if (Tessera::$actingAsAllowed && Tessera::$actingAsClient instanceof Client) {
            return $this->client = Tessera::$actingAsClient;
        }

        if (!$request instanceof ServerRequestInterface) {
            return null;
        }

        if ($this->bearerToken($request) !== null) {
            $psr = $this->getPsrRequestViaBearerToken($request);
            if (!$psr instanceof ServerRequestInterface) {
                return null;
            }

            return $this->client = $this->clientRepository()->findActive(
                (string)$psr->getAttribute('oauth_client_id'),
            );
        }

        if ($this->cookieToken($request) !== null) {
            $token = $this->getTokenViaCookie($request);
            if ($token === null || !isset($token['aud'])) {
                return null;
            }

            return $this->client = $this->clientRepository()->findActive((string)$token['aud']);
        }

        return null;
    }

    /**
     * Authenticate via Authorization Bearer token.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return \Crustum\Tessera\Contracts\OAuthenticatable|null
     */
    protected function authenticateViaBearerToken(ServerRequestInterface $request): ?OAuthenticatable
    {
        $psr = $this->getPsrRequestViaBearerToken($request);
        if (!$psr instanceof ServerRequestInterface) {
            return null;
        }

        $client = $this->clientRepository()->findActive((string)$psr->getAttribute('oauth_client_id'));
        if (
            !$client
            || ($client->provider && $client->provider !== $this->userProvider()->getProviderName())
        ) {
            return null;
        }

        $this->client = $client;

        $oauthUserId = $psr->getAttribute('oauth_user_id');
        if (
            empty($oauthUserId)
            || (
                (string)$oauthUserId === (string)$psr->getAttribute('oauth_client_id')
                && $client->hasGrantType('client_credentials')
            )
        ) {
            return null;
        }

        try {
            $user = $this->userProvider()->retrieveById(is_scalar($oauthUserId) ? $oauthUserId : (string)$oauthUserId);
        } catch (Exception) {
            return null;
        }

        if (!$user instanceof OAuthenticatable) {
            return null;
        }

        return $user->withAccessToken(AccessToken::fromPsrRequest($psr));
    }

    /**
     * Validate the Bearer token with the League resource server.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return \Psr\Http\Message\ServerRequestInterface|null
     */
    protected function getPsrRequestViaBearerToken(ServerRequestInterface $request): ?ServerRequestInterface
    {
        try {
            return $this->resourceServer()->validateAuthenticatedRequest($request);
        } catch (OAuthServerException $oAuthServerException) {
            Log::error($oAuthServerException->getMessage());

            return null;
        }
    }

    /**
     * Authenticate via the Tessera API token cookie.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return \Crustum\Tessera\Contracts\OAuthenticatable|null
     */
    protected function authenticateViaCookie(ServerRequestInterface $request): ?OAuthenticatable
    {
        $token = $this->getTokenViaCookie($request);
        if ($token === null) {
            return null;
        }

        try {
            $user = $this->userProvider()->retrieveById($token['sub']);
        } catch (Exception) {
            return null;
        }

        if (!$user instanceof OAuthenticatable) {
            return null;
        }

        return $user->withAccessToken(new TransientToken());
    }

    /**
     * Decode and validate the JWT cookie payload.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return array<string, mixed>|null
     */
    protected function getTokenViaCookie(ServerRequestInterface $request): ?array
    {
        try {
            $token = $this->decodeJwtTokenCookie($request);
        } catch (Exception) {
            return null;
        }

        if (isset($token['expiry']) && time() >= (int)$token['expiry']) {
            return null;
        }

        if (!Tessera::$ignoreCsrfToken && !$this->validCsrf($token, $request)) {
            return null;
        }

        return $token;
    }

    /**
     * Decode the JWT from the Tessera cookie.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return array<string, mixed>
     */
    protected function decodeJwtTokenCookie(ServerRequestInterface $request): array
    {
        $jwt = (string)$this->cookieToken($request);
        if (Tessera::$decryptsCookies) {
            $decrypted = Security::decrypt($jwt, Security::getSalt());
            if (is_string($decrypted) && $decrypted !== '') {
                $jwt = $decrypted;
            }
        }

        $key = Tessera::tokenEncryptionKey(Security::getSalt());

        return (array)JWT::decode($jwt, new Key($key, 'HS256'));
    }

    /**
     * Whether the CSRF claim matches the request header.
     *
     * @param array<string, mixed> $token Cookie JWT claims
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return bool
     */
    protected function validCsrf(array $token, ServerRequestInterface $request): bool
    {
        $requestToken = $this->getTokenFromRequest($request);

        return isset($token['csrf'])
            && is_string($requestToken)
            && hash_equals((string)$token['csrf'], $requestToken);
    }

    /**
     * CSRF token from X-CSRF-TOKEN or X-XSRF-TOKEN headers.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return string|null
     */
    protected function getTokenFromRequest(ServerRequestInterface $request): ?string
    {
        $token = $request->getHeaderLine('X-CSRF-TOKEN');
        if ($token !== '') {
            return $token;
        }

        $xsrf = $request->getHeaderLine('X-XSRF-TOKEN');
        if ($xsrf === '') {
            return null;
        }

        if (Tessera::$decryptsCookies) {
            $decrypted = Security::decrypt($xsrf, Security::getSalt());
            if (is_string($decrypted) && $decrypted !== '') {
                return $decrypted;
            }
        }

        return $xsrf;
    }

    /**
     * Bearer token string from the Authorization header.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return string|null
     */
    protected function bearerToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if ($header === '' || !preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Tessera API cookie value.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return string|null
     */
    protected function cookieToken(ServerRequestInterface $request): ?string
    {
        $cookies = $request->getCookieParams();
        $name = Tessera::cookie();
        $value = $cookies[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return \League\OAuth2\Server\ResourceServer
     */
    protected function resourceServer(): ResourceServer
    {
        if (!$this->server instanceof ResourceServer) {
            throw new RuntimeException('ResourceServer has not been configured for TokenAuthenticator.');
        }

        return $this->server;
    }

    /**
     * @return \Crustum\Tessera\ClientRepository
     */
    protected function clientRepository(): ClientRepository
    {
        return $this->clients ??= new ClientRepository();
    }

    /**
     * @return \Crustum\Tessera\UserProvider
     */
    protected function userProvider(): UserProvider
    {
        return $this->users ??= new UserProvider(
            $this->getIdentifier(),
            (string)($this->getConfig('providerName') ?? Configure::read('Tessera.guard') ?? 'users'),
        );
    }
}
