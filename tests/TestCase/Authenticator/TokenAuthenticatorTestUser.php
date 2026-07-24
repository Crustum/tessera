<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Authenticator;

use ArrayAccess;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Trait\HasApiTokensTrait;

/**
 * User stub for TokenAuthenticator tests.
 */
class TokenAuthenticatorTestUser implements OAuthenticatable, ArrayAccess
{
    use HasApiTokensTrait;

    /**
     * @var array<string, mixed>
     */
    protected array $attributes = ['id' => 1];

    /**
     * @inheritDoc
     */
    public function getIdentifier(): array|string|int|null
    {
        return 1;
    }

    /**
     * @param mixed $offset Offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * @param mixed $offset Offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    /**
     * @param mixed $offset Offset
     * @param mixed $value Value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[(string)$offset] = $value;
    }

    /**
     * @param mixed $offset Offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }
}
