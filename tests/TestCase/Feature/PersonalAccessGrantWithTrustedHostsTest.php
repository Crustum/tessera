<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Cake\Core\Configure;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\PersonalAccessTokenFactory;
use Crustum\Tessera\PersonalAccessTokenResult;
use Crustum\Tessera\Tessera;
use TestApp\Application;
use TestApp\Model\Entity\User;

/**
 * Personal access grant with trusted host / HTTPS base URL coverage.
 */
class PersonalAccessGrantWithTrustedHostsTest extends TesseraTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Configure::write('App.fullBaseUrl', 'https://local.test');
        Configure::write('Tessera.provider', 'users');
        Configure::write('Tessera.providers', [
            'users' => ['driver' => 'orm', 'model' => User::class],
        ]);
        Configure::write('Tessera.guards', [
            'api' => ['driver' => 'tessera', 'provider' => 'users'],
        ]);

        $app = $this->createApp();
        $this->assertInstanceOf(Application::class, $app);
        $app->bootstrap();
        Tessera::$personalAccessTokenFactory = $app->getContainer()->get(PersonalAccessTokenFactory::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Tessera::$personalAccessTokenFactory = null;
        Configure::write('App.fullBaseUrl', 'http://localhost');
        Configure::delete('Tessera.provider');
        Configure::delete('Tessera.providers');
        Configure::delete('Tessera.guards');

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testIssueToken(): void
    {
        $user = $this->createUser();
        (new ClientRepository())->createPersonalAccessGrantClient('PAT Client');

        $result = $user->createToken('test');

        $this->assertInstanceOf(PersonalAccessTokenResult::class, $result);
        $this->assertArrayHasKey('accessToken', $result->toArray());
    }
}
