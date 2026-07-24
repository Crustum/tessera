<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Model\Entity;

use Cake\TestSuite\TestCase;
use Crustum\Tessera\Model\Entity\Client;

/**
 * Client entity tests.
 */
class ClientTest extends TestCase
{
    /**
     * @return void
     */
    public function testScopesWhenClientDoesNotHaveScope(): void
    {
        $client = new Client(['scopes' => ['bar']]);
        $this->assertFalse($client->hasScope('foo'));
    }

    /**
     * @return void
     */
    public function testScopesWhenClientHasScope(): void
    {
        $client = new Client(['scopes' => ['foo', 'bar']]);
        $this->assertTrue($client->hasScope('foo'));
    }

    /**
     * @return void
     */
    public function testScopesWhenColumnDoesNotExist(): void
    {
        $client = new Client();
        $this->assertTrue($client->hasScope('foo'));
    }

    /**
     * @return void
     */
    public function testScopesWhenColumnIsNull(): void
    {
        $client = new Client(['scopes' => null]);
        $this->assertTrue($client->hasScope('foo'));
    }

    /**
     * @return void
     */
    public function testGrantTypesWhenClientDoesNotHaveGrantType(): void
    {
        $client = new Client(['grant_types' => ['bar']]);
        $this->assertFalse($client->hasGrantType('foo'));
    }

    /**
     * @return void
     */
    public function testGrantTypesWhenClientHasGrantType(): void
    {
        $client = new Client(['grant_types' => ['foo', 'bar']]);
        $this->assertTrue($client->hasGrantType('foo'));
    }

    /**
     * @return void
     */
    public function testGrantTypesWhenColumnDoesNotExist(): void
    {
        $client = new Client();

        $client->set('personal_access_client', false);
        $client->set('password_client', false);

        $this->assertFalse($client->hasGrantType('foo'));
        $this->assertFalse($client->hasGrantType('authorization_code'));
        $this->assertFalse($client->hasGrantType('password'));
        $this->assertFalse($client->hasGrantType('personal_access'));
        $this->assertFalse($client->hasGrantType('client_credentials'));

        $client->set('redirect', 'http://localhost');
        $this->assertTrue($client->hasGrantType('authorization_code'));
        $this->assertTrue($client->hasGrantType('implicit'));
        $client->unset('redirect');

        $client->set('personal_access_client', false);
        $client->set('password_client', true);

        $this->assertTrue($client->hasGrantType('password'));

        $client->set('personal_access_client', true);
        $client->set('password_client', false);
        $client->set('secret', 'secret');

        $this->assertTrue($client->hasGrantType('personal_access'));
        $this->assertTrue($client->hasGrantType('client_credentials'));
    }

    /**
     * @return void
     */
    public function testGrantTypesWhenColumnIsNull(): void
    {
        $client = new Client(['grant_types' => null]);

        $client->set('personal_access_client', false);
        $client->set('password_client', false);
        $this->assertFalse($client->hasGrantType('foo'));
        $this->assertFalse($client->hasGrantType('authorization_code'));
        $this->assertFalse($client->hasGrantType('password'));
        $this->assertFalse($client->hasGrantType('personal_access'));
        $this->assertFalse($client->hasGrantType('client_credentials'));

        $client->set('redirect', 'http://localhost');
        $this->assertTrue($client->hasGrantType('authorization_code'));
        $this->assertTrue($client->hasGrantType('implicit'));
        $client->unset('redirect');

        $client->set('personal_access_client', false);
        $client->set('password_client', true);
        $this->assertTrue($client->hasGrantType('password'));

        $client->set('personal_access_client', true);
        $client->set('password_client', false);
        $client->set('secret', 'secret');
        $this->assertTrue($client->hasGrantType('personal_access'));
        $this->assertTrue($client->hasGrantType('client_credentials'));
    }
}
