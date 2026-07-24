<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Authenticator;

use Authentication\Identifier\IdentifierInterface;
use Cake\Http\ServerRequest;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use Crustum\Tessera\AccessToken;
use Crustum\Tessera\Authenticator\TokenAuthenticator;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;
use Crustum\Tessera\UserProvider;
use Firebase\JWT\JWT;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Http\Message\ServerRequestInterface;

/**
 * TokenAuthenticator unit tests.
 */
class TokenAuthenticatorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Tessera::withoutCookieEncryption();
        Tessera::ignoreCsrfToken(false);
        Security::setSalt(str_repeat('a', 32));
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Tessera::withCookieEncryption();
        Tessera::encryptTokensUsing(null);
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testUserCanBePulledViaBearerToken(): void
    {
        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);

        $request = (new ServerRequest())->withHeader('Authorization', 'Bearer token');

        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

        $psr = Mockery::mock(ServerRequestInterface::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($psr);
        $psr->shouldReceive('getAttribute')->with('oauth_user_id')->andReturn(1);
        $psr->shouldReceive('getAttribute')->with('oauth_client_id')->andReturn(1);
        $psr->shouldReceive('getAttribute')->with('oauth_access_token_id')->andReturn('token');
        $psr->shouldReceive('getAttributes')->andReturn([
            'oauth_user_id' => 1,
            'oauth_client_id' => 1,
            'oauth_access_token_id' => 'token',
            'oauth_scopes' => [],
        ]);
        $userProvider->shouldReceive('retrieveById')->with(1)->andReturn(new TokenAuthenticatorTestUser());
        $userProvider->shouldReceive('getProviderName')->andReturn('users');
        $clients->shouldReceive('findActive')->with('1')->andReturn($this->makeClient());

        $user = $authenticator->user($request);

        $this->assertInstanceOf(TokenAuthenticatorTestUser::class, $user);
        $this->assertEquals(AccessToken::fromPsrRequest($psr), $user->currentAccessToken());
    }

    /**
     * @return void
     */
    public function testUserIsResolvedOnlyOnce(): void
    {
        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $request = (new ServerRequest())->withHeader('Authorization', 'Bearer token');
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

        $psr = Mockery::mock(ServerRequestInterface::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->once()->andReturn($psr);
        $psr->shouldReceive('getAttribute')->with('oauth_user_id')->andReturn(1);
        $psr->shouldReceive('getAttribute')->with('oauth_client_id')->andReturn(1);
        $psr->shouldReceive('getAttribute')->with('oauth_access_token_id')->andReturn('token');
        $psr->shouldReceive('getAttributes')->andReturn([
            'oauth_user_id' => 1,
            'oauth_client_id' => 1,
            'oauth_access_token_id' => 'token',
            'oauth_scopes' => [],
        ]);
        $userProvider->shouldReceive('retrieveById')->once()->with(1)->andReturn(new TokenAuthenticatorTestUser());
        $userProvider->shouldReceive('getProviderName')->andReturn('users');
        $clients->shouldReceive('findActive')->with('1')->andReturn($this->makeClient());

        $user = $authenticator->user($request);
        $user2 = $authenticator->user($request);

        $this->assertSame($user, $user2);
    }

    /**
     * @return void
     */
    public function testNoUserWhenOauthThrowsException(): void
    {
        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $request = (new ServerRequest())->withHeader('Authorization', 'Bearer token');
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

        $resourceServer->shouldReceive('validateAuthenticatedRequest')
            ->once()
            ->andThrow(new OAuthServerException('message', 500, 'error type'));

        $this->assertNull($authenticator->user($request));
        $this->assertNull($authenticator->user($request));
    }

    /**
     * @return void
     */
    public function testNullWhenNoUserFound(): void
    {
        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $request = (new ServerRequest())->withHeader('Authorization', 'Bearer token');
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

        $clients->shouldReceive('findActive')->with('1')->andReturn($this->makeClient());
        $psr = Mockery::mock(ServerRequestInterface::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($psr);
        $psr->shouldReceive('getAttribute')->with('oauth_user_id')->andReturn(1);
        $psr->shouldReceive('getAttribute')->with('oauth_client_id')->andReturn(1);
        $userProvider->shouldReceive('retrieveById')->with(1)->andReturn(null);
        $userProvider->shouldReceive('getProviderName')->andReturn('users');

        $this->assertNull($authenticator->user($request));
    }

    /**
     * @return void
     */
    public function testNullForClientCredentialsToken(): void
    {
        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $clientId = '019c9d23-9763-7303-9bdb-3a0a6bf0ef90';
        $client = $this->makeClient(['client_credentials']);
        $clients->shouldReceive('findActive')->with($clientId)->andReturn($client);

        $request = (new ServerRequest())->withHeader('Authorization', 'Bearer token');
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

        $psr = Mockery::mock(ServerRequestInterface::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($psr);
        $psr->shouldReceive('getAttribute')->with('oauth_user_id')->andReturn($clientId);
        $psr->shouldReceive('getAttribute')->with('oauth_client_id')->andReturn($clientId);
        $userProvider->shouldReceive('getProviderName')->andReturn('users');
        $userProvider->shouldReceive('retrieveById')->never();

        $this->assertNull($authenticator->user($request));
    }

    /**
     * @return void
     */
    public function testUsersRetrievedFromCookiesWithoutEncryption(): void
    {
        Tessera::withoutCookieEncryption();
        Tessera::encryptTokensUsing(fn (string $defaultKey): string => $defaultKey . '.mykey');

        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $clients->shouldReceive('findActive')->with('1')->andReturn($this->makeClient());

        $jwt = JWT::encode([
            'sub' => 1,
            'aud' => 1,
            'csrf' => 'token',
            'exp' => DateTime::now()->addMinutes(10)->getTimestamp(),
        ], Tessera::tokenEncryptionKey(Security::getSalt()), 'HS256');

        $request = (new ServerRequest([
            'cookies' => [Tessera::cookie() => $jwt],
        ]))->withHeader('X-CSRF-TOKEN', 'token');

        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);
        $expectedUser = new TokenAuthenticatorTestUser();
        $userProvider->shouldReceive('retrieveById')->with(1)->andReturn($expectedUser);
        $userProvider->shouldReceive('getProviderName')->andReturn('users');

        $this->assertSame($expectedUser, $authenticator->user($request));
    }

    /**
     * @return void
     */
    public function testUserIsResolvedWhenUserIdMatchesClientId(): void
    {
        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $clients->shouldReceive('findActive')->with('1')->andReturn($this->makeClient());

        $request = (new ServerRequest())->withHeader('Authorization', 'Bearer token');
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

        $psr = Mockery::mock(ServerRequestInterface::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($psr);
        $psr->shouldReceive('getAttribute')->with('oauth_user_id')->andReturn(1);
        $psr->shouldReceive('getAttribute')->with('oauth_client_id')->andReturn(1);
        $psr->shouldReceive('getAttribute')->with('oauth_access_token_id')->andReturn('token');
        $psr->shouldReceive('getAttributes')->andReturn([
            'oauth_user_id' => 1,
            'oauth_client_id' => 1,
            'oauth_access_token_id' => 'token',
            'oauth_scopes' => [],
        ]);
        $userProvider->shouldReceive('getProviderName')->andReturn('users');
        $userProvider->shouldReceive('retrieveById')->with(1)->andReturn(new TokenAuthenticatorTestUser());

        $user = $authenticator->user($request);

        $this->assertInstanceOf(TokenAuthenticatorTestUser::class, $user);
        $this->assertEquals(AccessToken::fromPsrRequest($psr), $user->currentAccessToken());
    }

    /**
     * @return void
     */
    public function testUsersMayBeRetrievedFromCookiesWithCsrfTokenHeader(): void
    {
        Tessera::withCookieEncryption();

        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $clients->shouldReceive('findActive')->with('1')->andReturn($this->makeClient());

        $request = $this->makeCookieRequest('token');
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);
        $expectedUser = new TokenAuthenticatorTestUser();
        $userProvider->shouldReceive('retrieveById')->with(1)->andReturn($expectedUser);
        $userProvider->shouldReceive('getProviderName')->andReturn('users');

        $this->assertSame($expectedUser, $authenticator->user($request));
    }

    /**
     * @return void
     */
    public function testUsersMayBeRetrievedFromCookiesWithXsrfTokenHeader(): void
    {
        Tessera::withCookieEncryption();

        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $clients->shouldReceive('findActive')->with('1')->andReturn($this->makeClient());

        $request = $this->makeCookieRequest(null, Security::encrypt('token', Security::getSalt()));
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);
        $expectedUser = new TokenAuthenticatorTestUser();
        $userProvider->shouldReceive('retrieveById')->with(1)->andReturn($expectedUser);
        $userProvider->shouldReceive('getProviderName')->andReturn('users');

        $this->assertSame($expectedUser, $authenticator->user($request));
    }

    /**
     * @return void
     */
    public function testCookieXsrfIsVerifiedAgainstCsrfTokenHeader(): void
    {
        Tessera::withCookieEncryption();

        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $request = $this->makeCookieRequest('wrong_token');
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

        $userProvider->shouldReceive('retrieveById')->never();

        $this->assertNull($authenticator->user($request));
    }

    /**
     * @return void
     */
    public function testCookieWithEmptyCsrfClaimIsRejected(): void
    {
        Tessera::withCookieEncryption();
        Tessera::ignoreCsrfToken(false);

        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $request = $this->makeCookieRequest(null, null, [
            'csrf' => '',
        ]);
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

        $userProvider->shouldReceive('retrieveById')->never();

        $this->assertNull($authenticator->user($request));
    }

    /**
     * @return void
     */
    public function testActingAsUserStubIsIgnoredWhenNotAllowed(): void
    {
        Tessera::allowActingAs(false);
        Tessera::$actingAsUser = new TokenAuthenticatorTestUser();

        try {
            $resourceServer = Mockery::mock(ResourceServer::class);
            $userProvider = Mockery::mock(UserProvider::class);
            $clients = Mockery::mock(ClientRepository::class);
            $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

            $this->assertNull($authenticator->user(new ServerRequest()));
        } finally {
            Tessera::allowActingAs(true);
            Tessera::clearActingAs();
        }
    }

    /**
     * @return void
     */
    public function testCookieXsrfIsVerifiedAgainstXsrfTokenHeader(): void
    {
        Tessera::withCookieEncryption();

        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $request = $this->makeCookieRequest(
            null,
            Security::encrypt('wrong_token', Security::getSalt()),
        );
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

        $userProvider->shouldReceive('retrieveById')->never();

        $this->assertNull($authenticator->user($request));
    }

    /**
     * @return void
     */
    public function testUsersMayBeRetrievedFromCookiesWithXsrfTokenHeaderWhenUsingCustomEncryptionKey(): void
    {
        Tessera::withCookieEncryption();
        Tessera::encryptTokensUsing(fn (string $defaultKey): string => $defaultKey . '.mykey');

        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $clients->shouldReceive('findActive')->with('1')->andReturn($this->makeClient());

        $request = $this->makeCookieRequest(null, Security::encrypt('token', Security::getSalt()));
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);
        $expectedUser = new TokenAuthenticatorTestUser();
        $userProvider->shouldReceive('retrieveById')->with(1)->andReturn($expectedUser);
        $userProvider->shouldReceive('getProviderName')->andReturn('users');

        $this->assertSame($expectedUser, $authenticator->user($request));
    }

    /**
     * @return void
     */
    public function testXsrfTokenCookieWithoutTokenHeaderIsNotAccepted(): void
    {
        Tessera::withCookieEncryption();

        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $request = (new ServerRequest([
            'cookies' => [
                'XSRF-TOKEN' => Security::encrypt('token', Security::getSalt()),
                Tessera::cookie() => $this->encodeApiTokenCookie([
                    'sub' => 1,
                    'aud' => 1,
                    'csrf' => 'token',
                    'exp' => DateTime::now()->addMinutes(10)->getTimestamp(),
                ]),
            ],
        ]));
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

        $userProvider->shouldReceive('retrieveById')->never();

        $this->assertNull($authenticator->user($request));
    }

    /**
     * @return void
     */
    public function testExpiredCookiesMayNotBeUsed(): void
    {
        Tessera::withCookieEncryption();

        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $request = $this->makeCookieRequest('token', null, [
            'sub' => 1,
            'aud' => 1,
            'csrf' => 'token',
            'exp' => DateTime::now()->subMinutes(10)->getTimestamp(),
        ]);
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

        $userProvider->shouldReceive('retrieveById')->never();

        $this->assertNull($authenticator->user($request));
    }

    /**
     * @return void
     */
    public function testCsrfCheckCanBeDisabled(): void
    {
        Tessera::withCookieEncryption();
        Tessera::ignoreCsrfToken();

        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $clients->shouldReceive('findActive')->with('1')->andReturn($this->makeClient());

        $request = (new ServerRequest([
            'cookies' => [
                Tessera::cookie() => $this->encodeApiTokenCookie([
                    'sub' => 1,
                    'aud' => 1,
                    'exp' => DateTime::now()->addMinutes(10)->getTimestamp(),
                ]),
            ],
        ]));
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);
        $expectedUser = new TokenAuthenticatorTestUser();
        $userProvider->shouldReceive('retrieveById')->with(1)->andReturn($expectedUser);
        $userProvider->shouldReceive('getProviderName')->andReturn('users');

        $this->assertSame($expectedUser, $authenticator->user($request));
    }

    /**
     * @return void
     */
    public function testClientCanBePulledViaBearerToken(): void
    {
        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $request = (new ServerRequest())->withHeader('Authorization', 'Bearer token');
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

        $psr = Mockery::mock(ServerRequestInterface::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($psr);
        $psr->shouldReceive('getAttribute')->with('oauth_client_id')->andReturn(1);
        $clients->shouldReceive('findActive')->with('1')->andReturn($this->makeClient());

        $client = $authenticator->client($request);

        $this->assertInstanceOf(Client::class, $client);
    }

    /**
     * @return void
     */
    public function testClientIsResolvedOnlyOnce(): void
    {
        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $request = (new ServerRequest())->withHeader('Authorization', 'Bearer token');
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

        $psr = Mockery::mock(ServerRequestInterface::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($psr);
        $psr->shouldReceive('getAttribute')->with('oauth_client_id')->andReturn(1);
        $clients->shouldReceive('findActive')->with('1')->andReturn($this->makeClient());

        $client = $authenticator->client($request);

        $clients->shouldReceive('findActive')->never();

        $client2 = $authenticator->client();

        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame($client, $client2);
    }

    /**
     * @return void
     */
    public function testNoClientIsReturnedWhenOauthThrowsException(): void
    {
        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $request = (new ServerRequest())->withHeader('Authorization', 'Bearer token');
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

        $resourceServer->shouldReceive('validateAuthenticatedRequest')
            ->andThrow(new OAuthServerException('message', 500, 'error type'));

        $this->assertNull($authenticator->client($request));
        $this->assertNull($authenticator->client($request));
    }

    /**
     * @return void
     */
    public function testNullIsReturnedIfNoClientIsFound(): void
    {
        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $request = (new ServerRequest())->withHeader('Authorization', 'Bearer token');
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);

        $psr = Mockery::mock(ServerRequestInterface::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($psr);
        $psr->shouldReceive('getAttribute')->with('oauth_client_id')->andReturn(1);
        $clients->shouldReceive('findActive')->with('1')->andReturn(null);

        $this->assertNull($authenticator->client($request));
    }

    /**
     * @return void
     */
    public function testClientsMayBeRetrievedFromCookies(): void
    {
        Tessera::withCookieEncryption();

        $resourceServer = Mockery::mock(ResourceServer::class);
        $userProvider = Mockery::mock(UserProvider::class);
        $clients = Mockery::mock(ClientRepository::class);
        $request = $this->makeCookieRequest('token');
        $authenticator = $this->makeAuthenticator($resourceServer, $userProvider, $clients);
        $expectedClient = $this->makeClient();
        $clients->shouldReceive('findActive')->with('1')->andReturn($expectedClient);

        $client = $authenticator->client($request);

        $this->assertSame($expectedClient, $client);
    }

    /**
     * Build a request carrying an encrypted API token cookie and optional CSRF headers.
     *
     * @param string|null $csrfHeader X-CSRF-TOKEN header value
     * @param string|null $xsrfHeader X-XSRF-TOKEN header value
     * @param array<string, mixed> $jwtClaims JWT payload overrides
     * @return \Cake\Http\ServerRequest
     */
    protected function makeCookieRequest(
        ?string $csrfHeader = 'token',
        ?string $xsrfHeader = null,
        array $jwtClaims = [],
    ): ServerRequest {
        $claims = array_merge([
            'sub' => 1,
            'aud' => 1,
            'csrf' => 'token',
            'exp' => DateTime::now()->addMinutes(10)->getTimestamp(),
        ], $jwtClaims);

        $request = (new ServerRequest([
            'cookies' => [
                Tessera::cookie() => $this->encodeApiTokenCookie($claims),
            ],
        ]));

        if ($csrfHeader !== null) {
            $request = $request->withHeader('X-CSRF-TOKEN', $csrfHeader);
        }

        if ($xsrfHeader !== null) {
            return $request->withHeader('X-XSRF-TOKEN', $xsrfHeader);
        }

        return $request;
    }

    /**
     * Encode and optionally encrypt the Tessera API token cookie value.
     *
     * @param array<string, mixed> $claims JWT payload
     * @return string
     */
    protected function encodeApiTokenCookie(array $claims): string
    {
        $jwt = JWT::encode(
            $claims,
            Tessera::tokenEncryptionKey(Security::getSalt()),
            'HS256',
        );

        if (Tessera::$decryptsCookies) {
            return Security::encrypt($jwt, Security::getSalt());
        }

        return $jwt;
    }

    /**
     * @param \League\OAuth2\Server\ResourceServer $server Server
     * @param \Crustum\Tessera\UserProvider $users Users
     * @param \Crustum\Tessera\ClientRepository $clients Clients
     * @return \Crustum\Tessera\Authenticator\TokenAuthenticator
     */
    protected function makeAuthenticator(
        ResourceServer $server,
        UserProvider $users,
        ClientRepository $clients,
    ): TokenAuthenticator {
        $identifier = Mockery::mock(IdentifierInterface::class);

        return new TokenAuthenticator($identifier, [
            'server' => $server,
            'clients' => $clients,
            'users' => $users,
            'providerName' => 'users',
        ]);
    }

    /**
     * @param list<string> $grantTypes Grant types
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    protected function makeClient(array $grantTypes = []): Client
    {
        return new Client([
            'id' => '1',
            'name' => 'Client',
            'grant_types' => $grantTypes,
            'provider' => null,
            'revoked' => false,
        ], ['markNew' => false, 'guard' => false]);
    }
}
