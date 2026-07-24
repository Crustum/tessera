<?php
declare(strict_types=1);

namespace Crustum\Tessera\Model\Entity;

use Cake\ORM\Entity;

/**
 * OAuth device code entity.
 *
 * @property string $id
 * @property int|string|null $user_id
 * @property string $client_id
 * @property string $user_code
 * @property list<string>|null $scopes
 * @property bool $revoked
 * @property \Cake\I18n\DateTime|null $user_approved
 * @property \Cake\I18n\DateTime|null $last_polled
 * @property \Cake\I18n\DateTime|null $expires
 */
class DeviceCode extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => true,
        'client_id' => true,
        'user_code' => true,
        'scopes' => true,
        'revoked' => true,
        'user_approved' => true,
        'last_polled' => true,
        'expires' => true,
    ];
}
