<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Empty oauth_clients fixture for Feature isolation.
 */
class OauthClientsFixture extends TestFixture
{
    /**
     * @var string
     */
    public string $table = 'oauth_clients';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $records = [];
}
