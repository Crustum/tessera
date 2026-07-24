<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Empty oauth_refresh_tokens fixture for Feature isolation.
 */
class OauthRefreshTokensFixture extends TestFixture
{
    /**
     * @var string
     */
    public string $table = 'oauth_refresh_tokens';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $records = [];
}
