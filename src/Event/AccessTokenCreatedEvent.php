<?php
declare(strict_types=1);

namespace Crustum\Tessera\Event;

use Cake\Event\Event;

/**
 * Dispatched when an access token is created.
 */
class AccessTokenCreatedEvent extends Event
{
    /**
     * Event name.
     *
     * @var string
     */
    public const NAME = 'Tessera.AccessToken.created';

    /**
     * Carry the new access token id with its user and client ids.
     *
     * @param string $tokenId Access token identifier
     * @param string|null $userId User identifier
     * @param string $clientId Client identifier
     */
    public function __construct(
        string $tokenId,
        ?string $userId,
        string $clientId,
    ) {
        parent::__construct(self::NAME, null, [
            'tokenId' => $tokenId,
            'userId' => $userId,
            'clientId' => $clientId,
        ]);
    }

    /**
     * Access token identifier.
     *
     * @return string
     */
    public function getTokenId(): string
    {
        return $this->getData('tokenId');
    }

    /**
     * User identifier, if any.
     *
     * @return string|null
     */
    public function getUserId(): ?string
    {
        return $this->getData('userId');
    }

    /**
     * OAuth client identifier.
     *
     * @return string
     */
    public function getClientId(): string
    {
        return $this->getData('clientId');
    }
}
