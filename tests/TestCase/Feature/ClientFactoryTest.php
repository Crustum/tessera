<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;
use Crustum\Tessera\Test\Factory\ClientFactory;

/**
 * ClientFactory persist coverage (cakephp-fixture-factories + oauth fixtures).
 */
class ClientFactoryTest extends TesseraTestCase
{
    /**
     * @return void
     */
    public function testPersistCreatesOauthClientRow(): void
    {
        $client = ClientFactory::new([
            'name' => 'Factory Persist Client',
            'redirect_uris' => ['https://example.test/callback'],
        ])->save();

        $this->assertInstanceOf(Client::class, $client);
        $this->assertNotEmpty($client->id);
        $this->assertSame('Factory Persist Client', $client->name);

        $stored = Tessera::clientsTable()->get($client->id);
        $this->assertSame('Factory Persist Client', $stored->name);
        $this->assertSame(['https://example.test/callback'], $stored->redirect_uris);
    }

    /**
     * @return void
     */
    public function testPersistAsClientCredentials(): void
    {
        $client = ClientFactory::new()->asClientCredentials()->save();

        $this->assertTrue($client->hasGrantType('client_credentials'));
        $this->assertSame([], $client->redirect_uris);
    }
}
