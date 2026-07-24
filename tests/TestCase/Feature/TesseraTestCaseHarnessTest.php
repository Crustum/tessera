<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Cake\ORM\TableRegistry;
use Crustum\Tessera\Tessera;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use TestApp\Application;
use TestApp\Model\Entity\User;

/**
 * Smoke coverage for TesseraTestCase harness (F0).
 */
class TesseraTestCaseHarnessTest extends TesseraTestCase
{
    /**
     * @return void
     */
    public function testKeysAreGeneratedForFeatureSuite(): void
    {
        $this->assertFileExists(self::PRIVATE_KEY);
        $this->assertFileExists(self::PUBLIC_KEY);
        $this->assertSame(self::PRIVATE_KEY, Tessera::keyPath('oauth-private.key'));
    }

    /**
     * @return void
     */
    public function testApplicationContainerResolvesOAuthServers(): void
    {
        $app = $this->createApp();
        $this->assertInstanceOf(Application::class, $app);
        $app->bootstrap();

        $container = $app->getContainer();

        $this->assertInstanceOf(AuthorizationServer::class, $container->get(AuthorizationServer::class));
        $this->assertInstanceOf(ResourceServer::class, $container->get(ResourceServer::class));
    }

    /**
     * @return void
     */
    public function testUsersTableIsAvailable(): void
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        $user = $users->newEntity([
            'email' => 'feature@example.com',
            'password' => 'secret',
        ]);
        $this->assertTrue((bool)$users->save($user));
        $this->assertInstanceOf(User::class, $user);
        $this->assertNotSame('secret', $user->password);
    }
}
