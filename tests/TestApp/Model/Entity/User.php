<?php
declare(strict_types=1);

namespace TestApp\Model\Entity;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\ORM\Entity;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Trait\HasApiTokensTrait;

/**
 * Test application user.
 *
 * @property int $id
 * @property string $email
 * @property string $password
 */
class User extends Entity implements OAuthenticatable
{
    use HasApiTokensTrait;

    /**
     * @var list<string>
     */
    protected array $_accessible = [
        'email' => true,
        'password' => true,
    ];

    /**
     * @var list<string>
     */
    protected array $_hidden = [
        'password',
    ];

    /**
     * @inheritDoc
     */
    public function getIdentifier(): array|string|int|null
    {
        return $this->id;
    }

    /**
     * Hash password on set.
     *
     * @param string $password Plain password
     * @return string
     */
    protected function _setPassword(string $password): string
    {
        return (new DefaultPasswordHasher())->hash($password);
    }
}
