<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Cake\Utility\Text;
use Crustum\Tessera\Bridge\Client;
use Crustum\Tessera\Bridge\ClientRepository as BridgeClientRepository;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Test\Factory\ClientFactory;

/**
 * Bridge client repository Feature coverage against real client rows.
 */
class BridgeClientRepositoryTest extends TesseraTestCase
{
    /**
     * @return void
     */
    public function testCanGetClient(): void
    {
        $model = ClientFactory::new([
            'name' => 'Client',
            'redirect_uris' => ['http://localhost'],
        ])->save();

        $client = $this->repository()->getClientEntity($model->id);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame($model->id, $client->getIdentifier());
        $this->assertSame('Client', $client->getName());
        $this->assertEquals(['http://localhost'], $client->getRedirectUri());
        $this->assertTrue($client->isConfidential());
    }

    /**
     * @return void
     */
    public function testCanGetPublicClient(): void
    {
        $model = ClientFactory::new()->asPublic()->save();

        $client = $this->repository()->getClientEntity($model->id);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertFalse($client->isConfidential());
    }

    /**
     * @return void
     */
    public function testCannotGetRevokedOrMissingClient(): void
    {
        $revoked = ClientFactory::new(['revoked' => true])->save();

        $repository = $this->repository();

        $this->assertNull($repository->getClientEntity($revoked->id));
        $this->assertNull($repository->getClientEntity(Text::uuid()));
    }

    /**
     * @return void
     */
    public function testCanValidateClient(): void
    {
        $model = ClientFactory::new()->asClientCredentials()->save();
        $secret = $model->plainSecret;
        $this->assertNotEmpty($secret);

        $repository = $this->repository();

        foreach (['authorization_code', 'client_credentials', null] as $grantType) {
            $this->assertTrue($repository->validateClient($model->id, $secret, $grantType));
            $this->assertFalse($repository->validateClient($model->id, 'wrong-secret', $grantType));
            $this->assertFalse($repository->validateClient($model->id, null, $grantType));
            $this->assertFalse($repository->validateClient($model->id, '', $grantType));
        }
    }

    /**
     * @return void
     */
    public function testCannotValidateRevokedOrPublicClient(): void
    {
        $revoked = ClientFactory::new(['revoked' => true])->save();
        $public = ClientFactory::new()->asPublic()->save();

        $repository = $this->repository();

        $this->assertFalse($repository->validateClient(
            $revoked->id,
            (string)$revoked->plainSecret,
            'authorization_code',
        ));
        $this->assertFalse($repository->validateClient($public->id, 'secret', 'authorization_code'));
    }

    /**
     * @return \Crustum\Tessera\Bridge\ClientRepository
     */
    protected function repository(): BridgeClientRepository
    {
        return new BridgeClientRepository(new ClientRepository());
    }
}
