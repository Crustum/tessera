<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Unit\Controller;

use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\Controller\AccessTokenController;
use Crustum\Tessera\Exception\OAuthServerException;
use Laminas\Diactoros\Response as PsrResponse;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException as LeagueException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * AccessTokenController unit tests.
 */
class AccessTokenControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testATokenCanBeIssued(): void
    {
        $request = new ServerRequest();
        $controller = new AccessTokenController($request);
        $controller->setRequest($request);

        $psrResponse = new PsrResponse();
        $psrResponse->getBody()->write(json_encode(['access_token' => 'access-token']));

        $server = Mockery::mock(AuthorizationServer::class);
        $server->shouldReceive('respondToAccessTokenRequest')
            ->with($request, Mockery::type(PsrResponse::class))
            ->andReturn($psrResponse);

        $response = $controller->issueToken($server);

        $this->assertSame('{"access_token":"access-token"}', (string)$response->getBody());
    }

    /**
     * @return void
     */
    public function testExceptionsAreHandled(): void
    {
        $request = new ServerRequest();
        $controller = new AccessTokenController($request);
        $controller->setRequest($request);

        $server = Mockery::mock(AuthorizationServer::class);
        $server->shouldReceive('respondToAccessTokenRequest')
            ->with($request, Mockery::type(PsrResponse::class))
            ->andThrow(LeagueException::invalidCredentials());

        $this->expectException(OAuthServerException::class);

        $controller->issueToken($server);
    }
}
