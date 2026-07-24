<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Empty oauth_auth_codes fixture for Feature isolation.
 */
class OauthAuthCodesFixture extends TestFixture
{
    /**
     * @var string
     */
    public string $table = 'oauth_auth_codes';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $records = [];
}
