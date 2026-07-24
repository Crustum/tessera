<?php
declare(strict_types=1);

namespace Crustum\Tessera\Event;

use Cake\Event\Event;

/**
 * Dispatched when a refresh token is created.
 */
class RefreshTokenCreatedEvent extends Event
{
    /**
     * Event name.
     *
     * @var string
     */
    public const NAME = 'Tessera.RefreshToken.created';

    /**
     * Carry the new refresh token id and its related access token id.
     *
     * @param string $refreshTokenId Refresh token identifier
     * @param string $accessTokenId Access token identifier
     */
    public function __construct(
        string $refreshTokenId,
        string $accessTokenId,
    ) {
        parent::__construct(self::NAME, null, [
            'refreshTokenId' => $refreshTokenId,
            'accessTokenId' => $accessTokenId,
        ]);
    }

    /**
     * Refresh token identifier.
     *
     * @return string
     */
    public function getRefreshTokenId(): string
    {
        return $this->getData('refreshTokenId');
    }

    /**
     * Related access token identifier.
     *
     * @return string
     */
    public function getAccessTokenId(): string
    {
        return $this->getData('accessTokenId');
    }
}
