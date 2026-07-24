<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Empty oauth_device_codes fixture for Feature isolation.
 */
class OauthDeviceCodesFixture extends TestFixture
{
    /**
     * @var string
     */
    public string $table = 'oauth_device_codes';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $records = [];
}
