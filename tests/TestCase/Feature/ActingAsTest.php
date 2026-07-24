<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;
use TestApp\Model\Entity\User;

/**
 * Tessera::actingAs Feature coverage.
 */
class ActingAsTest extends TesseraTestCase
{
    /**
     * @return void
     */
    public function testActingAsWhenTheRouteIsProtectedByAuthMiddleware(): void
    {
        $client = new Client(['id' => 'client-1234'], ['guard' => false]);
        $user = new User(['id' => 1234], ['guard' => false]);

        Tessera::actingAs($user, ['admin'], $client);

        $this->get('/acting-as/auth');
        $this->assertResponseOk();
        $this->assertResponseEquals('bar');
    }

    /**
     * @return void
     */
    public function testActingAsWhenTheRouteIsProtectedByCheckScopesMiddleware(): void
    {
        Tessera::actingAs(new User(['id' => 1], ['guard' => false]), ['admin', 'footest']);

        $this->get('/acting-as/check-scopes');
        $this->assertResponseOk();
        $this->assertResponseEquals('response');
    }

    /**
     * @return void
     */
    public function testActingAsWhenTheRouteIsProtectedByCheckForAnyScopeMiddleware(): void
    {
        Tessera::actingAs(new User(['id' => 1], ['guard' => false]), ['footest']);

        $this->get('/acting-as/check-any-scope');
        $this->assertResponseOk();
        $this->assertResponseEquals('response');
    }

    /**
     * @return void
     */
    public function testActingAsWhenTheRouteIsProtectedByCheckScopesMiddlewareWithInheritance(): void
    {
        Tessera::$withInheritedScopes = true;

        Tessera::actingAs(new User(['id' => 1], ['guard' => false]), ['foo', 'baz']);

        $this->get('/acting-as/check-inherited');
        $this->assertResponseOk();
        $this->assertResponseEquals('response');
    }

    /**
     * @return void
     */
    public function testActingAsWhenTheRouteIsProtectedByCheckForAnyScopeMiddlewareWithInheritance(): void
    {
        Tessera::$withInheritedScopes = true;

        Tessera::actingAs(new User(['id' => 1], ['guard' => false]), ['foo']);

        $this->get('/acting-as/check-any-inherited');
        $this->assertResponseOk();
        $this->assertResponseEquals('response');
    }
}
