<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Bridge;

use Cake\TestSuite\TestCase;
use Crustum\Tessera\Bridge\Client;
use Crustum\Tessera\Bridge\Scope;
use Crustum\Tessera\Bridge\ScopeRepository;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Model\Entity\Client as ClientModel;
use Crustum\Tessera\Tessera;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * Bridge scope repository unit tests.
 */
class BridgeScopeRepositoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Tessera::$withInheritedScopes = false;
        Tessera::$scopes = [];

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testInvalidScopesAreRemoved(): void
    {
        Tessera::tokensCan([
            'scope-1' => 'description',
        ]);

        $client = new ClientModel();
        $clients = Mockery::mock(ClientRepository::class);
        $clients->shouldReceive('findActive')->withAnyArgs()->andReturn($client);

        $repository = new ScopeRepository($clients);

        $scopes = $repository->finalizeScopes(
            [$scope1 = new Scope('scope-1'), new Scope('scope-2')],
            'client_credentials',
            new Client('id', 'name', ['http://localhost']),
            '1',
        );

        $this->assertEquals([$scope1], $scopes);
    }

    /**
     * @return void
     */
    public function testClientsDoNotRestrictScopesByDefault(): void
    {
        Tessera::tokensCan([
            'scope-1' => 'description',
            'scope-2' => 'description',
        ]);

        $client = new ClientModel(['scopes' => null]);
        $clients = Mockery::mock(ClientRepository::class);
        $clients->shouldReceive('findActive')->withAnyArgs()->andReturn($client);

        $repository = new ScopeRepository($clients);

        $scopes = $repository->finalizeScopes(
            [$scope1 = new Scope('scope-1'), $scope2 = new Scope('scope-2')],
            'client_credentials',
            new Client('id', 'name', ['http://localhost']),
            '1',
        );

        $this->assertEquals([$scope1, $scope2], $scopes);
    }

    /**
     * @return void
     */
    public function testScopesDisallowedForClientAreRemoved(): void
    {
        Tessera::tokensCan([
            'scope-1' => 'description',
            'scope-2' => 'description',
        ]);

        $client = new ClientModel(['scopes' => ['scope-1']]);
        $clients = Mockery::mock(ClientRepository::class);
        $clients->shouldReceive('findActive')->withAnyArgs()->andReturn($client);

        $repository = new ScopeRepository($clients);

        $scopes = $repository->finalizeScopes(
            [$scope1 = new Scope('scope-1'), new Scope('scope-2')],
            'client_credentials',
            new Client('id', 'name', ['http://localhost']),
            '1',
        );

        $this->assertEquals([$scope1], $scopes);
    }

    /**
     * @return void
     */
    public function testScopesDisallowedForClientAreRemovedButInheritedScopesAreNot(): void
    {
        Tessera::$withInheritedScopes = true;

        Tessera::tokensCan([
            'scope-1' => 'description',
            'scope-1:limited-access' => 'description',
            'scope-2' => 'description',
        ]);

        $client = new ClientModel(['scopes' => ['scope-1']]);
        $clients = Mockery::mock(ClientRepository::class);
        $clients->shouldReceive('findActive')->withAnyArgs()->andReturn($client);

        $repository = new ScopeRepository($clients);

        $scopes = $repository->finalizeScopes(
            [$scope1 = new Scope('scope-1:limited-access'), new Scope('scope-2')],
            'client_credentials',
            new Client('id', 'name', ['http://localhost']),
            '1',
        );

        $this->assertEquals([$scope1], $scopes);
    }

    /**
     * @return void
     */
    public function testSuperuserScopeCantBeAppliedIfWrongGrant(): void
    {
        Tessera::tokensCan([
            'scope-1' => 'description',
        ]);

        $client = new ClientModel();
        $clients = Mockery::mock(ClientRepository::class);
        $clients->shouldReceive('findActive')->withAnyArgs()->andReturn($client);

        $repository = new ScopeRepository($clients);

        $scopes = $repository->finalizeScopes(
            [new Scope('*')],
            'refresh_token',
            new Client('id', 'name', ['http://localhost']),
            '1',
        );

        $this->assertEquals([], $scopes);
    }
}
