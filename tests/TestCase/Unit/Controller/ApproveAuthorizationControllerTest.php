<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Unit\Controller;

use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\Controller\ApproveAuthorizationController;
use Laminas\Diactoros\Response as PsrResponse;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * ApproveAuthorizationController unit tests.
 */
class ApproveAuthorizationControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testCompleteAuthorizationRequest(): void
    {
        $server = Mockery::mock(AuthorizationServer::class);

        $authRequest = new AuthorizationRequest();
        $authRequest->setGrantTypeId('authorization_code');

        $request = new ServerRequest();
        $request = $request->withParsedBody(['auth_token' => 'foo']);

        $session = $request->getSession();
        $session->write('authToken', 'foo');
        $session->write('authRequest', serialize($authRequest));

        $controller = new ApproveAuthorizationController($request);
        $controller->setRequest($request);

        $psrResponse = new PsrResponse();
        $psrResponse->getBody()->write('response');

        $server->shouldReceive('completeAuthorizationRequest')
            ->with(
                Mockery::on(
                    static fn(AuthorizationRequest $request): bool => $request->isAuthorizationApproved(),
                ),
                Mockery::type(PsrResponse::class),
            )
            ->andReturn($psrResponse);

        $response = $controller->approve($server);

        $this->assertSame('response', (string)$response->getBody());
    }
}
