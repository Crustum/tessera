<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\PersonalAccessTokenFactory;
use Crustum\Tessera\Tessera;
use TestApp\Application;
use TestApp\Model\Entity\User;

/**
 * TokenAuthenticator HTTP coverage with real bearer tokens.
 */
class TokenGuardTest extends TesseraTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Configure::write('Tessera.provider', 'users');
        Configure::write('Tessera.providers', [
            'users' => ['driver' => 'orm', 'model' => User::class],
        ]);
        Configure::write('Tessera.guards', [
            'api' => ['driver' => 'tessera', 'provider' => 'users'],
        ]);

        $app = $this->createApp();
        $this->assertInstanceOf(Application::class, $app);
        $app->bootstrap();
        Tessera::$personalAccessTokenFactory = $app->getContainer()->get(PersonalAccessTokenFactory::class);

        Tessera::tokensCan([
            'create' => 'Create',
            'delete' => 'Delete',
        ]);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Tessera::$personalAccessTokenFactory = null;
        Tessera::tokensCan([]);
        Configure::delete('Tessera.provider');
        Configure::delete('Tessera.providers');
        Configure::delete('Tessera.guards');

        parent::tearDown();
    }

    /**
     * @param string $token Bearer token
     * @return void
     */
    protected function withBearerToken(string $token): void
    {
        $this->clearSession();
        $this->configRequest([
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);
    }

    /**
     * @return void
     */
    public function testUserCanBePulledViaBearerToken(): void
    {
        $user = $this->createUser();
        (new ClientRepository())->createPersonalAccessGrantClient('PAT Client');
        $client = (new ClientRepository())->personalAccessClient('users');
        $result = $user->createToken('test');

        $this->withBearerToken($result->accessToken);
        $this->get('/guard');
        $this->assertResponseOk();

        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertEquals($user->id, $json['user']);
        $this->assertSame($result->accessTokenId, $json['token']);
        $this->assertSame($client->id, $json['client']);
    }

    /**
     * @return void
     */
    public function testUserAndClientAreResolvedOnRepeatedRequests(): void
    {
        $user = $this->createUser();
        (new ClientRepository())->createPersonalAccessGrantClient('PAT Client');
        $result = $user->createToken('test');

        $this->withBearerToken($result->accessToken);
        $this->get('/guard');
        $this->assertResponseOk();
        $first = (string)$this->_response->getBody();

        $this->withBearerToken($result->accessToken);
        $this->get('/guard');
        $this->assertResponseOk();
        $this->assertSame($first, (string)$this->_response->getBody());
    }

    /**
     * @return void
     */
    public function testNothingIsReturnedWhenOauthThrowsException(): void
    {
        $this->withBearerToken('invalid');
        $this->assertRequestUnauthorized(fn() => $this->get('/guard'));
    }

    /**
     * @return void
     */
    public function testNullIsReturnedIfNoUserIsFound(): void
    {
        $user = $this->createUser();
        (new ClientRepository())->createPersonalAccessGrantClient('PAT Client');
        $result = $user->createToken('test');

        $users = TableRegistry::getTableLocator()->get('Users');
        $users->deleteOrFail($users->get($user->id));

        $this->withBearerToken($result->accessToken);
        $this->assertRequestUnauthorized(fn() => $this->get('/guard'));
    }

    /**
     * @return void
     */
    public function testNullIsReturnedForClientCredentialsToken(): void
    {
        $client = (new ClientRepository())->createClientCredentialsGrantClient('Test Client');

        $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'scope' => 'create delete',
        ]);
        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);

        $this->withBearerToken((string)$json['access_token']);
        $this->assertRequestUnauthorized(fn() => $this->get('/guard'));

        $this->withBearerToken((string)$json['access_token']);
        $this->get('/foo');
        $this->assertResponseOk();
    }

    /**
     * @return void
     */
    public function testNullIsReturnedIfNoClientIsFound(): void
    {
        $user = $this->createUser();
        $clients = new ClientRepository();
        $clients->createPersonalAccessGrantClient('PAT Client');

        $client = $clients->personalAccessClient('users');
        $result = $user->createToken('test');

        $client->set('revoked', true);
        Tessera::clientsTable()->saveOrFail($client);

        $this->withBearerToken($result->accessToken);
        $this->assertRequestUnauthorized(fn() => $this->get('/guard'));
    }

    /**
     * @return void
     */
    public function testUnauthorizedForRevokedToken(): void
    {
        $user = $this->createUser();
        (new ClientRepository())->createPersonalAccessGrantClient('PAT Client');
        $result = $user->createToken('test');

        $token = $result->getToken();
        $this->assertNotNull($token);
        Tessera::tokensTable()->revoke($token);

        $this->withBearerToken($result->accessToken);
        $this->assertRequestUnauthorized(fn() => $this->get('/guard'));
    }
}
