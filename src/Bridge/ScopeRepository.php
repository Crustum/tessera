<?php
declare(strict_types=1);

namespace Crustum\Tessera\Bridge;

use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Model\Entity\Client as ClientEntity;
use Crustum\Tessera\Tessera;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

/**
 * League OAuth2 scope repository.
 */
class ScopeRepository implements ScopeRepositoryInterface
{
    /**
     * Resolve scopes against Tessera definitions and client grants.
     *
     * @param \Crustum\Tessera\ClientRepository $clients App client repository
     */
    public function __construct(
        protected ClientRepository $clients,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        return Tessera::hasScope($identifier) ? new Scope($identifier) : null;
    }

    /**
     * @inheritDoc
     */
    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null,
    ): array {
        $allowedWildcard = in_array($grantType, ['password', 'personal_access', 'client_credentials'], true);

        $filtered = [];
        foreach ($scopes as $scope) {
            $id = $scope->getIdentifier();
            if (!$allowedWildcard && $id === '*') {
                continue;
            }

            if (!Tessera::hasScope($id)) {
                continue;
            }

            $filtered[] = $scope;
        }

        $client = $this->clients->findActive($clientEntity->getIdentifier());
        if ($client instanceof ClientEntity) {
            return array_values(array_filter(
                $filtered,
                fn(ScopeEntityInterface $scope): bool => $client->hasScope($scope->getIdentifier()),
            ));
        }

        return $filtered;
    }
}
