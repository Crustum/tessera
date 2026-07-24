<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Empty users table fixture for Feature isolation.
 */
class UsersFixture extends TestFixture
{
    /**
     * @var string
     */
    public string $table = 'users';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $records = [];
}
