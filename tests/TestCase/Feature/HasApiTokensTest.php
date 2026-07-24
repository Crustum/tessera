<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

/**
 * HasApiTokens provider resolution feature tests.
 */
class HasApiTokensTest extends TestCase
{
    /**
     * @return void
     */
    public function testGetProvider(): void
    {
        Configure::write('Tessera.providers', [
            'users' => ['driver' => 'orm', 'model' => UserHasApiTokensStub::class],
            'admins' => ['driver' => 'orm', 'model' => AdminHasApiTokensStub::class],
            'customers' => ['driver' => 'orm', 'model' => CustomerHasApiTokensStub::class],
        ]);
        Configure::write('Tessera.guards', [
            'api' => ['driver' => 'tessera', 'provider' => 'users'],
            'api-admins' => ['driver' => 'tessera', 'provider' => 'admins'],
            'api-customers' => ['driver' => 'tessera', 'provider' => 'customers'],
        ]);

        $this->assertSame('users', (new UserHasApiTokensStub())->getProviderName());
        $this->assertSame('admins', (new AdminHasApiTokensStub())->getProviderName());
        $this->assertSame('customers', (new CustomerHasApiTokensStub())->getProviderName());
    }
}
