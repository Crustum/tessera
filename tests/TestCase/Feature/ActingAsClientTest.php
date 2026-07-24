<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;

/**
 * Tessera::actingAsClient Feature coverage.
 */
class ActingAsClientTest extends TesseraTestCase
{
    /**
     * @return void
     */
    public function testActingAsClientWhenTheRouteIsProtectedByCheckTokenMiddleware(): void
    {
        Tessera::actingAsClient(new Client(['id' => 'client-1'], ['guard' => false]));

        $this->get('/acting-as/client');
        $this->assertResponseOk();
        $this->assertResponseEquals('response');
    }

    /**
     * @return void
     */
    public function testActingAsClientWhenTheRouteIsProtectedByCheckTokenForAnyScope(): void
    {
        Tessera::actingAsClient(new Client(['id' => 'client-1'], ['guard' => false]), ['testFoo']);

        $this->get('/acting-as/client-scope');
        $this->assertResponseOk();
        $this->assertResponseEquals('response');
    }

    /**
     * @return void
     */
    public function testActingAsClientSetsTheClientOnTheGuard(): void
    {
        $client = new Client(['id' => 'client-1'], ['guard' => false]);
        Tessera::actingAsClient($client);

        $this->assertSame($client, Tessera::tokenClient());
    }
}
