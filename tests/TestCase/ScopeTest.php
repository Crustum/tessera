<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\Tessera\Scope;

/**
 * Scope unit tests.
 */
class ScopeTest extends TestCase
{
    /**
     * @return void
     */
    public function testScopeCanBeConvertedToArray(): void
    {
        $scope = new Scope('user', 'get user information');
        $this->assertEquals([
            'id' => 'user',
            'description' => 'get user information',
        ], $scope->toArray());
    }

    /**
     * @return void
     */
    public function testScopeCanBeConvertedToJson(): void
    {
        $scope = new Scope('user', 'get user information');
        $this->assertEquals(json_encode([
            'id' => 'user',
            'description' => 'get user information',
        ]), $scope->toJson());
    }
}
