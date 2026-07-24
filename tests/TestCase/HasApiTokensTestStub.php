<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase;

use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Trait\HasApiTokensTrait;

/**
 * User stub for HasApiTokens unit tests.
 */
class HasApiTokensTestStub implements OAuthenticatable
{
    use HasApiTokensTrait;

    /**
     * @inheritDoc
     */
    public function getIdentifier(): array|string|int|null
    {
        return 1;
    }
}
