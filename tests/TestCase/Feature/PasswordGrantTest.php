<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Tessera;

/**
 * Password grant Feature coverage.
 */
class PasswordGrantTest extends TesseraTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Tessera::enablePasswordGrant();

        Tessera::tokensCan([
            'create' => 'Create',
            'read' => 'Read',
            'update' => 'Update',
            'delete' => 'Delete',
        ]);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Tessera::$passwordGrantEnabled = false;
        Tessera::tokensCan([]);

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testIssueToken(): void
    {
        $client = (new ClientRepository())->createPasswordGrantClient('Test Client', null, true);
        $user = $this->createUser();

        $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'username' => $user->email,
            'password' => 'secret',
            'scope' => 'create delete',
        ]);

        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('access_token', $json);
        $this->assertArrayHasKey('refresh_token', $json);
        $this->assertSame('Bearer', $json['token_type']);
        $this->assertEqualsWithDelta(31536000, $json['expires_in'], 2);

        $this->clearSession();
        $this->configRequest([
            'headers' => [
                'Authorization' => $json['token_type'] . ' ' . $json['access_token'],
            ],
        ]);
        $this->get('/token-info');
        $this->assertResponseOk();

        $info = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($info);
        $this->assertSame($client->id, $info['oauth_client_id']);
        $this->assertEquals($user->id, $info['oauth_user_id']);
        $this->assertSame(['create', 'delete'], $info['oauth_scopes']);
    }

    /**
     * @return void
     */
    public function testIssueTokenWithAllScopes(): void
    {
        $client = (new ClientRepository())->createPasswordGrantClient('Test Client', null, true);
        $user = $this->createUser();

        $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'username' => $user->email,
            'password' => 'secret',
            'scope' => '*',
        ]);

        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('access_token', $json);
        $this->assertArrayHasKey('refresh_token', $json);
        $this->assertSame('Bearer', $json['token_type']);
        $this->assertEqualsWithDelta(31536000, $json['expires_in'], 2);

        $this->clearSession();
        $this->configRequest([
            'headers' => [
                'Authorization' => $json['token_type'] . ' ' . $json['access_token'],
            ],
        ]);
        $this->get('/token-info');
        $this->assertResponseOk();

        $info = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($info);
        $this->assertSame($client->id, $info['oauth_client_id']);
        $this->assertEquals($user->id, $info['oauth_user_id']);
        $this->assertSame(['*'], $info['oauth_scopes']);
    }

    /**
     * @return void
     */
    public function testPublicClient(): void
    {
        $client = (new ClientRepository())->createPasswordGrantClient('Public Client', null, false);
        $user = $this->createUser();

        $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'username' => $user->email,
            'password' => 'secret',
        ]);

        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('access_token', $json);
        $this->assertArrayHasKey('refresh_token', $json);
        $this->assertSame('Bearer', $json['token_type']);
        $this->assertEqualsWithDelta(31536000, $json['expires_in'], 2);
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
        $user = $this->createUser();

        $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'username' => $user->email,
            'password' => 'secret',
        ]);

        $this->assertResponseCode(400);
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertSame('unauthorized_client', $json['error']);
        $this->assertSame(
            'The authenticated client is not authorized to use this authorization grant type.',
            $json['error_description'],
        );
    }
}
