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
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Matching\Argument;
use RuntimeException;

/**
 * DenyAuthorizationController unit tests.
 */
class DenyAuthorizationControllerTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * @return void
     */
    public function testAuthorizationCanBeDenied(): void
    {
        $this->expectException(OAuthServerException::class);

        $server = Double::for(AuthorizationServer::class);
        $controller = new DenyAuthorizationController(new ServerRequest());

        $authRequest = new AuthorizationRequest();
        $authRequest->setGrantTypeId('authorization_code');

        $request = new ServerRequest();
        $request = $request->withParsedBody(['auth_token' => 'foo']);

        $session = $request->getSession();
        $session->write('authToken', 'foo');
        $session->write('authRequest', serialize($authRequest));

        $controller->setRequest($request);

        $server->allows('completeAuthorizationRequest')
            ->with(
                Argument::satisfies(
                    static fn(AuthorizationRequest $request): bool => !$request->isAuthorizationApproved(),
                ),
                Argument::type(PsrResponse::class),
            )
            ->throws(new LeagueException('', 0, ''));

        $controller->deny($server);
    }

    /**
     * @return void
     */
    public function testAuthRequestShouldExist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Authorization request was not present in the session.');

        $server = Double::for(AuthorizationServer::class);
        $controller = new DenyAuthorizationController(new ServerRequest());

        $request = new ServerRequest();
        $request = $request->withParsedBody(['auth_token' => 'foo']);

        $session = $request->getSession();
        $session->write('authToken', 'foo');

        $controller->setRequest($request);

        $server->expects('completeAuthorizationRequest')->never();

        $controller->deny($server);
    }
}
