<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Bridge;

use Authentication\PasswordHasher\PasswordHasherInterface;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\Bridge\Client;
use Crustum\Tessera\Bridge\ClientRepository as BridgeClientRepository;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Model\Entity\Client as ClientEntity;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;

/**
 * Bridge client repository unit tests.
 */
class BridgeClientRepositoryTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * @var \Crustum\Tessera\Bridge\ClientRepository
     */
    protected BridgeClientRepository $repository;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $client = new ClientEntity([
            'id' => '1',
            'name' => 'Client',
            'redirect_uris' => ['http://localhost'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
        $client->set(
            'secret',
            '$2y$10$WgqU4wQpfsARCIQk.nPSOOiNkrMpPVxQiLCFUt8comvQwh1z6WFMG',
            ['setter' => false],
        );

        $clientModelRepository = Double::for(ClientRepository::class);
        $clientModelRepository->allows('findActive')->returns($client);

        $hasher = Double::for(PasswordHasherInterface::class);
        $hasher->allows('check')->with('secret', $client->get('secret'))->returns(true);
        $hasher->allows('check')->returns(false);

        $this->repository = new BridgeClientRepository($clientModelRepository, $hasher);
    }

    /**
     * @return void
     */
    public function testCanGetClient(): void
    {
        $client = $this->repository->getClientEntity('1');

        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame('1', $client->getIdentifier());
        $this->assertSame('Client', $client->getName());
        $this->assertEquals(['http://localhost'], $client->getRedirectUri());
        $this->assertTrue($client->isConfidential());
    }

    /**
     * @return void
     */
    public function testCanValidateClient(): void
    {
        $this->assertTrue($this->repository->validateClient('1', 'secret', 'authorization_code'));
        $this->assertFalse($this->repository->validateClient('1', 'wrong-secret', 'authorization_code'));
        $this->assertFalse($this->repository->validateClient('1', 'wrong-secret', 'client_credentials'));
        $this->assertFalse($this->repository->validateClient('1', null, 'authorization_code'));
        $this->assertFalse($this->repository->validateClient('1', '', 'authorization_code'));
        $this->assertTrue($this->repository->validateClient('1', 'secret', null));
        $this->assertFalse($this->repository->validateClient('1', 'wrong-secret', null));
        $this->assertFalse($this->repository->validateClient('1', null, null));
        $this->assertFalse($this->repository->validateClient('1', '', null));
    }
}
