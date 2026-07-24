<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\Tessera\Tessera;
use Crustum\Tessera\Trait\ResolvesInheritedScopesTrait;

/**
 * ResolvesInheritedScopesTrait unit coverage.
 */
class ResolvesInheritedScopesTraitTest extends TestCase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Tessera::$withInheritedScopes = false;
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testScopeExistsWithoutInheritance(): void
    {
        Tessera::$withInheritedScopes = false;
        $subject = new class {
            use ResolvesInheritedScopesTrait;

            /**
             * @param string $scope Scope
             * @param list<string> $haystack Haystack
             * @return bool
             */
            public function check(string $scope, array $haystack): bool
            {
                return $this->scopeExistsIn($scope, $haystack);
            }
        };

        $this->assertTrue($subject->check('user:read', ['user:read']));
        $this->assertFalse($subject->check('user:read', ['user']));
    }

    /**
     * @return void
     */
    public function testScopeExistsWithInheritance(): void
    {
        Tessera::$withInheritedScopes = true;
        $subject = new class {
            use ResolvesInheritedScopesTrait;

            /**
             * @param string $scope Scope
             * @param list<string> $haystack Haystack
             * @return bool
             */
            public function check(string $scope, array $haystack): bool
            {
                return $this->scopeExistsIn($scope, $haystack);
            }
        };

        $this->assertTrue($subject->check('user:read', ['user']));
    }
}
