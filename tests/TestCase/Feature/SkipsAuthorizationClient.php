<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Crustum\Tessera\Model\Entity\Client;

/**
 * Client entity that always skips the authorization prompt.
 */
class SkipsAuthorizationClient extends Client
{
    /**
     * @inheritDoc
     */
    public function skipsAuthorization(object $user, array $scopes): bool
    {
        return true;
    }
}
