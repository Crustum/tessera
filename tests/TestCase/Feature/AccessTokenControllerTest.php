<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Tessera;

/**
 * Access token endpoint Feature coverage (headers + grant error paths).
 */
class AccessTokenControllerTest extends TesseraTestCase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Tessera::$passwordGrantEnabled = false;
        Tessera::$authorizationServerResponseType = null;

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testGettingAccessTokenWithClientCredentialsGrant(): void
    {
        $client = (new ClientRepository())->createClientCredentialsGrantClient('Test Client');

        $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
        ]);

        $this->assertResponseOk();
        $this->assertHeader('pragma', 'no-cache');
        $this->assertTrue(
            str_contains(strtolower($this->_response->getHeaderLine('cache-control')), 'no-store'),
        );
        $this->assertStringContainsString('application/json', $this->_response->getHeaderLine('content-type'));

        $decodedResponse = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($decodedResponse);
        $this->assertArrayHasKey('token_type', $decodedResponse);
        $this->assertArrayHasKey('expires_in', $decodedResponse);
        $this->assertArrayHasKey('access_token', $decodedResponse);
        $this->assertSame('Bearer', $decodedResponse['token_type']);
        $this->assertEqualsWithDelta(31536000, $decodedResponse['expires_in'], 2);
    }

    /**
     * @return void
     */
    public function testGettingAccessTokenWithClientCredentialsGrantInvalidClientSecret(): void
    {
        $client = (new ClientRepository())->createClientCredentialsGrantClient('Test Client');

        $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret . 'foo',
        ]);

        $this->assertResponseCode(401);
        $this->assertStringContainsString('application/json', $this->_response->getHeaderLine('content-type'));

        $decodedResponse = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($decodedResponse);
        $this->assertArrayNotHasKey('token_type', $decodedResponse);
        $this->assertArrayNotHasKey('expires_in', $decodedResponse);
        $this->assertArrayNotHasKey('access_token', $decodedResponse);
        $this->assertArrayHasKey('error', $decodedResponse);
        $this->assertSame('invalid_client', $decodedResponse['error']);
        $this->assertArrayHasKey('error_description', $decodedResponse);
        $this->assertSame('Client authentication failed', $decodedResponse['error_description']);
        $this->assertArrayNotHasKey('hint', $decodedResponse);
        $this->assertSame(0, Tessera::tokensTable()->find()->count());
    }

    /**
     * @return void
     */
    public function testGettingAccessTokenWithPasswordGrant(): void
    {
        Tessera::enablePasswordGrant();

        $client = (new ClientRepository())->createPasswordGrantClient('Test Client', null, true);
        $user = $this->createUser('foo@gmail.com', 'foobar123');

        $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'username' => $user->email,
            'password' => 'foobar123',
        ]);

        $this->assertResponseOk();
        $this->assertHeader('pragma', 'no-cache');
        $this->assertTrue(
            str_contains(strtolower($this->_response->getHeaderLine('cache-control')), 'no-store'),
        );
        $this->assertStringContainsString('application/json', $this->_response->getHeaderLine('content-type'));

        $decodedResponse = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($decodedResponse);
        $this->assertArrayHasKey('token_type', $decodedResponse);
        $this->assertArrayHasKey('expires_in', $decodedResponse);
        $this->assertArrayHasKey('access_token', $decodedResponse);
        $this->assertArrayHasKey('refresh_token', $decodedResponse);
        $this->assertSame('Bearer', $decodedResponse['token_type']);
        $this->assertEqualsWithDelta(31536000, $decodedResponse['expires_in'], 2);
    }

    /**
     * @return void
     */
    public function testGettingAccessTokenWithPasswordGrantWithInvalidPassword(): void
    {
        Tessera::enablePasswordGrant();

        $client = (new ClientRepository())->createPasswordGrantClient('Test Client', null, true);
        $user = $this->createUser('foo@gmail.com', 'foobar123');

        $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'username' => $user->email,
            'password' => 'foobar123foo',
        ]);

        $this->assertResponseCode(400);
        $this->assertStringContainsString('application/json', $this->_response->getHeaderLine('content-type'));

        $decodedResponse = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($decodedResponse);
        $this->assertArrayNotHasKey('token_type', $decodedResponse);
        $this->assertArrayNotHasKey('expires_in', $decodedResponse);
        $this->assertArrayNotHasKey('access_token', $decodedResponse);
        $this->assertArrayNotHasKey('refresh_token', $decodedResponse);
        $this->assertArrayNotHasKey('hint', $decodedResponse);
        $this->assertArrayHasKey('error', $decodedResponse);
        $this->assertSame('invalid_grant', $decodedResponse['error']);
        $this->assertArrayHasKey('error_description', $decodedResponse);
        $this->assertSame(0, Tessera::tokensTable()->find()->count());
    }

    /**
     * @return void
     */
    public function testGettingAccessTokenWithPasswordGrantWithInvalidClientSecret(): void
    {
        Tessera::enablePasswordGrant();

        $client = (new ClientRepository())->createPasswordGrantClient('Test Client', null, true);
        $user = $this->createUser('foo@gmail.com', 'foobar123');

        $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret . 'foo',
            'username' => $user->email,
            'password' => 'foobar123',
        ]);

        $this->assertResponseCode(401);
        $this->assertStringContainsString('application/json', $this->_response->getHeaderLine('content-type'));

        $decodedResponse = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($decodedResponse);
        $this->assertArrayNotHasKey('token_type', $decodedResponse);
        $this->assertArrayNotHasKey('expires_in', $decodedResponse);
        $this->assertArrayNotHasKey('access_token', $decodedResponse);
        $this->assertArrayNotHasKey('refresh_token', $decodedResponse);
        $this->assertArrayHasKey('error', $decodedResponse);
        $this->assertSame('invalid_client', $decodedResponse['error']);
        $this->assertArrayHasKey('error_description', $decodedResponse);
        $this->assertSame('Client authentication failed', $decodedResponse['error_description']);
        $this->assertArrayNotHasKey('hint', $decodedResponse);
        $this->assertSame(0, Tessera::tokensTable()->find()->count());
    }

    /**
     * @return void
     */
    public function testGettingCustomResponseType(): void
    {
        Tessera::$authorizationServerResponseType = new IdTokenResponse('foo_bar_open_id_token');

        $client = (new ClientRepository())->createClientCredentialsGrantClient('Test Client');

        $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
        ]);

        $this->assertResponseOk();
        $decodedResponse = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($decodedResponse);
        $this->assertArrayHasKey('id_token', $decodedResponse);
        $this->assertSame('foo_bar_open_id_token', $decodedResponse['id_token']);
    }
}
