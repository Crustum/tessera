<?php
declare(strict_types=1);

namespace Crustum\Tessera\Bridge;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;

/**
 * League OAuth2 scope entity.
 */
class Scope implements ScopeEntityInterface
{
    use EntityTrait;
    use ScopeTrait;

    /**
     * Bind a League scope entity to the given scope identifier.
     *
     * @param non-empty-string $name Scope identifier
     */
    public function __construct(string $name)
    {
        $this->setIdentifier($name);
    }
}
