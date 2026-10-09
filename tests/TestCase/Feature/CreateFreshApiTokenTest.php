<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Crustum\Tessera\Tessera;

/**
 * CreateFreshApiTokenMiddleware HTTP coverage with real session users.
 */
class CreateFreshApiTokenTest extends TesseraTestCase
{
    /**
     * @return void
     */
    public function testShouldReceiveAFreshToken(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);

        $this->configRequest(['headers' => ['X-CSRF-TOKEN' => 't0k3n']]);
        $this->get('/fresh');
        $this->assertResponseOk();
        $cookie = $this->assertFreshCookieIssued();

        $this->clearSession();
        $this->cookie(Tessera::cookie(), $cookie);
        $this->configRequest(['headers' => ['X-CSRF-TOKEN' => 't0k3n']]);
        $this->get('/whoami');
        $this->assertResponseOk();

        $info = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($info);
        $this->assertEquals($user->id, $info['id']);
    }

    /**
     * @return void
     */
    public function testShouldNotReceiveAFreshTokenForOtherHttpVerbs(): void
    {
        $this->loginAs($this->createUser());

        $this->configRequest(['headers' => ['X-CSRF-TOKEN' => 't0k3n']]);
        $this->post('/fresh');
        $this->assertResponseOk();
        $this->assertSame([], $this->freshCookies());
    }

    /**
     * @return void
     */
    public function testShouldNotReceiveAFreshTokenForGuests(): void
    {
        $this->clearSession();

        $this->configRequest(['headers' => ['X-CSRF-TOKEN' => 't0k3n']]);
        $this->get('/fresh');
        $this->assertResponseOk();
        $this->assertSame([], $this->freshCookies());
    }

    /**
     * @return void
     */
    public function testShouldNotReceiveAFreshTokenForRedirects(): void
    {
        $this->loginAs($this->createUser());

        $this->configRequest(['headers' => ['X-CSRF-TOKEN' => 't0k3n']]);
        $this->get('/fresh-redirect');
        $this->assertResponseCode(302);
        $this->assertSame([], $this->freshCookies());
    }

    /**
     * @return void
     */
    public function testShouldNotReceiveAFreshTokenForResponseThatAlreadyHasToken(): void
    {
        $this->loginAs($this->createUser());

        $this->configRequest(['headers' => ['X-CSRF-TOKEN' => 't0k3n']]);
        $this->get('/fresh-existing');
        $this->assertResponseOk();

        $cookie = $this->_response->getCookie(Tessera::cookie());
        $this->assertIsArray($cookie);
        $this->assertSame('existing', $cookie['value'] ?? null);
        $this->assertSame([], $this->freshCookies());
    }

    /**
     * Collect Tessera API token Set-Cookie headers from the last response.
     *
     * The middleware appends a raw Set-Cookie header, which Cake's cookie
     * collection assertions do not see, so the headers are read directly.
     *
     * @return list<string>
     */
    protected function freshCookies(): array
    {
        $found = [];
        foreach ($this->_response->getHeader('Set-Cookie') as $header) {
            if (str_starts_with($header, Tessera::cookie() . '=')) {
                $found[] = $header;
            }
        }

        return $found;
    }

    /**
     * Assert the Tessera API token cookie was issued and return its value.
     *
     * @return string
     */
    protected function assertFreshCookieIssued(): string
    {
        $cookies = $this->freshCookies();
        $this->assertCount(1, $cookies);

        $value = $this->cookieValue($cookies[0]);
        $this->assertNotEmpty($value);

        return $value;
    }

    /**
     * Extract the value from a Set-Cookie header line.
     *
     * @param string $header Set-Cookie header line
     * @return string
     */
    protected function cookieValue(string $header): string
    {
        $pair = explode(';', $header, 2)[0];

        return rawurldecode(explode('=', $pair, 2)[1] ?? '');
    }
}
