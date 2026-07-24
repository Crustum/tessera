<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Unit\Middleware;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Contracts\ScopeAuthorizable;
use Crustum\Tessera\Exception\AuthenticationException;
use Crustum\Tessera\Exception\MissingScopeException;
use Crustum\Tessera\Middleware\CheckTokenMiddleware;
use Crustum\Tessera\TransientToken;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CheckTokenMiddleware unit tests.
 */
class CheckTokenMiddlewareTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testRequestIsPassedAlongIfTokenIsValid(): void
    {
        $resourceServer = Mockery::mock(ResourceServer::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($psr = Mockery::mock(ServerRequestInterface::class));
        $psr->shouldReceive('getAttributes')->andReturn([
            'oauth_user_id' => 1,
            'oauth_client_id' => 1,
            'oauth_access_token_id' => 'token',
            'oauth_scopes' => ['*'],
        ]);

        $middleware = new CheckTokenMiddleware($resourceServer);
        $request = (new ServerRequest())->withHeader('Authorization', 'Bearer token');
        $response = new Response(['body' => 'response']);

        $result = $middleware->process($request, $this->makeHandler($response));

        $this->assertSame('response', (string)$result->getBody());
    }

    /**
     * @return void
     */
    public function testRequestIsPassedAlongIfTokenIsTransient(): void
    {
        $user = Mockery::mock(OAuthenticatable::class);
        $user->shouldReceive('currentAccessToken')->andReturn(new TransientToken());

        $resourceServer = Mockery::mock(ResourceServer::class);
        $resourceServer->shouldNotReceive('validateAuthenticatedRequest');

        $middleware = new CheckTokenMiddleware($resourceServer);
        $request = (new ServerRequest())
            ->withHeader('Authorization', 'Bearer token')
            ->withAttribute('identity', $user);
        $response = new Response(['body' => 'response']);

        $result = $middleware->process($request, $this->makeHandler($response));

        $this->assertSame('response', (string)$result->getBody());
    }

    /**
     * @return void
     */
    public function testRequestIsPassedAlongIfTokenAndScopeAreValid(): void
    {
        $resourceServer = Mockery::mock(ResourceServer::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($psr = Mockery::mock(ServerRequestInterface::class));
        $psr->shouldReceive('getAttributes')->andReturn([
            'oauth_user_id' => 1,
            'oauth_client_id' => 1,
            'oauth_access_token_id' => 'token',
            'oauth_scopes' => ['see-profile'],
        ]);

        $middleware = new CheckTokenMiddleware($resourceServer, ['see-profile']);
        $request = (new ServerRequest())->withHeader('Authorization', 'Bearer token');
        $response = new Response(['body' => 'response']);

        $result = $middleware->process($request, $this->makeHandler($response));

        $this->assertSame('response', (string)$result->getBody());
    }

    /**
     * @return void
     */
    public function testExceptionIsThrownWhenOauthThrowsException(): void
    {
        $this->expectException(AuthenticationException::class);

        $resourceServer = Mockery::mock(ResourceServer::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andThrow(
            new OAuthServerException('message', 500, 'error type'),
        );

        $middleware = new CheckTokenMiddleware($resourceServer);
        $request = (new ServerRequest())->withHeader('Authorization', 'Bearer token');

        $middleware->process($request, $this->makeUnusedHandler());
    }

    /**
     * @return void
     */
    public function testExceptionIsThrownIfTokenDoesNotHaveRequiredScopes(): void
    {
        $this->expectException(MissingScopeException::class);

        $resourceServer = Mockery::mock(ResourceServer::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($psr = Mockery::mock(ServerRequestInterface::class));
        $psr->shouldReceive('getAttributes')->andReturn([
            'oauth_user_id' => 1,
            'oauth_client_id' => 1,
            'oauth_access_token_id' => 'token',
            'oauth_scopes' => ['foo', 'notbar'],
        ]);

        $middleware = new CheckTokenMiddleware($resourceServer, ['foo', 'bar']);
        $request = (new ServerRequest())->withHeader('Authorization', 'Bearer token');

        $middleware->process($request, $this->makeUnusedHandler());
    }

    /**
     * @return void
     */
    public function testRequestIsPassedAlongIfScopesArePresentOnToken(): void
    {
        $resourceServer = Mockery::mock(ResourceServer::class);
        $middleware = new CheckTokenMiddleware($resourceServer, ['foo', 'bar']);
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getAttribute')->with('identity')->andReturn($user = Mockery::mock(OAuthenticatable::class));
        $user->shouldReceive('currentAccessToken')->andReturn($token = Mockery::mock(ScopeAuthorizable::class));
        $token->shouldReceive('cant')->with('foo')->andReturn(false);
        $token->shouldReceive('cant')->with('bar')->andReturn(false);

        $response = new Response(['body' => 'response']);
        $result = $middleware->process($request, $this->makeHandler($response));

        $this->assertSame('response', (string)$result->getBody());
    }

    /**
     * @return void
     */
    public function testExceptionIsThrownIfTokenDoesntHaveScope(): void
    {
        $this->expectException(MissingScopeException::class);

        $resourceServer = Mockery::mock(ResourceServer::class);
        $middleware = new CheckTokenMiddleware($resourceServer, ['foo', 'bar']);
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getAttribute')->with('identity')->andReturn($user = Mockery::mock(OAuthenticatable::class));
        $user->shouldReceive('currentAccessToken')->andReturn($token = Mockery::mock(ScopeAuthorizable::class));
        $token->shouldReceive('cant')->with('foo')->andReturn(true);

        $middleware->process($request, $this->makeUnusedHandler());
    }

    /**
     * @return \Psr\Http\Server\RequestHandlerInterface
     */
    protected function makeUnusedHandler(): RequestHandlerInterface
    {
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        return $handler;
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
