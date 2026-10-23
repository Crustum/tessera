<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase;

use Crustum\Tessera\Model\Entity\Client;

/**
 * Client model with ULID identifiers for unique-id guard tests.
 */
class UlidClient extends Client
{
    /**
     * @param string|int $id Client identifier
     * @return bool
     */
    public function isValidUniqueId(string|int $id): bool
    {
        return is_string($id) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id) === 1;
    }
}
