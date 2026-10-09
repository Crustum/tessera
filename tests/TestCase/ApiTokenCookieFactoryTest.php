<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\Tessera\ApiTokenCookieFactory;
use Crustum\Tessera\Tessera;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * ApiTokenCookieFactory unit tests.
 */
class ApiTokenCookieFactoryTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * @return void
     */
    public function testCookieCanBeSuccessfullyCreated(): void
    {
        $factory = new ApiTokenCookieFactory([
            'lifetime' => 120,
            'path' => '/',
            'domain' => null,
            'secure' => true,
            'same_site' => 'lax',
        ], str_repeat('a', 32));

        $cookie = $factory->make(1, 'token');

        $this->assertInstanceOf(Cookie::class, $cookie);
    }

    /**
     * @return void
     */
    public function testCookieCanBeSuccessfullyCreatedWhenUsingACustomEncryptionKey(): void
    {
        Tessera::encryptTokensUsing(fn(string $key): string => $key . '.mykey');

        $factory = new ApiTokenCookieFactory([
            'lifetime' => 120,
            'path' => '/',
            'domain' => null,
            'secure' => true,
            'same_site' => 'lax',
        ], str_repeat('a', 32));

        $cookie = $factory->make(1, 'token');

        $this->assertInstanceOf(Cookie::class, $cookie);

        Tessera::encryptTokensUsing(null);
    }
}
