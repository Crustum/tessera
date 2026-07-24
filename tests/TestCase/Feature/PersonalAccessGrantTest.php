<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Cake\Core\Configure;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\PersonalAccessTokenFactory;
use Crustum\Tessera\PersonalAccessTokenResult;
use Crustum\Tessera\Tessera;
use TestApp\Application;
use TestApp\Model\Entity\User;

/**
 * Personal access grant Feature coverage.
 */
class PersonalAccessGrantTest extends TesseraTestCase
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
            'admins' => ['driver' => 'orm', 'model' => AdminHasApiTokensStub::class],
            'customers' => ['driver' => 'orm', 'model' => CustomerHasApiTokensStub::class],
        ]);
        Configure::write('Tessera.guards', [
            'api' => ['driver' => 'tessera', 'provider' => 'users'],
            'api-admins' => ['driver' => 'tessera', 'provider' => 'admins'],
            'api-customers' => ['driver' => 'tessera', 'provider' => 'customers'],
        ]);

        $app = $this->createApp();
        $this->assertInstanceOf(Application::class, $app);
        $app->bootstrap();
        Tessera::$personalAccessTokenFactory = $app->getContainer()->get(PersonalAccessTokenFactory::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Tessera::$personalAccessTokenFactory = null;
        Configure::delete('Tessera.provider');
        Configure::delete('Tessera.providers');
        Configure::delete('Tessera.guards');

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testIssueToken(): void
    {
        $user = $this->createUser();
        (new ClientRepository())->createPersonalAccessGrantClient('PAT Client');
        $client = (new ClientRepository())->personalAccessClient('users');

        Tessera::tokensCan([
            'foo' => 'Do foo',
            'bar' => 'Do bar',
        ]);

        $result = $user->createToken('test', ['bar']);
        $token = $result->getToken();

        $this->assertInstanceOf(PersonalAccessTokenResult::class, $result);
        $this->assertArrayHasKey('accessToken', $result->toArray());
        $this->assertSame($token?->id, $result->accessTokenId);
        $this->assertSame('Bearer', $result->tokenType);
        $this->assertEqualsWithDelta(31536000, $result->expiresIn, 2);
        $this->assertNotNull($token);
        $this->assertSame($client->id, $token->client_id);
        $this->assertEquals($user->id, $token->user_id);
        $this->assertSame(['bar'], $token->scopes);
        $this->assertSame('test', $token->name);

        $found = Tessera::tokensTable()->find()
            ->where([
                'id' => $token->id,
                'user_id' => $token->user_id,
                'client_id' => $token->client_id,
                'name' => $token->name,
            ])
            ->count();
        $this->assertSame(1, $found);
    }

    /**
     * @return void
     */
    public function testIssueTokenWithAllScopes(): void
    {
        $user = $this->createUser();
        (new ClientRepository())->createPersonalAccessGrantClient('PAT Client');
        $client = (new ClientRepository())->personalAccessClient('users');

        $result = $user->createToken('test', ['*']);
        $token = $result->getToken();

        $this->assertInstanceOf(PersonalAccessTokenResult::class, $result);
        $this->assertNotNull($token);
        $this->assertSame($client->id, $token->client_id);
        $this->assertEquals($user->id, $token->user_id);
        $this->assertSame(['*'], $token->scopes);
        $this->assertSame('test', $token->name);

        $this->clearSession();
        $this->configRequest([
            'headers' => ['Authorization' => 'Bearer ' . $result->accessToken],
        ]);
        $this->get('/token-info');
        $this->assertResponseOk();
        $info = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($info);
        $this->assertSame($token->id, $info['oauth_access_token_id']);
        $this->assertSame($client->id, $info['oauth_client_id']);
        $this->assertEquals($user->id, $info['oauth_user_id']);
        $this->assertSame(['*'], $info['oauth_scopes']);
    }

    /**
     * @return void
     */
    public function testIssueTokenWithDifferentProviders(): void
    {
        $client = (new ClientRepository())->createPersonalAccessGrantClient('Users PAT');
        $adminClient = (new ClientRepository())->createPersonalAccessGrantClient('Admins PAT', 'admins');
        $customerClient = (new ClientRepository())->createPersonalAccessGrantClient('Customers PAT', 'customers');

        $user = $this->createUser();
        $userToken = $user->createToken('test user');
        $userTokenRecord = $userToken->getToken();

        $admin = new AdminHasApiTokensStub();
        $adminToken = $admin->createToken('test admin');
        $adminTokenRecord = $adminToken->getToken();

        $customer = new CustomerHasApiTokensStub();
        $customerToken = $customer->createToken('test customer');
        $customerTokenRecord = $customerToken->getToken();

        $this->assertInstanceOf(PersonalAccessTokenResult::class, $userToken);
        $this->assertNotNull($userTokenRecord);
        $this->assertSame($client->id, $userTokenRecord->client_id);
        $this->assertEquals($user->id, $userTokenRecord->user_id);
        $this->assertSame('test user', $userTokenRecord->name);

        $this->assertInstanceOf(PersonalAccessTokenResult::class, $adminToken);
        $this->assertNotNull($adminTokenRecord);
        $this->assertSame($adminClient->id, $adminTokenRecord->client_id);
        $this->assertEquals(1, $adminTokenRecord->user_id);
        $this->assertSame('test admin', $adminTokenRecord->name);

        $this->assertInstanceOf(PersonalAccessTokenResult::class, $customerToken);
        $this->assertNotNull($customerTokenRecord);
        $this->assertSame($customerClient->id, $customerTokenRecord->client_id);
        $this->assertEquals(3, $customerTokenRecord->user_id);
        $this->assertSame('test customer', $customerTokenRecord->name);

        $userTokens = $user->tokens()->all()->extract('id')->toList();
        $adminTokens = $admin->tokens()->all()->extract('id')->toList();
        $customerTokens = $customer->tokens()->all()->extract('id')->toList();

        $this->assertEquals([$userToken->accessTokenId], $userTokens);
        $this->assertEquals([$adminToken->accessTokenId], $adminTokens);
        $this->assertEquals([$customerToken->accessTokenId], $customerTokens);
    }

    /**
     * @return void
     */
    public function testPersonalAccessTokenRequestIsDisabled(): void
    {
        $user = $this->createUser();
        (new ClientRepository())->createPersonalAccessGrantClient('PAT Client');

        $this->post('/oauth/token', [
            'grant_type' => 'personal_access',
            'provider' => $user->getProviderName(),
            'user_id' => $user->id,
            'scope' => '',
        ]);
        $this->assertResponseCode(400);
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertSame('unsupported_grant_type', $json['error']);
        $this->assertArrayHasKey('error_description', $json);
        $this->assertArrayNotHasKey('access_token', $json);

        $this->assertInstanceOf(PersonalAccessTokenResult::class, $user->createToken('test'));
    }
}
