<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Cake\Cache\Cache;
use Cake\ORM\TableRegistry;
use Cake\Utility\Text;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Test\Factory\ClientFactory;
use Crustum\Tessera\Tessera;
use TestApp\Model\Entity\User;

/**
 * ClientRepository owner-column coverage (owner default + legacy user_id).
 */
class ClientRepositoryTest extends TesseraTestCase
{
    /**
     * @return void
     */
    public function testForUserReturnsOwnerClientsOrderedByName(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $repository = new ClientRepository();

        $mine = ClientFactory::make([
            'owner_id' => (string)$user->id,
            'owner_type' => User::class,
            'name' => 'Mine',
        ])->persist();
        ClientFactory::make([
            'owner_id' => (string)$user->id,
            'owner_type' => User::class,
            'name' => 'Revoked',
            'revoked' => true,
        ])->persist();
        ClientFactory::make([
            'owner_id' => (string)$other->id,
            'owner_type' => User::class,
        ])->persist();

        $clients = $repository->forUser($user);

        $this->assertCount(1, $clients);
        $this->assertSame($mine->id, $clients[0]->id);

        $found = $repository->findForUser($mine->id, $user);
        $this->assertNotNull($found);
        $this->assertSame($mine->id, $found->id);
        $this->assertNull($repository->findForUser($mine->id, $other));
    }

    /**
     * @return void
     */
    public function testForUserViaLegacyUserIdColumn(): void
    {
        $this->addLegacyUserIdColumn();

        try {
            $user = $this->createUser();
            $other = $this->createUser();
            $repository = new ClientRepository();

            $mineId = Text::uuid();
            $this->insertLegacyClient($mineId, (int)$user->id, 'Mine', false);
            $this->insertLegacyClient(Text::uuid(), (int)$user->id, 'Revoked', true);
            $this->insertLegacyClient(Text::uuid(), (int)$other->id, 'Theirs', false);

            $clients = $repository->forUser($user);

            $this->assertCount(1, $clients);
            $this->assertSame($mineId, $clients[0]->id);

            $found = $repository->findForUser($mineId, $user);
            $this->assertNotNull($found);
            $this->assertSame($mineId, $found->id);
            $this->assertNull($repository->findForUser($mineId, $other));
        } finally {
            $this->dropLegacyUserIdColumn();
        }
    }

    /**
     * @return void
     */
    private function addLegacyUserIdColumn(): void
    {
        $connection = Tessera::clientsTable()->getConnection();
        $connection->execute('ALTER TABLE oauth_clients ADD COLUMN user_id INTEGER NULL')->closeCursor();
        TableRegistry::getTableLocator()->clear();
        Cache::clear('_cake_model_');
    }

    /**
     * @return void
     */
    private function dropLegacyUserIdColumn(): void
    {
        $connection = Tessera::clientsTable()->getConnection();
        $connection->execute('ALTER TABLE oauth_clients DROP COLUMN user_id')->closeCursor();
        TableRegistry::getTableLocator()->clear();
        Cache::clear('_cake_model_');
    }

    /**
     * @param string $id Client identifier
     * @param int $userId Legacy owner id
     * @param string $name Client name
     * @param bool $revoked Revoked flag
     * @return void
     */
    private function insertLegacyClient(string $id, int $userId, string $name, bool $revoked): void
    {
        $connection = Tessera::clientsTable()->getConnection();
        $connection->execute(
            'INSERT INTO oauth_clients (id, user_id, name, secret, redirect_uris, grant_types, revoked)'
            . " VALUES (:id, :userId, :name, NULL, '[]', '[\"authorization_code\"]', :revoked)",
            ['id' => $id, 'userId' => $userId, 'name' => $name, 'revoked' => $revoked ? 1 : 0],
        )->closeCursor();
    }
}
