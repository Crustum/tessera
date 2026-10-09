<?php
declare(strict_types=1);

namespace Crustum\Tessera\Bridge;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Authentication\PasswordHasher\PasswordHasherInterface;
use Crustum\Tessera\ClientRepository as ClientModelRepository;
use Crustum\Tessera\Model\Entity\Client as ClientModel;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

/**
 * League OAuth2 client repository.
 */
class ClientRepository implements ClientRepositoryInterface
{
    /**
     * Adapt Tessera client persistence to the League client repository.
     *
     * @param \Crustum\Tessera\ClientRepository $clients App client repository
     * @param \Authentication\PasswordHasher\PasswordHasherInterface $hasher Password hasher
     */
    public function __construct(
        protected ClientModelRepository $clients,
        protected PasswordHasherInterface $hasher = new DefaultPasswordHasher(),
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $record = $this->clients->findActive($clientIdentifier);

        return $record instanceof ClientModel ? $this->fromClientModel($record) : null;
    }

    /**
     * @inheritDoc
     */
    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $record = $this->clients->findActive($clientIdentifier);

        return $record instanceof ClientModel
            && $clientSecret !== null
            && $clientSecret !== ''
            && $this->hasher->check($clientSecret, (string)$record->get('secret'));
    }

    /**
     * Load the personal access client for a user provider as a League entity.
     *
     * @param string $provider User provider name
     * @return \League\OAuth2\Server\Entities\ClientEntityInterface|null
     */
    public function getPersonalAccessClientEntity(string $provider): ?ClientEntityInterface
    {
        return $this->fromClientModel(
            $this->clients->personalAccessClient($provider),
        );
    }

    /**
     * Map a Tessera client entity to a League client entity.
     *
     * @param \Crustum\Tessera\Model\Entity\Client $model Client entity
     * @return \League\OAuth2\Server\Entities\ClientEntityInterface
     */
    protected function fromClientModel(ClientModel $model): ClientEntityInterface
    {
        return new Client(
            (string)$model->id,
            $model->name,
            $model->redirect_uris,
            $model->confidential(),
            $model->provider,
            $model->grant_types,
        );
    }
}
