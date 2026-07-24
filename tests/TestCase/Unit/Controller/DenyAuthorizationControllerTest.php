<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Unit\Controller;

use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\Controller\DenyAuthorizationController;
use Crustum\Tessera\Exception\OAuthServerException;
use Laminas\Diactoros\Response as PsrResponse;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException as LeagueException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;

/**
 * DenyAuthorizationController unit tests.
 */
class DenyAuthorizationControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testAuthorizationCanBeDenied(): void
    {
        $this->expectException(OAuthServerException::class);

        $server = Mockery::mock(AuthorizationServer::class);
        $controller = new DenyAuthorizationController(new ServerRequest());

        $authRequest = new AuthorizationRequest();
        $authRequest->setGrantTypeId('authorization_code');

        $request = new ServerRequest();
        $request = $request->withParsedBody(['auth_token' => 'foo']);

        $session = $request->getSession();
        $session->write('authToken', 'foo');
        $session->write('authRequest', serialize($authRequest));

        $controller->setRequest($request);

        $server->shouldReceive('completeAuthorizationRequest')
            ->with(
                Mockery::on(
                    static fn(AuthorizationRequest $request): bool => !$request->isAuthorizationApproved(),
                ),
                Mockery::type(PsrResponse::class),
            )
            ->andReturnUsing(static function (): void {
                throw new LeagueException('', 0, '');
            });

        $controller->deny($server);
    }

    /**
     * @return void
     */
    public function testAuthRequestShouldExist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Authorization request was not present in the session.');

        $server = Mockery::mock(AuthorizationServer::class);
        $controller = new DenyAuthorizationController(new ServerRequest());

        $request = new ServerRequest();
        $request = $request->withParsedBody(['auth_token' => 'foo']);

        $session = $request->getSession();
        $session->write('authToken', 'foo');

        $controller->setRequest($request);

        $server->shouldReceive('completeAuthorizationRequest')->never();

        $controller->deny($server);
    }
}
