<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;

/**
 * Refresh token grant Feature coverage.
 */
class RefreshTokenGrantTest extends TesseraTestCase
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

        Tessera::$revokeRefreshTokenAfterUse = true;

        Tessera::authorizationView(static fn(array $params): array => $params);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Tessera::$authorizationView = null;
        Tessera::tokensCan([]);
        Tessera::$revokeRefreshTokenAfterUse = true;

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testRefreshingToken(): void
    {
        $client = $this->makeAuthCodeClient();

        $oldToken = $this->getNewAccessToken($client);

        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'refresh_token' => $oldToken['refresh_token'],
            'scope' => 'read delete',
        ]);
        $this->assertResponseOk();

        $newToken = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($newToken);
        $this->assertArrayHasKey('access_token', $newToken);
        $this->assertArrayHasKey('refresh_token', $newToken);
        $this->assertEqualsWithDelta(31536000, $newToken['expires_in'], 2);
        $this->assertSame('Bearer', $newToken['token_type']);

        $this->clearSession();
        $this->configRequest([
            'headers' => [
                'Authorization' => $oldToken['token_type'] . ' ' . $oldToken['access_token'],
            ],
        ]);
        $this->assertRequestUnauthorized(fn() => $this->get('/token-info'));

        $this->clearSession();
        $this->configRequest([
            'headers' => [
                'Authorization' => $newToken['token_type'] . ' ' . $newToken['access_token'],
            ],
        ]);
        $this->get('/token-info');
        $this->assertResponseOk();

        $info = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($info);
        $this->assertSame(['read', 'delete'], $info['oauth_scopes']);

        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'refresh_token' => $oldToken['refresh_token'],
        ]);
        $this->assertResponseCode(400);

        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertSame('invalid_grant', $json['error']);
        $this->assertSame('The refresh token is invalid.', $json['error_description']);
        $this->assertSame('Token has been revoked', $json['hint']);
    }

    /**
     * @return void
     */
    public function testRefreshingTokenWithoutRevoking(): void
    {
        Tessera::$revokeRefreshTokenAfterUse = false;

        $client = $this->makeAuthCodeClient();

        $oldToken = $this->getNewAccessToken($client);

        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'refresh_token' => $oldToken['refresh_token'],
            'scope' => 'read delete',
        ]);
        $this->assertResponseOk();

        $newToken = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($newToken);
        $this->assertArrayHasKey('access_token', $newToken);
        $this->assertArrayHasKey('refresh_token', $newToken);
        $this->assertEqualsWithDelta(31536000, $newToken['expires_in'], 2);
        $this->assertSame('Bearer', $newToken['token_type']);

        $this->clearSession();
        $this->configRequest([
            'headers' => [
                'Authorization' => $oldToken['token_type'] . ' ' . $oldToken['access_token'],
            ],
        ]);
        $this->assertRequestUnauthorized(fn() => $this->get('/token-info'));

        $this->clearSession();
        $this->configRequest([
            'headers' => [
                'Authorization' => $newToken['token_type'] . ' ' . $newToken['access_token'],
            ],
        ]);
        $this->get('/token-info');
        $this->assertResponseOk();

        $info = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($info);
        $this->assertSame(['read', 'delete'], $info['oauth_scopes']);

        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'refresh_token' => $oldToken['refresh_token'],
        ]);
        $this->assertResponseOk();

        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('access_token', $json);
        $this->assertArrayHasKey('refresh_token', $json);
        $this->assertEqualsWithDelta(31536000, $json['expires_in'], 2);
        $this->assertSame('Bearer', $json['token_type']);
    }

    /**
     * @return void
     */
    public function testRefreshingTokenWithAdditionalScopes(): void
    {
        $client = $this->makeAuthCodeClient();

        $oldToken = $this->getNewAccessToken($client);

        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'refresh_token' => $oldToken['refresh_token'],
            'scope' => 'create update',
        ]);
        $this->assertResponseCode(400);

        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertSame('invalid_scope', $json['error']);
        $this->assertSame('The requested scope is invalid, unknown, or malformed', $json['error_description']);
        $this->assertSame('Check the `update` scope', $json['hint']);
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
     * Complete consent and exchange the code for a token (creates and logs in a user).
     *
     * @param \Crustum\Tessera\Model\Entity\Client $client Client
     * @return array<string, mixed>
     */
    protected function getNewAccessToken(Client $client): array
    {
        $redirect = $client->redirect_uris[0];

        $this->loginAs($this->createUser());

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'create read delete',
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

        $token = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($token);

        return $token;
    }
}
