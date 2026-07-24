<?php
declare(strict_types=1);

namespace Crustum\Tessera\Model\Entity;

use Cake\ORM\Entity;

/**
 * OAuth refresh token entity.
 *
 * @property string $id
 * @property string $access_token_id
 * @property bool $revoked
 * @property \Cake\I18n\DateTime|null $expires
 */
class RefreshToken extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'access_token_id' => true,
        'revoked' => true,
        'expires' => true,
    ];

    /**
     * Mark the refresh token as revoked in memory.
     *
     * @return $this
     */
    public function markRevoked()
    {
        $this->set('revoked', true);

        return $this;
    }
}
