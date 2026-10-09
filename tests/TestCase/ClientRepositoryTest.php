<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase;

use Cake\ORM\TableRegistry;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Test\Factory\ClientFactory;
use Crustum\Tessera\Test\TestCase\Feature\TesseraTestCase;
use Crustum\Tessera\Tessera;

/**
 * ClientRepository identifier guard unit tests.
 */
class ClientRepositoryTest extends TesseraTestCase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Tessera::$clientUuids = true;

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testMalformedUuidClientIdsDoNotExecuteALookup(): void
    {
        Tessera::$clientUuids = true;

        $repository = new ClientRepository();

        foreach (['x', '', '1', 1, '550e8400-e29b-41d4-a716-44665544000x'] as $id) {
            $this->assertNull($repository->find($id));
        }
    }

    /**
     * @return void
     */
    public function testValidUuidClientIdsAreLookedUp(): void
    {
        Tessera::$clientUuids = true;

        $model = ClientFactory::make()->persist();

        $this->assertSame($model->id, (new ClientRepository())->find($model->id)?->id);
    }

    /**
     * @return void
     */
    public function testNonUuidClientIdsAreLookedUpWhenUuidClientsAreDisabled(): void
    {
        Tessera::$clientUuids = false;

        $model = ClientFactory::make()->persist();

        $this->assertSame($model->id, (new ClientRepository())->find($model->id)?->id);
    }

    /**
     * Custom unique IDs are validated by the client model.
     *
     * No ULID row is persisted: the test schema types `oauth_clients.id`
     * as native uuid, so a ULID value cannot be stored here. Hosts using
     * ULIDs run their own column type; the dispatch (model decides, no
     * query on mismatch) is what this covers.
     *
     * @return void
     */
    public function testCustomUniqueIdClientIdsAreValidatedByTheModel(): void
    {
        Tessera::useClientModel(UlidClient::class);
        TableRegistry::getTableLocator()->clear();

        try {
            $repository = new ClientRepository();

            $this->assertNull($repository->find('x'));
            $this->assertNull($repository->find(1));
            $this->assertNull($repository->find('550e8400-e29b-41d4-a716-446655440000'));

            $model = new UlidClient();
            $this->assertTrue($model->isValidUniqueId('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
            $this->assertFalse($model->isValidUniqueId('550e8400-e29b-41d4-a716-446655440000'));
        } finally {
            Tessera::useClientModel(Client::class);
            TableRegistry::getTableLocator()->clear();
        }
    }
}

/**
 * Client model with ULID identifiers for unique-id guard tests.
 */
class UlidClient extends Client
{
    /**
     * @param string|int $id Client identifier
     * @return bool
     */
    public function isValidUniqueId(string|int $id): bool
    {
        return is_string($id) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id) === 1;
    }
}
