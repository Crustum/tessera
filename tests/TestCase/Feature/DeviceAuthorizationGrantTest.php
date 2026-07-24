<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Tessera;

/**
 * Device authorization grant Feature coverage.
 */
class DeviceAuthorizationGrantTest extends TesseraTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Tessera::$deviceCodeGrantEnabled = true;
        Tessera::tokensCan([
            'create' => 'Create',
            'read' => 'Read',
            'update' => 'Update',
            'delete' => 'Delete',
        ]);
        Tessera::deviceAuthorizationView(static fn(array $params): array => $params);
        Tessera::deviceUserCodeView(static fn(array $params): array => $params);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Tessera::$deviceAuthorizationView = null;
        Tessera::$deviceUserCodeView = null;
        Tessera::tokensCan([]);

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testIssueDeviceCode(): void
    {
        $client = (new ClientRepository())->createDeviceAuthorizationGrantClient('Device Client');

        $this->post('/oauth/device/code', [
            'client_id' => $client->id,
            'scope' => 'create read',
        ]);
        $this->assertResponseOk();

        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('device_code', $json);
        $this->assertArrayHasKey('user_code', $json);
        $this->assertSame(5, $json['interval']);
        $this->assertSame(600, $json['expires_in']);
        $this->assertSame('http://localhost/oauth/device', $json['verification_uri']);
        $this->assertSame(
            'http://localhost/oauth/device?user_code=' . $json['user_code'],
            $json['verification_uri_complete'],
        );
    }

    /**
     * @return void
     */
    public function testRequestAccessTokenAuthorizationPending(): void
    {
        $client = (new ClientRepository())->createDeviceAuthorizationGrantClient('Device Client');

        $this->post('/oauth/device/code', [
            'client_id' => $client->id,
            'scope' => 'create read',
        ]);
        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertSame(5, $json['interval']);
        $deviceCode = $json['device_code'];

        $this->post('/oauth/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'device_code' => $deviceCode,
        ]);
        $this->assertResponseCode(400);
        $pending = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($pending);
        $this->assertSame('authorization_pending', $pending['error']);
        $this->assertArrayHasKey('error_description', $pending);

        $this->post('/oauth/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'device_code' => $deviceCode,
        ]);
        $this->assertResponseCode(400);
        $slow = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($slow);
        $this->assertSame('slow_down', $slow['error']);
        $this->assertArrayHasKey('error_description', $slow);
    }

    /**
     * @return void
     */
    public function testAuthorizationWithoutUserCodeRedirects(): void
    {
        $this->loginAs($this->createUser());
        $this->get('/oauth/device/authorize');
        $this->assertResponseCode(302);
        $this->assertStringContainsString('/oauth/device', $this->_response->getHeaderLine('Location'));
    }

    /**
     * @return void
     */
    public function testVerificationUrl(): void
    {
        $client = (new ClientRepository())->createDeviceAuthorizationGrantClient('Device Client');

        $this->post('/oauth/device/code', [
            'client_id' => $client->id,
            'scope' => 'create read',
        ]);
        $this->assertResponseOk();
        $issued = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($issued);

        $this->get($issued['verification_uri']);
        $this->assertResponseOk();
        $entry = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($entry);
        $this->assertEqualsCanonicalizing(['request'], array_keys($entry));

        $this->loginAs($this->createUser());
        $this->get($issued['verification_uri_complete']);
        $this->assertResponseCode(302);
        $this->assertStringContainsString(
            '/oauth/device/authorize?user_code=' . $issued['user_code'],
            $this->_response->getHeaderLine('Location'),
        );

        $formatted = substr_replace((string)$issued['user_code'], '-', 4, 0);
        $this->syncSession();
        $this->get('/oauth/device/authorize?user_code=' . $formatted);
        $this->assertResponseOk();
        $this->assertNotNull($this->getSession()->read('deviceCode'));
        $this->assertNotNull($this->getSession()->read('authToken'));
    }

    /**
     * @return void
     */
    public function testAuthorizationWithInvalidUserCode(): void
    {
        $this->loginAs($this->createUser());

        $this->get('/oauth/device/authorize?user_code=12345678');
        $this->assertResponseCode(302);
        $location = $this->_response->getHeaderLine('Location');
        $this->assertStringContainsString('/oauth/device', $location);
        parse_str((string)parse_url($location, PHP_URL_QUERY), $params);
        $this->assertSame('12345678', $params['user_code'] ?? null);
        $this->assertSame('invalid_code', $params['error'] ?? null);

        $this->get('/oauth/device/authorize?user_code=ABCD-EFGH');
        $this->assertResponseCode(302);
        $location = $this->_response->getHeaderLine('Location');
        parse_str((string)parse_url($location, PHP_URL_QUERY), $params);
        $this->assertSame('ABCD-EFGH', $params['user_code'] ?? null);
        $this->assertSame('invalid_code', $params['error'] ?? null);
    }

    /**
     * @return void
     */
    public function testIssueAccessToken(): void
    {
        $client = (new ClientRepository())->createDeviceAuthorizationGrantClient('Device Client');

        $this->post('/oauth/device/code', [
            'client_id' => $client->id,
            'scope' => 'create read',
        ]);
        $this->assertResponseOk();
        $issued = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($issued);

        $user = $this->createUser();
        $this->loginAs($user);
        $this->get('/oauth/device/authorize?user_code=' . $issued['user_code']);
        $this->assertResponseOk();
        $this->assertNotNull($this->getSession()->read('deviceCode'));
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
        $this->post('/oauth/device/authorize', ['auth_token' => $json['authToken']]);
        $this->assertResponseCode(302);
        $location = $this->_response->getHeaderLine('Location');
        $this->assertStringContainsString('/oauth/device', $location);
        $this->assertStringContainsString('status=authorization-approved', $location);
        $this->assertNull($this->getSession()->read('deviceCode'));
        $this->assertNull($this->getSession()->read('authToken'));

        $this->syncSession();
        $this->get('/oauth/device/authorize?user_code=' . $issued['user_code']);
        $this->assertResponseCode(302);
        parse_str((string)parse_url($this->_response->getHeaderLine('Location'), PHP_URL_QUERY), $params);
        $this->assertSame($issued['user_code'], $params['user_code'] ?? null);
        $this->assertSame('invalid_code', $params['error'] ?? null);

        $this->post('/oauth/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'device_code' => $issued['device_code'],
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
        $client = (new ClientRepository())->createDeviceAuthorizationGrantClient('Device Client');

        $this->post('/oauth/device/code', [
            'client_id' => $client->id,
            'scope' => 'create read',
        ]);
        $this->assertResponseOk();
        $issued = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($issued);

        $this->loginAs($this->createUser());
        $this->get('/oauth/device/authorize?user_code=' . $issued['user_code']);
        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);

        $this->syncSession();
        $this->_sendRequest('/oauth/device/authorize', 'DELETE', ['auth_token' => $json['authToken']]);
        $this->assertResponseCode(302);
        $location = $this->_response->getHeaderLine('Location');
        $this->assertStringContainsString('/oauth/device', $location);
        $this->assertStringContainsString('status=authorization-denied', $location);
        $this->assertNull($this->getSession()->read('deviceCode'));
        $this->assertNull($this->getSession()->read('authToken'));

        $this->syncSession();
        $this->get('/oauth/device/authorize?user_code=' . $issued['user_code']);
        $this->assertResponseCode(302);
        parse_str((string)parse_url($this->_response->getHeaderLine('Location'), PHP_URL_QUERY), $params);
        $this->assertSame('invalid_code', $params['error'] ?? null);

        $this->post('/oauth/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'device_code' => $issued['device_code'],
        ]);
        $this->assertResponseCode(401);
        $denied = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($denied);
        $this->assertSame('access_denied', $denied['error']);
        $this->assertArrayHasKey('error_description', $denied);
    }

    /**
     * @return void
     */
    public function testDeviceCodeSerialization(): void
    {
        $client = (new ClientRepository())->createDeviceAuthorizationGrantClient('Device Client');

        $this->post('/oauth/device/code', [
            'client_id' => $client->id,
            'scope' => 'create read',
        ]);
        $this->assertResponseOk();
        $issued = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($issued);

        $this->loginAs($this->createUser());
        $this->get('/oauth/device/authorize?user_code=' . $issued['user_code']);
        $this->assertResponseOk();
        $this->assertNotNull($this->getSession()->read('deviceCode'));
        $this->assertNotNull($this->getSession()->read('authToken'));
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertEqualsCanonicalizing(
            ['client', 'user', 'scopes', 'request', 'authToken'],
            array_keys($json),
        );

        $this->syncSession();
        $this->post('/oauth/device/authorize', ['auth_token' => $json['authToken']]);
        $this->assertResponseCode(302);
        $this->assertStringContainsString('status=authorization-approved', $this->_response->getHeaderLine('Location'));
        $this->assertNull($this->getSession()->read('deviceCode'));
        $this->assertNull($this->getSession()->read('authToken'));
    }

    /**
     * @return void
     */
    public function testPublicClient(): void
    {
        $client = (new ClientRepository())->createDeviceAuthorizationGrantClient('Public Device', false);

        $this->post('/oauth/device/code', [
            'client_id' => $client->id,
            'scope' => 'create read',
        ]);
        $this->assertResponseOk();
        $issued = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($issued);

        $this->loginAs($this->createUser());
        $this->get('/oauth/device/authorize?user_code=' . $issued['user_code']);
        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);

        $this->syncSession();
        $this->post('/oauth/device/authorize', ['auth_token' => $json['authToken']]);
        $this->assertResponseCode(302);

        $this->post('/oauth/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            'client_id' => $client->id,
            'device_code' => $issued['device_code'],
        ]);
        $this->assertResponseOk();
        $tokenJson = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($tokenJson);
        $this->assertArrayHasKey('access_token', $tokenJson);
        $this->assertArrayHasKey('refresh_token', $tokenJson);
        $this->assertSame('Bearer', $tokenJson['token_type']);
        $this->assertEqualsWithDelta(31536000, $tokenJson['expires_in'], 2);
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

        $this->post('/oauth/device/code', [
            'client_id' => $client->id,
        ]);
        $this->assertResponseCode(400);
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertSame('unauthorized_client', $json['error']);
        $this->assertArrayHasKey('error_description', $json);
    }
}
