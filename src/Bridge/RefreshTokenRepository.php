<?php
declare(strict_types=1);

namespace Crustum\Tessera\Bridge;

use Cake\Event\EventManager;
use Cake\Event\EventManagerInterface;
use Crustum\Tessera\Event\RefreshTokenCreatedEvent;
use Crustum\Tessera\Tessera;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

/**
 * League OAuth2 refresh token repository.
 */
class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    /**
     * @var \Cake\Event\EventManagerInterface
     */
    protected EventManagerInterface $events;

    /**
     * Optionally inject an event manager for refresh-token lifecycle events.
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
    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new RefreshToken();
    }

    /**
     * @inheritDoc
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $id = $refreshTokenEntity->getIdentifier();
        $accessTokenId = $refreshTokenEntity->getAccessToken()->getIdentifier();

        $table = Tessera::refreshTokensTable();
        $token = $table->newEntity([
            'access_token_id' => $accessTokenId,
            'revoked' => false,
            'expires' => $refreshTokenEntity->getExpiryDateTime(),
        ]);
        $token->set('id', $id);

        $table->saveOrFail($token);

        $this->events->dispatch(new RefreshTokenCreatedEvent($id, $accessTokenId));
    }

    /**
     * @inheritDoc
     */
    public function revokeRefreshToken(string $tokenId): void
    {
        Tessera::refreshTokensTable()->updateAll(
            ['revoked' => true],
            ['id' => $tokenId],
        );
    }

    /**
     * @inheritDoc
     */
    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        return !Tessera::refreshTokensTable()->exists([
            'id' => $tokenId,
            'revoked' => false,
        ]);
    }
}
