<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Trait\HasApiTokensTrait;

/**
 * Customers provider stub.
 */
class CustomerHasApiTokensStub implements OAuthenticatable
{
    use HasApiTokensTrait;

    /**
     * @inheritDoc
     */
    public function getIdentifier(): array|string|int|null
    {
        return 3;
    }
}
