<?php
declare(strict_types=1);

namespace Crustum\Tessera\Model\Entity;

use Cake\ORM\Entity;

/**
 * OAuth authorization code entity.
 *
 * @property string $id
 * @property int|string $user_id
 * @property string $client_id
 * @property list<string>|null $scopes
 * @property bool $revoked
 * @property \Cake\I18n\DateTime|null $expires
 */
class AuthCode extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => true,
        'client_id' => true,
        'scopes' => true,
        'revoked' => true,
        'expires' => true,
    ];
}
