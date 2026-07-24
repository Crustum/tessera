<?php
declare(strict_types=1);

namespace Crustum\Tessera\Event;

use Cake\Event\Event;

/**
 * Dispatched when an access token is revoked.
 */
class AccessTokenRevokedEvent extends Event
{
    /**
     * Event name.
     *
     * @var string
     */
    public const NAME = 'Tessera.AccessToken.revoked';

    /**
     * Carry the revoked access token identifier.
     *
     * @param string $tokenId Access token identifier
     */
    public function __construct(string $tokenId)
    {
        parent::__construct(self::NAME, null, [
            'tokenId' => $tokenId,
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
}
