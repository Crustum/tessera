<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Unit\Controller;

use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\ApiTokenCookieFactory;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Controller\TransientTokenController;
use Crustum\Tessera\Exception\AuthenticationException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * TransientTokenController unit tests.
 */
class TransientTokenControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testTokenCanBeRefreshed(): void
    {
        $cookieFactory = Mockery::mock(ApiTokenCookieFactory::class);
        $cookieFactory->shouldReceive('make')->once()->with(1, 'token')->andReturn(new Cookie('cookie', 'value'));

        $user = Mockery::mock(OAuthenticatable::class);
        $user->shouldReceive('getIdentifier')->andReturn(1);

        $request = (new ServerRequest())
            ->withAttribute('identity', $user)
            ->withAttribute('csrfToken', 'token');

        $controller = new TransientTokenController($request);
        $controller->setRequest($request);

        $response = $controller->refresh($cookieFactory);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Refreshed.', (string)$response->getBody());
    }

    /**
     * @return void
     */
    public function testRefreshFailsWhenCsrfAttributeMissing(): void
    {
        $cookieFactory = Mockery::mock(ApiTokenCookieFactory::class);
        $cookieFactory->shouldReceive('make')->never();

        $user = Mockery::mock(OAuthenticatable::class);
        $user->shouldReceive('getIdentifier')->andReturn(1);

        $request = (new ServerRequest())->withAttribute('identity', $user);

        $controller = new TransientTokenController($request);
        $controller->setRequest($request);

        $this->expectException(AuthenticationException::class);
        $controller->refresh($cookieFactory);
    }
}
