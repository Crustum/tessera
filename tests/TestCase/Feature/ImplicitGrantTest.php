<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;
use Cake\Utility\Text;

/**
 * Implicit grant Feature coverage.
 */
class ImplicitGrantTest extends TesseraTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Tessera::enableImplicitGrant();

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
        Tessera::$implicitGrantEnabled = false;
        Tessera::$authorizationView = null;
        Tessera::tokensCan([]);

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testIssueAccessToken(): void
    {
        $client = $this->makeImplicitClient();
        $redirect = $client->redirect_uris[0];
        $state = Text::uuid();

        $user = $this->createUser();
        $this->loginAs($user);

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'token',
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
        parse_str((string)parse_url($location, PHP_URL_FRAGMENT), $params);

        $this->assertStringStartsWith($redirect . '#', $location);
        $this->assertSame($state, $params['state']);
        $this->assertArrayHasKey('access_token', $params);
        $this->assertArrayNotHasKey('refresh_token', $params);
        $this->assertSame('Bearer', $params['token_type']);
        $this->assertEqualsWithDelta(31536000, $params['expires_in'], 2);

        $this->clearSession();
        $this->configRequest([
            'headers' => [
                'Authorization' => $params['token_type'] . ' ' . $params['access_token'],
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
        $client = $this->makeImplicitClient();
        $redirect = $client->redirect_uris[0];
        $state = Text::uuid();

        $this->loginAs($this->createUser());

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'token',
            'scope' => 'create read',
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
        parse_str((string)parse_url($location, PHP_URL_FRAGMENT), $params);

        $this->assertStringStartsWith($redirect . '#', $location);
        $this->assertSame($state, $params['state']);
        $this->assertSame('access_denied', $params['error']);
        $this->assertArrayHasKey('error_description', $params);
    }

    /**
     * @return void
     */
    public function testSkipsAuthorizationWhenHasGrantedScopes(): void
    {
        $client = $this->makeImplicitClient();
        $redirect = $client->redirect_uris[0];

        $this->loginAs($this->createUser());
        $this->completeImplicitConsent($client, 'create read');

        $state = Text::uuid();
        $this->syncSession();
        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'token',
            'scope' => 'create',
            'state' => $state,
        ]));
        $this->assertResponseCode(302);

        $location = $this->_response->getHeaderLine('Location');
        parse_str((string)parse_url($location, PHP_URL_FRAGMENT), $params);

        $this->assertStringStartsWith($redirect . '#', $location);
        $this->assertSame($state, $params['state']);
        $this->assertArrayHasKey('access_token', $params);
        $this->assertArrayNotHasKey('refresh_token', $params);
        $this->assertSame('Bearer', $params['token_type']);
        $this->assertEqualsWithDelta(31536000, $params['expires_in'], 2);
    }

    /**
     * @return void
     */
    public function testValidateAuthorizationRequest(): void
    {
        $client = $this->makeImplicitClient();

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'https://evil.example/callback',
            'response_type' => 'token',
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
        $client = $this->makeImplicitClient();
        $redirect = $client->redirect_uris[0];
        $state = Text::uuid();

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'token',
            'scope' => 'foo',
            'state' => $state,
        ]));
        $this->assertResponseCode(302);

        $location = $this->_response->getHeaderLine('Location');
        parse_str((string)parse_url($location, PHP_URL_FRAGMENT), $params);

        $this->assertStringStartsWith($redirect . '#', $location);
        $this->assertSame($state, $params['state']);
        $this->assertSame('invalid_scope', $params['error']);
        $this->assertArrayHasKey('error_description', $params);
    }

    /**
     * @return void
     */
    public function testRedirectGuestUser(): void
    {
        $client = $this->makeImplicitClient();

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect_uris[0],
            'response_type' => 'token',
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
        $client = $this->makeImplicitClient();
        $redirect = $client->redirect_uris[0];
        $state = Text::uuid();

        $this->loginAs($this->createUser());
        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'token',
            'state' => $state,
            'prompt' => 'none',
        ]));
        $this->assertResponseCode(302);

        $location = $this->_response->getHeaderLine('Location');
        parse_str((string)parse_url($location, PHP_URL_FRAGMENT), $params);

        $this->assertStringStartsWith($redirect . '#', $location);
        $this->assertSame($state, $params['state']);
        $this->assertSame('consent_required', $params['error']);
        $this->assertArrayHasKey('error_description', $params);
    }

    /**
     * @return void
     */
    public function testPromptNoneLoginRequired(): void
    {
        $client = $this->makeImplicitClient();
        $redirect = $client->redirect_uris[0];
        $state = Text::uuid();

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'token',
            'state' => $state,
            'prompt' => 'none',
        ]));
        $this->assertResponseCode(302);

        $location = $this->_response->getHeaderLine('Location');
        parse_str((string)parse_url($location, PHP_URL_FRAGMENT), $params);

        $this->assertStringStartsWith($redirect . '#', $location);
        $this->assertSame($state, $params['state']);
        $this->assertSame('login_required', $params['error']);
        $this->assertArrayHasKey('error_description', $params);
    }

    /**
     * @return void
     */
    public function testPromptConsent(): void
    {
        $client = $this->makeImplicitClient();
        $redirect = $client->redirect_uris[0];

        $this->loginAs($this->createUser());
        $this->completeImplicitConsent($client, 'create read update');

        $this->syncSession();
        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'token',
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
        $client = $this->makeImplicitClient();

        $this->loginAs($this->createUser());
        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect_uris[0],
            'response_type' => 'token',
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
    public function testUnauthorizedClient(): void
    {
        $client = (new ClientRepository())->createAuthorizationCodeGrantClient(
            'Auth Code Client',
            ['http://localhost'],
        );

        $this->loginAs($this->createUser());
        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $client->redirect_uris[0],
            'response_type' => 'token',
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
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    protected function makeImplicitClient(): Client
    {
        return (new ClientRepository())->createImplicitGrantClient(
            'Implicit Client',
            ['http://localhost'],
        );
    }

    /**
     * Complete an implicit consent flow and return the redirect fragment parameters.
     *
     * @param \Crustum\Tessera\Model\Entity\Client $client Client
     * @param string $scope Space-delimited scopes
     * @return array<string, mixed>
     */
    protected function completeImplicitConsent(Client $client, string $scope): array
    {
        $redirect = $client->redirect_uris[0];

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'token',
            'scope' => $scope,
            'state' => Text::uuid(),
        ]));
        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);

        $this->syncSession();
        $this->post('/oauth/authorize', ['auth_token' => $json['authToken']]);
        $this->assertResponseCode(302);

        parse_str((string)parse_url($this->_response->getHeaderLine('Location'), PHP_URL_FRAGMENT), $params);

        return $params;
    }
}
