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
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CheckTokenMiddleware unit tests.
 */
class CheckTokenMiddlewareTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * @return void
     */
    public function testRequestIsPassedAlongIfTokenIsValid(): void
    {
        $resourceServer = Double::for(ResourceServer::class);
        $psr = Double::for(ServerRequestInterface::class);
        $resourceServer->allows('validateAuthenticatedRequest')->returns($psr);
        $psr->allows('getAttributes')->returns([
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
        $user = Double::for(OAuthenticatable::class);
        $user->allows('currentAccessToken')->returns(new TransientToken());

        $resourceServer = Double::for(ResourceServer::class);
        $resourceServer->expects('validateAuthenticatedRequest')->never();

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
        $resourceServer = Double::for(ResourceServer::class);
        $psr = Double::for(ServerRequestInterface::class);
        $resourceServer->allows('validateAuthenticatedRequest')->returns($psr);
        $psr->allows('getAttributes')->returns([
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

        $resourceServer = Double::for(ResourceServer::class);
        $resourceServer->allows('validateAuthenticatedRequest')->throws(
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

        $resourceServer = Double::for(ResourceServer::class);
        $psr = Double::for(ServerRequestInterface::class);
        $resourceServer->allows('validateAuthenticatedRequest')->returns($psr);
        $psr->allows('getAttributes')->returns([
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
        $resourceServer = Double::for(ResourceServer::class);
        $middleware = new CheckTokenMiddleware($resourceServer, ['foo', 'bar']);
        $request = Double::for(ServerRequestInterface::class);
        $request->allows('getAttribute')->with('identity')->returns($user = Double::for(OAuthenticatable::class));
        $user->allows('currentAccessToken')->returns($token = Double::for(ScopeAuthorizable::class));
        $token->allows('cant')->with('foo')->returns(false);
        $token->allows('cant')->with('bar')->returns(false);

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

        $resourceServer = Double::for(ResourceServer::class);
        $middleware = new CheckTokenMiddleware($resourceServer, ['foo', 'bar']);
        $request = Double::for(ServerRequestInterface::class);
        $request->allows('getAttribute')->with('identity')->returns($user = Double::for(OAuthenticatable::class));
        $user->allows('currentAccessToken')->returns($token = Double::for(ScopeAuthorizable::class));
        $token->allows('cant')->with('foo')->returns(true);

        $middleware->process($request, $this->makeUnusedHandler());
    }

    /**
     * @return \Psr\Http\Server\RequestHandlerInterface
     */
    protected function makeUnusedHandler(): RequestHandlerInterface
    {
        $handler = Double::for(RequestHandlerInterface::class);
        $handler->expects('handle')->never();

        return $handler;
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response Response returned by the handler
     * @return \Psr\Http\Server\RequestHandlerInterface
     */
    protected function makeHandler(ResponseInterface $response): RequestHandlerInterface
    {
        $handler = Double::for(RequestHandlerInterface::class);
        $handler->expects('handle')->returns($response);

        return $handler;
    }
}
