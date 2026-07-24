<?php
declare(strict_types=1);

namespace Crustum\Tessera\Bridge;

use Cake\Event\EventManager;
use Cake\Event\EventManagerInterface;
use Crustum\Tessera\Event\AccessTokenCreatedEvent;
use Crustum\Tessera\Event\AccessTokenRevokedEvent;
use Crustum\Tessera\Support\UserId;
use Crustum\Tessera\Tessera;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

/**
 * League OAuth2 access token repository.
 */
class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    /**
     * @var \Cake\Event\EventManagerInterface
     */
    protected EventManagerInterface $events;

    /**
     * Optionally inject an event manager for token lifecycle events.
     *
     * @param \Cake\Event\EventManagerInterface|null $events Event manager
     */
    public function __construct(?EventManagerInterface $events = null)
    {
        $this->events = $events ?? EventManager::instance();
    }

    /**
     * @inheritDoc
     */
    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        ?string $userIdentifier = null,
    ): AccessTokenEntityInterface {
        $class = Tessera::$accessTokenEntity;

        return new $class($userIdentifier, $scopes, $clientEntity);
    }

    /**
     * @inheritDoc
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $id = $accessTokenEntity->getIdentifier();
        $userId = $accessTokenEntity->getUserIdentifier();
        $clientId = $accessTokenEntity->getClient()->getIdentifier();

        $table = Tessera::tokensTable();
        $token = $table->newEntity([
            'user_id' => UserId::normalize($userId),
            'client_id' => $clientId,
            'scopes' => $this->scopeIdentifiers($accessTokenEntity->getScopes()),
            'revoked' => false,
            'expires' => $accessTokenEntity->getExpiryDateTime(),
        ]);
        $token->set('id', $id);

        $table->saveOrFail($token);

        $this->events->dispatch(new AccessTokenCreatedEvent(
            $id,
            $userId,
            $clientId,
        ));
    }

    /**
     * @inheritDoc
     */
    public function revokeAccessToken(string $tokenId): void
    {
        $updated = Tessera::tokensTable()->updateAll(
            ['revoked' => true],
            [
                'id' => $tokenId,
                'revoked' => false,
            ],
        );

        if ($updated > 0) {
            $this->events->dispatch(new AccessTokenRevokedEvent($tokenId));
        }
    }

    /**
     * @inheritDoc
     */
    public function isAccessTokenRevoked(string $tokenId): bool
    {
        return !Tessera::tokensTable()->exists([
            'id' => $tokenId,
            'revoked' => false,
        ]);
    }

    /**
     * Extract scope identifier strings from League scope entities.
     *
     * @param array<\League\OAuth2\Server\Entities\ScopeEntityInterface> $scopes Scopes
     * @return list<string>
     */
    protected function scopeIdentifiers(array $scopes): array
    {
        $ids = [];
        foreach ($scopes as $scope) {
            $ids[] = $scope->getIdentifier();
        }

        return $ids;
    }
}
