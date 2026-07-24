<?php
declare(strict_types=1);

namespace Crustum\Tessera\Bridge;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/**
 * League OAuth2 access token entity.
 */
class AccessToken implements AccessTokenEntityInterface
{
    use AccessTokenTrait;
    use EntityTrait;
    use TokenEntityTrait;

    /**
     * Bind user, scopes, and issuing client for a League access token entity.
     *
     * @param non-empty-string|null $userIdentifier User identifier
     * @param list<\League\OAuth2\Server\Entities\ScopeEntityInterface> $scopes Scopes
     * @param \League\OAuth2\Server\Entities\ClientEntityInterface $client Client
     */
    public function __construct(?string $userIdentifier, array $scopes, ClientEntityInterface $client)
    {
        if ($userIdentifier !== null) {
            $this->setUserIdentifier($userIdentifier);
        }

        foreach ($scopes as $scope) {
            $this->addScope($scope);
        }

        $this->setClient($client);
    }
}
