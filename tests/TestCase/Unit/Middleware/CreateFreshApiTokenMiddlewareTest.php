<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Unit\Middleware;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\ApiTokenCookieFactory;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Middleware\CreateFreshApiTokenMiddleware;
use Crustum\Tessera\Tessera;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * CreateFreshApiTokenMiddleware unit tests.
 */
class CreateFreshApiTokenMiddlewareTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testShouldReceiveAFreshToken(): void
    {
        $cookieFactory = Mockery::mock(ApiTokenCookieFactory::class);
        $middleware = new CreateFreshApiTokenMiddleware($cookieFactory);

        $user = Mockery::mock(OAuthenticatable::class);
        $user->shouldReceive('getIdentifier')->andReturn(1);

        $request = (new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]))
            ->withAttribute('identity', $user)
            ->withAttribute('csrfToken', 't0k3n');

        $response = new Response();
        $cookieFactory->shouldReceive('make')
            ->with(1, 't0k3n')
            ->once()
            ->andReturn(new Cookie(Tessera::cookie()));

        $result = $middleware->process($request, $this->makeHandler($response));

        $this->assertTrue($this->hasTesseraCookie($result));
    }

    /**
     * @return void
     */
    public function testShouldNotReceiveAFreshTokenWhenCsrfAttributeMissing(): void
    {
        $cookieFactory = Mockery::mock(ApiTokenCookieFactory::class);
        $middleware = new CreateFreshApiTokenMiddleware($cookieFactory);

        $user = Mockery::mock(OAuthenticatable::class);
        $user->shouldReceive('getIdentifier')->andReturn(1);

        $request = (new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]))->withAttribute('identity', $user);

        $response = new Response();
        $cookieFactory->shouldReceive('make')->never();

        $result = $middleware->process($request, $this->makeHandler($response));

        $this->assertSame($response, $result);
        $this->assertFalse($this->hasTesseraCookie($result));
    }

    /**
     * @return void
     */
    public function testShouldNotReceiveAFreshTokenWhenCsrfAttributeEmpty(): void
    {
        $cookieFactory = Mockery::mock(ApiTokenCookieFactory::class);
        $middleware = new CreateFreshApiTokenMiddleware($cookieFactory);

        $user = Mockery::mock(OAuthenticatable::class);
        $user->shouldReceive('getIdentifier')->andReturn(1);

        $request = (new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]))
            ->withAttribute('identity', $user)
            ->withAttribute('csrfToken', '');

        $response = new Response();
        $cookieFactory->shouldReceive('make')->never();

        $result = $middleware->process($request, $this->makeHandler($response));

        $this->assertSame($response, $result);
        $this->assertFalse($this->hasTesseraCookie($result));
    }

    /**
     * @return void
     */
    public function testShouldNotReceiveAFreshTokenForOtherHttpVerbs(): void
    {
        $cookieFactory = Mockery::mock(ApiTokenCookieFactory::class);
        $middleware = new CreateFreshApiTokenMiddleware($cookieFactory);
        $request = new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'POST'],
        ]);
        $response = new Response();

        $result = $middleware->process($request, $this->makeHandler($response));

        $this->assertSame($response, $result);
        $this->assertFalse($this->hasTesseraCookie($result));
    }

    /**
     * @return void
     */
    public function testShouldNotReceiveAFreshTokenForAnInvalidUser(): void
    {
        $cookieFactory = Mockery::mock(ApiTokenCookieFactory::class);
        $middleware = new CreateFreshApiTokenMiddleware($cookieFactory);
        $request = new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);
        $response = new Response();

        $result = $middleware->process($request, $this->makeHandler($response));

        $this->assertSame($response, $result);
        $this->assertFalse($this->hasTesseraCookie($result));
    }

    /**
     * @return void
     */
    public function testShouldNotReceiveAFreshTokenForResponseThatAlreadyHasToken(): void
    {
        $cookieFactory = Mockery::mock(ApiTokenCookieFactory::class);
        $middleware = new CreateFreshApiTokenMiddleware($cookieFactory);

        $user = Mockery::mock(OAuthenticatable::class);
        $user->shouldReceive('getIdentifier')->andReturn(1);

        $request = (new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]))->withAttribute('identity', $user);

        $response = (new Response())->withHeader('Set-Cookie', Tessera::cookie() . '=existing');

        $result = $middleware->process($request, $this->makeHandler($response));

        $this->assertSame($response, $result);
        $this->assertTrue($this->hasTesseraCookie($result));
    }

    /**
     * Whether the response carries a Tessera API token cookie.
     *
     * @param \Psr\Http\Message\ResponseInterface $response Response
     * @return bool
     */
    protected function hasTesseraCookie(ResponseInterface $response): bool
    {
        $prefix = Tessera::cookie() . '=';
        foreach ($response->getHeader('Set-Cookie') as $cookieHeader) {
            if (str_starts_with($cookieHeader, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response Response returned by the handler
     * @return \Psr\Http\Server\RequestHandlerInterface
     */
    protected function makeHandler(ResponseInterface $response): RequestHandlerInterface
    {
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->andReturn($response);

        return $handler;
    }
}
