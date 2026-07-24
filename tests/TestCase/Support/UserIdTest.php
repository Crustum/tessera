<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Support;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\Support\UserId;

/**
 * UserId support helper tests.
 */
class UserIdTest extends TestCase
{
    /**
     * @return void
     */
    public function testTypeDefaultsToInteger(): void
    {
        Configure::delete('Tessera.userIdType');

        $this->assertSame('integer', UserId::type());
        $this->assertSame(42, UserId::normalize('42'));
        $this->assertSame(
            ['type' => 'integer', 'options' => ['null' => false]],
            UserId::migrationColumn(false),
        );
    }

    /**
     * @return void
     */
    public function testTypeUuidPreservesStringIdentifiers(): void
    {
        Configure::write('Tessera.userIdType', 'uuid');

        $uuid = '8c7d9503-1234-5678-9abc-def012345678';
        $this->assertSame('uuid', UserId::type());
        $this->assertSame($uuid, UserId::normalize($uuid));
        $this->assertSame(
            ['type' => 'uuid', 'options' => ['null' => true, 'default' => null]],
            UserId::migrationColumn(true),
        );

        Configure::write('Tessera.userIdType', 'integer');
    }

    /**
     * @return void
     */
    public function testTypeStringUsesVarcharColumn(): void
    {
        Configure::write('Tessera.userIdType', 'string');

        $this->assertSame('user-abc', UserId::normalize('user-abc'));
        $this->assertSame(
            ['type' => 'string', 'options' => ['null' => false, 'limit' => 255]],
            UserId::migrationColumn(false),
        );

        Configure::write('Tessera.userIdType', 'integer');
    }
}
