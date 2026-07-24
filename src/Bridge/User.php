<?php
declare(strict_types=1);

namespace Crustum\Tessera\Bridge;

use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\UserEntityInterface;

/**
 * League OAuth2 user entity.
 */
class User implements UserEntityInterface
{
    use EntityTrait;

    /**
     * Bind a League user entity to the given user identifier.
     *
     * @param non-empty-string $identifier User identifier
     */
    public function __construct(string $identifier)
    {
        $this->setIdentifier($identifier);
    }
}
