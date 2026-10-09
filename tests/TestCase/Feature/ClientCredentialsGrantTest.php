<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Tessera;
use DateInterval;

/**
 * Client credentials grant Feature coverage.
 */
class ClientCredentialsGrantTest extends TesseraTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        Tessera::$clientCredentialsTokensExpireIn = null;

        parent::setUp();

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
        Tessera::$clientCredentialsTokensExpireIn = null;

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testIssueAccessToken(): void
    {
        $client = (new ClientRepository())->createClientCredentialsGrantClient('Test Client');

        $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'scope' => 'create read delete',
        ]);

        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('access_token', $json);
        $this->assertArrayNotHasKey('refresh_token', $json);
        $this->assertSame('Bearer', $json['token_type']);
        $this->assertEqualsWithDelta(31536000, $json['expires_in'], 2);

        $this->configRequest([
            'headers' => ['Authorization' => 'Bearer ' . $json['access_token']],
        ]);
        $this->get('/foo');
        $this->assertResponseOk();
        $this->assertResponseEquals('response');

        $this->configRequest([
            'headers' => ['Authorization' => 'Bearer ' . $json['access_token']],
        ]);
        $this->assertRequestForbidden(fn() => $this->get('/bar-missing-scope'));
    }

    /**
     * @return void
     */
    public function testIssueAccessTokenWithAllScopes(): void
    {
        $client = (new ClientRepository())->createClientCredentialsGrantClient('Test Client');

        $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'scope' => '*',
        ]);

        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('access_token', $json);
        $this->assertArrayNotHasKey('refresh_token', $json);
        $this->assertSame('Bearer', $json['token_type']);
        $this->assertEqualsWithDelta(31536000, $json['expires_in'], 2);

        $this->configRequest([
            'headers' => ['Authorization' => 'Bearer ' . $json['access_token']],
        ]);
        $this->get('/foo');
        $this->assertResponseOk();

        $this->configRequest([
            'headers' => ['Authorization' => 'Bearer ' . $json['access_token']],
        ]);
        $this->get('/bar-ok-scopes');
        $this->assertResponseOk();
    }

    /**
     * @return void
     */
    public function testGettingAccessTokenWithMalformedUuidClientId(): void
    {
        $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => 'x',
            'client_secret' => 'secret',
        ]);

        $this->assertResponseCode(401);
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertSame('invalid_client', $json['error']);
        $this->assertSame('Client authentication failed', $json['error_description']);

        $this->assertSame(0, Tessera::tokensTable()->find()->count());
    }

    /**
     * @return void
     */
    public function testPublicClient(): void
    {
        $table = Tessera::clientsTable();
        $client = $table->saveOrFail($table->newEntity([
            'name' => 'Public Client',
            'secret' => null,
            'provider' => null,
            'revoked' => false,
            'redirect_uris' => [],
            'grant_types' => ['client_credentials'],
        ]));

        $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
        ]);

        $this->assertResponseCode(401);
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertSame('invalid_client', $json['error']);
        $this->assertSame('Client authentication failed', $json['error_description']);
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

        $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
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

    /**
     * @return void
     */
    public function testCustomClientCredentialsTokenExpiration(): void
    {
        Tessera::clientCredentialsTokensExpireIn(new DateInterval('P7D'));

        $client = (new ClientRepository())->createClientCredentialsGrantClient('Test Client');

        $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
        ]);

        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertEqualsWithDelta(604800, $json['expires_in'], 2);
    }
}
