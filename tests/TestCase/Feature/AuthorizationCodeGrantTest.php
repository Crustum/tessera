<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Cake\Utility\Security;
use Cake\Utility\Text;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;

/**
 * Authorization code grant Feature coverage.
 */
class AuthorizationCodeGrantTest extends TesseraTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Tessera::tokensCan([
            'create' => 'Create',
            'read' => 'Read',
            'update' => 'Update',
            'delete' => 'Delete',
        ]);

        Tessera::authorizationView(static fn(array $params): array => $params);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Tessera::$authorizationView = null;
        Tessera::tokensCan([]);

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testIssueAccessToken(): void
    {
        $client = $this->makeAuthCodeClient();
        $redirect = $client->redirect_uris[0];
        $state = Text::uuid();

        $user = $this->createUser();
        $this->loginAs($user);

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'create read',
            'state' => $state,
        ]));

        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertEqualsCanonicalizing(
            ['client', 'user', 'scopes', 'request', 'authToken'],
            array_keys($json),
        );
        $this->assertSame(
            array_map(
                static fn($scope): array => $scope->toArray(),
                Tessera::scopesFor(['create', 'read']),
            ),
            $json['scopes'],
        );

        $this->syncSession();
        $this->post('/oauth/authorize', ['auth_token' => $json['authToken']]);
        $this->assertResponseCode(302);
        $this->assertNull($this->getSession()->read('authRequest'));
        $this->assertNull($this->getSession()->read('authToken'));

        $location = $this->_response->getHeaderLine('Location');
        parse_str((string)parse_url($location, PHP_URL_QUERY), $params);

        $this->assertStringStartsWith($redirect . '?', $location);
        $this->assertSame($state, $params['state']);
        $this->assertArrayHasKey('code', $params);

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'redirect_uri' => $redirect,
            'code' => $params['code'],
        ]);

        $this->assertResponseOk();
        $tokenJson = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($tokenJson);
        $this->assertArrayHasKey('access_token', $tokenJson);
        $this->assertArrayHasKey('refresh_token', $tokenJson);
        $this->assertSame('Bearer', $tokenJson['token_type']);
        $this->assertEqualsWithDelta(31536000, $tokenJson['expires_in'], 2);

        $this->clearSession();
        $this->configRequest([
            'headers' => [
                'Authorization' => $tokenJson['token_type'] . ' ' . $tokenJson['access_token'],
            ],
        ]);
        $this->get('/token-info');
        $this->assertResponseOk();

        $info = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($info);
        $this->assertSame($client->id, $info['oauth_client_id']);
        $this->assertEquals($user->id, $info['oauth_user_id']);
        $this->assertSame(['create', 'read'], $info['oauth_scopes']);
    }

    /**
     * @return void
     */
    public function testDenyAuthorization(): void
    {
        $client = $this->makeAuthCodeClient();
        $redirect = $client->redirect_uris[0];
        $state = Text::uuid();

        $this->loginAs($this->createUser());

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => '',
            'state' => $state,
        ]));

        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);

        $this->syncSession();
        $this->_sendRequest('/oauth/authorize', 'DELETE', ['auth_token' => $json['authToken']]);
        $this->assertResponseCode(302);
        $this->assertNull($this->getSession()->read('authRequest'));
        $this->assertNull($this->getSession()->read('authToken'));

        $location = $this->_response->getHeaderLine('Location');
        parse_str((string)parse_url($location, PHP_URL_QUERY), $params);

        $this->assertStringStartsWith($redirect . '?', $location);
        $this->assertSame($state, $params['state']);
        $this->assertSame('access_denied', $params['error']);
        $this->assertArrayHasKey('error_description', $params);
    }

    /**
     * @return void
     */
    public function testSkipsAuthorizationWhenHasGrantedScopes(): void
    {
        $client = $this->makeAuthCodeClient();
        $redirect = $client->redirect_uris[0];

        $this->loginAs($this->createUser());
        $this->issueTokenForScopes($client, 'create read update');

        $state = Text::uuid();
        $this->syncSession();
        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'create read',
            'state' => $state,
        ]));
        $this->assertResponseCode(302);

        $location = $this->_response->getHeaderLine('Location');
        parse_str((string)parse_url($location, PHP_URL_QUERY), $params);

        $this->assertStringStartsWith($redirect . '?', $location);
        $this->assertSame($state, $params['state']);
        $this->assertArrayHasKey('code', $params);
    }

    /**
     * @return void
     */
    public function testSkipsAuthorizationWhenHasActiveTokensAndEmptyScope(): void
    {
        $client = $this->makeAuthCodeClient();
        $redirect = $client->redirect_uris[0];

        $this->loginAs($this->createUser());
        $this->issueTokenForScopes($client, '');

        $state = Text::uuid();
        $this->syncSession();
        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => '',
            'state' => $state,
        ]));
        $this->assertResponseCode(302);

        $location = $this->_response->getHeaderLine('Location');
        parse_str((string)parse_url($location, PHP_URL_QUERY), $params);

        $this->assertStringStartsWith($redirect . '?', $location);
        $this->assertSame($state, $params['state']);
        $this->assertArrayHasKey('code', $params);
    }

    /**
     * @return void
     */
    public function testPromptConsentForNewScope(): void
    {
        $client = $this->makeAuthCodeClient();
        $redirect = $client->redirect_uris[0];

        $this->loginAs($this->createUser());
        $this->issueTokenForScopes($client, 'create read');

        $this->syncSession();
        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'create update',
            'state' => Text::uuid(),
        ]));

        $this->assertResponseOk();
        $this->assertNotNull($this->getSession()->read('authRequest'));
        $this->assertNotNull($this->getSession()->read('authToken'));
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertEqualsCanonicalizing(
            ['client', 'user', 'scopes', 'request', 'authToken'],
            array_keys($json),
        );
        $this->assertSame(
            array_map(
                static fn($scope): array => $scope->toArray(),
                Tessera::scopesFor(['create', 'update']),
            ),
            $json['scopes'],
        );
    }

    /**
     * @return void
     */
    public function testAuthRequestSerialization(): void
    {
        $client = $this->makeAuthCodeClient();
        $redirect = $client->redirect_uris[0];

        $this->loginAs($this->createUser());

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'create read',
            'state' => Text::uuid(),
        ]));

        $this->assertResponseOk();
        $this->assertNotNull($this->getSession()->read('authRequest'));
        $this->assertNotNull($this->getSession()->read('authToken'));
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertEqualsCanonicalizing(
            ['client', 'user', 'scopes', 'request', 'authToken'],
            array_keys($json),
        );
        $this->assertSame(
            array_map(
                static fn($scope): array => $scope->toArray(),
                Tessera::scopesFor(['create', 'read']),
            ),
            $json['scopes'],
        );

        $this->syncSession();
        $this->post('/oauth/authorize', ['auth_token' => $json['authToken']]);
        $this->assertResponseCode(302);
        $this->assertNull($this->getSession()->read('authRequest'));
        $this->assertNull($this->getSession()->read('authToken'));
    }

    /**
     * @return void
     */
    public function testValidateAuthorizationRequest(): void
    {
        $client = $this->makeAuthCodeClient();

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'https://evil.example/callback',
            'response_type' => 'code',
            'scope' => '',
            'state' => Text::uuid(),
        ]));

        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertSame('invalid_client', $json['error']);
        $this->assertArrayHasKey('error_description', $json);
    }

    /**
     * @return void
     */
    public function testValidateScopes(): void
    {
        $client = $this->makeAuthCodeClient();
        $redirect = $client->redirect_uris[0];
        $state = Text::uuid();

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'foo',
            'state' => $state,
        ]));
        $this->assertResponseCode(302);

        $location = $this->_response->getHeaderLine('Location');
        parse_str((string)parse_url($location, PHP_URL_QUERY), $params);

        $this->assertStringStartsWith($redirect . '?', $location);
        $this->assertSame($state, $params['state']);
        $this->assertSame('invalid_scope', $params['error']);
        $this->assertArrayHasKey('error_description', $params);
    }

    /**
     * @return void
     */
    public function testRedirectGuestUser(): void
    {
        $client = $this->makeAuthCodeClient();

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect_uris[0],
            'response_type' => 'code',
        ]));

        $this->assertResponseCode(302);
        $this->assertTrue((bool)$this->getSession()->read('promptedForLogin'));
        $this->assertStringContainsString('/login', $this->_response->getHeaderLine('Location'));
    }

    /**
     * @return void
     */
    public function testPromptNone(): void
    {
        $client = $this->makeAuthCodeClient();
        $redirect = $client->redirect_uris[0];
        $state = Text::uuid();

        $this->loginAs($this->createUser());
        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'state' => $state,
            'prompt' => 'none',
        ]));
        $this->assertResponseCode(302);

        $location = $this->_response->getHeaderLine('Location');
        parse_str((string)parse_url($location, PHP_URL_QUERY), $params);

        $this->assertStringStartsWith($redirect . '?', $location);
        $this->assertSame($state, $params['state']);
        $this->assertSame('consent_required', $params['error']);
        $this->assertArrayHasKey('error_description', $params);
    }

    /**
     * @return void
     */
    public function testPromptNoneLoginRequired(): void
    {
        $client = $this->makeAuthCodeClient();
        $redirect = $client->redirect_uris[0];
        $state = Text::uuid();

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'state' => $state,
            'prompt' => 'none',
        ]));
        $this->assertResponseCode(302);

        $location = $this->_response->getHeaderLine('Location');
        parse_str((string)parse_url($location, PHP_URL_QUERY), $params);

        $this->assertStringStartsWith($redirect . '?', $location);
        $this->assertSame($state, $params['state']);
        $this->assertSame('login_required', $params['error']);
        $this->assertArrayHasKey('error_description', $params);
    }

    /**
     * @return void
     */
    public function testPromptConsent(): void
    {
        $client = $this->makeAuthCodeClient();
        $redirect = $client->redirect_uris[0];

        $this->loginAs($this->createUser());
        $this->issueTokenForScopes($client, 'create read update');

        $this->syncSession();
        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'create read',
            'state' => Text::uuid(),
            'prompt' => 'consent',
        ]));

        $this->assertResponseOk();
        $this->assertNotNull($this->getSession()->read('authRequest'));
        $this->assertNotNull($this->getSession()->read('authToken'));
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertEqualsCanonicalizing(
            ['client', 'user', 'scopes', 'request', 'authToken'],
            array_keys($json),
        );
        $this->assertSame(
            array_map(
                static fn($scope): array => $scope->toArray(),
                Tessera::scopesFor(['create', 'read']),
            ),
            $json['scopes'],
        );
    }

    /**
     * @return void
     */
    public function testPromptLogin(): void
    {
        $client = $this->makeAuthCodeClient();

        $this->loginAs($this->createUser());
        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect_uris[0],
            'response_type' => 'code',
            'scope' => 'create read update',
            'state' => Text::uuid(),
            'prompt' => 'login',
        ]));

        $this->assertResponseCode(302);
        $this->assertTrue((bool)$this->getSession()->read('promptedForLogin'));
        $this->assertStringContainsString('/login', $this->_response->getHeaderLine('Location'));
    }

    /**
     * @return void
     */
    public function testPromptLoginConsent(): void
    {
        $client = $this->makeAuthCodeClient();
        $redirect = $client->redirect_uris[0];

        $user = $this->createUser();
        $this->loginAs($user);
        $this->issueTokenForScopes($client, 'create read update');

        $this->syncSession();
        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'create read',
            'state' => Text::uuid(),
            'prompt' => 'login consent',
        ]));

        $this->assertResponseCode(302);
        $this->assertTrue((bool)$this->getSession()->read('promptedForLogin'));
        $loginLocation = $this->_response->getHeaderLine('Location');
        $this->assertStringContainsString('/login', $loginLocation);

        parse_str((string)parse_url($loginLocation, PHP_URL_QUERY), $loginParams);
        $this->assertArrayHasKey('redirect', $loginParams);
        $intendedUrl = $loginParams['redirect'];

        $this->syncSession();
        $this->loginAs($user);
        $this->get($intendedUrl);
        $this->assertResponseOk();
        $this->assertNotNull($this->getSession()->read('authRequest'));
        $this->assertNotNull($this->getSession()->read('authToken'));
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertEqualsCanonicalizing(
            ['client', 'user', 'scopes', 'request', 'authToken'],
            array_keys($json),
        );
        $this->assertSame(
            array_map(
                static fn($scope): array => $scope->toArray(),
                Tessera::scopesFor(['create', 'read']),
            ),
            $json['scopes'],
        );
    }

    /**
     * @return void
     */
    public function testPromptContainsNone(): void
    {
        $client = $this->makeAuthCodeClient();
        $redirect = $client->redirect_uris[0];
        $state = Text::uuid();

        $this->loginAs($this->createUser());
        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'state' => $state,
            'prompt' => 'none login consent select_account',
        ]));
        $this->assertResponseCode(302);

        $location = $this->_response->getHeaderLine('Location');
        parse_str((string)parse_url($location, PHP_URL_QUERY), $params);

        $this->assertStringStartsWith($redirect . '?', $location);
        $this->assertSame($state, $params['state']);
        $this->assertSame('consent_required', $params['error']);
        $this->assertArrayHasKey('error_description', $params);
    }

    /**
     * @return void
     */
    public function testUnauthorizedClient(): void
    {
        $client = $this->makeClientWithGrants([], ['http://localhost']);

        $this->loginAs($this->createUser());
        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect_uris[0],
            'response_type' => 'code',
        ]));

        $this->assertResponseCode(400);
        $this->assertNull($this->getSession()->read('authRequest'));
        $this->assertNull($this->getSession()->read('authToken'));
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertSame('unauthorized_client', $json['error']);
        $this->assertSame(
            'The authenticated client is not authorized to use this authorization grant type.',
            $json['error_description'],
        );
    }

    /**
     * @return void
     */
    public function testIssueAccessTokenWithoutRefreshToken(): void
    {
        $client = $this->makeClientWithGrants(['authorization_code'], ['http://localhost']);
        $redirect = $client->redirect_uris[0];

        $this->loginAs($this->createUser());

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
        ]));
        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);

        $this->syncSession();
        $this->post('/oauth/authorize', ['auth_token' => $json['authToken']]);
        $this->assertResponseCode(302);
        parse_str((string)parse_url($this->_response->getHeaderLine('Location'), PHP_URL_QUERY), $params);

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'redirect_uri' => $redirect,
            'code' => $params['code'],
        ]);
        $this->assertResponseOk();

        $tokenJson = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($tokenJson);
        $this->assertArrayHasKey('access_token', $tokenJson);
        $this->assertArrayNotHasKey('refresh_token', $tokenJson);
        $this->assertSame('Bearer', $tokenJson['token_type']);
        $this->assertArrayHasKey('expires_in', $tokenJson);
    }

    /**
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    protected function makeAuthCodeClient(): Client
    {
        return (new ClientRepository())->createAuthorizationCodeGrantClient(
            'Auth Code Client',
            ['http://localhost'],
        );
    }

    /**
     * @param list<string> $grantTypes Grant types
     * @param list<string> $redirectUris Redirect URIs
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    protected function makeClientWithGrants(array $grantTypes, array $redirectUris): Client
    {
        $table = Tessera::clientsTable();

        return $table->saveOrFail($table->newEntity([
            'name' => 'Custom Grant Client',
            'secret' => Security::randomString(40),
            'provider' => null,
            'revoked' => false,
            'redirect_uris' => $redirectUris,
            'grant_types' => $grantTypes,
        ]));
    }

    /**
     * Complete consent and exchange the code for a token (user already logged in).
     *
     * @param \Crustum\Tessera\Model\Entity\Client $client Client
     * @param string $scope Space-delimited scopes
     * @return void
     */
    protected function issueTokenForScopes(Client $client, string $scope): void
    {
        $redirect = $client->redirect_uris[0];

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => Text::uuid(),
        ]));
        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);

        $this->syncSession();
        $this->post('/oauth/authorize', ['auth_token' => $json['authToken']]);
        $this->assertResponseCode(302);
        parse_str((string)parse_url($this->_response->getHeaderLine('Location'), PHP_URL_QUERY), $params);

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'redirect_uri' => $redirect,
            'code' => $params['code'],
        ]);
        $this->assertResponseOk();
    }
}
