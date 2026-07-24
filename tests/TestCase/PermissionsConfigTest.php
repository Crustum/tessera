<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase;

use Cake\Core\Plugin;
use Cake\TestSuite\TestCase;

/**
 * Tessera config/permissions.php fragment tests.
 */
class PermissionsConfigTest extends TestCase
{
    /**
     * @return void
     */
    public function testPermissionsConfigListsOAuthEndpoints(): void
    {
        $path = Plugin::path('Crustum/Tessera') . 'config' . DS . 'permissions.php';
        /** @var list<array<string, mixed>> $rules */
        $rules = require $path;

        $this->assertNotEmpty($rules);
        foreach ($rules as $rule) {
            $this->assertTrue($rule['bypassAuth']);
            $this->assertSame('Crustum/Tessera', $rule['plugin']);
        }

        $controllers = [];
        foreach ($rules as $rule) {
            $controller = $rule['controller'];
            if (is_array($controller)) {
                foreach ($controller as $name) {
                    $controllers[] = $name;
                }
            } else {
                $controllers[] = $controller;
            }
        }

        $this->assertContains('AccessToken', $controllers);
        $this->assertContains('Authorization', $controllers);
        $this->assertContains('ApproveAuthorization', $controllers);
        $this->assertContains('DeviceUserCode', $controllers);
    }
}
