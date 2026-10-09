<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Crustum\Tessera\Tessera;

/**
 * TransientTokenController HTTP coverage with real session users.
 */
class TransientTokenControllerTest extends TesseraTestCase
{
    /**
     * @return void
     */
    public function testTokenCanBeRefreshed(): void
    {
        $this->loginAs($this->createUser());

        $this->configRequest(['headers' => ['X-CSRF-TOKEN' => 'token']]);
        $this->post('/oauth/token/refresh');
        $this->assertResponseOk();
        $this->assertResponseEquals('Refreshed.');

        $cookie = $this->_response->getCookie(Tessera::cookie());
        $this->assertIsArray($cookie);
        $this->assertNotEmpty($cookie['value'] ?? null);
    }
}
