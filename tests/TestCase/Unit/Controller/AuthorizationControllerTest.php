<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Unit\Controller;

use Authentication\IdentityInterface;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\Bridge\Client as BridgeClient;
use Crustum\Tessera\Bridge\Scope as BridgeScope;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Exception\OAuthServerException;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;
use Laminas\Diactoros\Response as PsrResponse;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException as LeagueException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * AuthorizationController unit tests.
 */
class AuthorizationControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Tessera::$authorizationView = null;
        Tessera::tokensCan([]);

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testAuthorizationViewIsPresented(): void
    {
        Tessera::tokensCan([
            'scope-1' => 'description',
        ]);

        $identity = $this->makeIdentity();
        $request = (new ServerRequest())->withAttribute('identity', $identity);
        $client = $this->makeClient(false);
        $clients = $this->makeClientRepository($client);

        $authRequest = new AuthorizationRequest();
        $authRequest->setClient(new BridgeClient('1', 'Test Client'));
        $authRequest->setScopes([new BridgeScope('scope-1')]);

        $server = Mockery::mock(AuthorizationServer::class);
        $server->shouldReceive('validateAuthorizationRequest')->with($request)->andReturn($authRequest);

        $viewVars = null;
        Tessera::authorizationView(static function (array $vars) use (&$viewVars): Response {
            $viewVars = $vars;

            return (new Response())->withStringBody('view');
        });

        $controller = $this->makeController($request);
        $response = $controller->authorize($server, $clients);

        $this->assertSame('view', (string)$response->getBody());
        $this->assertSame($client, $viewVars['client']);
        $this->assertSame($identity, $viewVars['user']);
        $this->assertSame($request, $viewVars['request']);
        $this->assertSame('description', $viewVars['scopes'][0]->description);
        $this->assertIsString($viewVars['authToken']);
        $this->assertSame($viewVars['authToken'], $request->getSession()->read('authToken'));
    }

    /**
     * @return void
     */
    public function testAuthorizationExceptionsAreHandled(): void
    {
        $identity = $this->makeIdentity();
        $request = (new ServerRequest())->withAttribute('identity', $identity);
        $clients = Mockery::mock(ClientRepository::class);

        $server = Mockery::mock(AuthorizationServer::class);
        $server->shouldReceive('validateAuthorizationRequest')
            ->with($request)
            ->andThrow(LeagueException::invalidCredentials());

        $controller = $this->makeController($request);

        $this->expectException(OAuthServerException::class);

        $controller->authorize($server, $clients);
    }

    /**
     * @return void
     */
    public function testRequestIsApprovedIfValidTokenExists(): void
    {
        Tessera::tokensCan([
            'scope-1' => 'description',
        ]);

        $identity = $this->makeIdentity();
        $request = (new ServerRequest())->withAttribute('identity', $identity);
        $client = $this->makeClient(false);
        $clients = $this->makeClientRepository($client);

        $authRequest = Mockery::mock(AuthorizationRequest::class);
        $authRequest->shouldReceive('getClient->getIdentifier')->once()->andReturn('1');
        $authRequest->shouldReceive('getScopes')->once()->andReturn([new BridgeScope('scope-1')]);
        $authRequest->shouldReceive('setUser')->once()->andReturnNull();
        $authRequest->shouldReceive('setAuthorizationApproved')->once()->with(true);
        $authRequest->shouldReceive('getGrantTypeId')->once()->andReturn('authorization_code');

        $psrResponse = new PsrResponse();
        $psrResponse->getBody()->write('approved');

        $server = Mockery::mock(AuthorizationServer::class);
        $server->shouldReceive('validateAuthorizationRequest')->with($request)->andReturn($authRequest);
        $server->shouldReceive('completeAuthorizationRequest')
            ->with($authRequest, Mockery::type(PsrResponse::class))
            ->andReturn($psrResponse);

        $controller = $this->makeController($request);
        $controller->grantedScopes = true;

        $response = $controller->authorize($server, $clients);

        $this->assertSame('approved', (string)$response->getBody());
    }

    /**
     * @return void
     */
    public function testRequestIsApprovedIfClientCanSkipAuthorization(): void
    {
        Tessera::tokensCan([
            'scope-1' => 'description',
        ]);

        $identity = $this->makeIdentity();
        $request = (new ServerRequest())->withAttribute('identity', $identity);
        $client = $this->makeClient(true);
        $clients = $this->makeClientRepository($client);

        $authRequest = Mockery::mock(AuthorizationRequest::class);
        $authRequest->shouldReceive('getClient->getIdentifier')->once()->andReturn('1');
        $authRequest->shouldReceive('getScopes')->once()->andReturn([new BridgeScope('scope-1')]);
        $authRequest->shouldReceive('setUser')->once()->andReturnNull();
        $authRequest->shouldReceive('setAuthorizationApproved')->once()->with(true);
        $authRequest->shouldReceive('getGrantTypeId')->once()->andReturn('authorization_code');

        $psrResponse = new PsrResponse();
        $psrResponse->getBody()->write('approved');

        $server = Mockery::mock(AuthorizationServer::class);
        $server->shouldReceive('validateAuthorizationRequest')->with($request)->andReturn($authRequest);
        $server->shouldReceive('completeAuthorizationRequest')
            ->with($authRequest, Mockery::type(PsrResponse::class))
            ->andReturn($psrResponse);

        $controller = $this->makeController($request);

        $response = $controller->authorize($server, $clients);

        $this->assertSame('approved', (string)$response->getBody());
    }

    /**
     * @return void
     */
    public function testAuthorizationViewIsPresentedIfRequestHasPromptEqualsToConsent(): void
    {
        Tessera::tokensCan([
            'scope-1' => 'description',
        ]);

        $identity = $this->makeIdentity();
        $request = (new ServerRequest([
            'query' => ['prompt' => 'consent'],
        ]))->withAttribute('identity', $identity);
        $client = $this->makeClient(true);
        $clients = $this->makeClientRepository($client);

        $authRequest = new AuthorizationRequest();
        $authRequest->setClient(new BridgeClient('1', 'Test Client'));
        $authRequest->setScopes([new BridgeScope('scope-1')]);

        $server = Mockery::mock(AuthorizationServer::class);
        $server->shouldReceive('validateAuthorizationRequest')->with($request)->andReturn($authRequest);

        $viewVars = null;
        Tessera::authorizationView(static function (array $vars) use (&$viewVars): Response {
            $viewVars = $vars;

            return (new Response())->withStringBody('view');
        });

        $controller = $this->makeController($request);
        $response = $controller->authorize($server, $clients);

        $this->assertSame('view', (string)$response->getBody());
        $this->assertSame($client, $viewVars['client']);
        $this->assertSame($identity, $viewVars['user']);
        $this->assertSame($request, $viewVars['request']);
        $this->assertSame('description', $viewVars['scopes'][0]->description);
    }

    /**
     * @return void
     */
    public function testAuthorizationDeniedIfRequestHasPromptEqualsToNone(): void
    {
        Tessera::tokensCan([
            'scope-1' => 'description',
        ]);

        $identity = $this->makeIdentity();
        $request = (new ServerRequest([
            'query' => ['prompt' => 'none'],
        ]))->withAttribute('identity', $identity);
        $client = $this->makeClient(false);
        $clients = $this->makeClientRepository($client);

        $authRequest = Mockery::mock(AuthorizationRequest::class);
        $authRequest->shouldReceive('getClient->getIdentifier')->once()->andReturn('1');
        $authRequest->shouldReceive('getScopes')->once()->andReturn([new BridgeScope('scope-1')]);
        $authRequest->shouldReceive('setUser')->once()->andReturnNull();
        $authRequest->shouldReceive('getRedirectUri')->once()->andReturn('http://localhost');
        $authRequest->shouldReceive('getState')->once()->andReturn('state');
        $authRequest->shouldReceive('getGrantTypeId')->once()->andReturn('authorization_code');

        $server = Mockery::mock(AuthorizationServer::class);
        $server->shouldReceive('validateAuthorizationRequest')->with($request)->andReturn($authRequest);

        $controller = $this->makeController($request);

        try {
            $controller->authorize($server, $clients);
        } catch (OAuthServerException $oAuthServerException) {
            $this->assertSame('The authorization server requires end-user consent.', $oAuthServerException->getMessage());
            $httpResponse = $oAuthServerException->generateHttpResponse(new PsrResponse());
            $this->assertStringStartsWith(
                'http://localhost?state=state&error=consent_required&error_description=',
                $httpResponse->getHeaderLine('Location'),
            );

            return;
        }

        $this->fail('Expected OAuthServerException was not thrown.');
    }

    /**
     * @return void
     */
    public function testAuthorizationDeniedIfUnauthenticatedAndRequestHasPromptEqualsToNone(): void
    {
        $request = new ServerRequest([
            'query' => ['prompt' => 'none'],
        ]);
        $clients = Mockery::mock(ClientRepository::class);

        $authRequest = Mockery::mock(AuthorizationRequest::class);
        $authRequest->shouldNotReceive('setUser');
        $authRequest->shouldReceive('getRedirectUri')->andReturn('http://localhost');
        $authRequest->shouldReceive('getClient->getRedirectUri')->andReturn('http://localhost');
        $authRequest->shouldReceive('getState')->once()->andReturn('state');
        $authRequest->shouldReceive('getGrantTypeId')->once()->andReturn('authorization_code');

        $server = Mockery::mock(AuthorizationServer::class);
        $server->shouldReceive('validateAuthorizationRequest')->with($request)->andReturn($authRequest);
        $server->shouldNotReceive('completeAuthorizationRequest');

        $controller = $this->makeController($request);

        try {
            $controller->authorize($server, $clients);
        } catch (OAuthServerException $oAuthServerException) {
            $this->assertSame('The authorization server requires end-user authentication.', $oAuthServerException->getMessage());
            $httpResponse = $oAuthServerException->generateHttpResponse(new PsrResponse());
            $this->assertStringStartsWith(
                'http://localhost?state=state&error=login_required&error_description=',
                $httpResponse->getHeaderLine('Location'),
            );

            return;
        }

        $this->fail('Expected OAuthServerException was not thrown.');
    }

    /**
     * @return void
     */
    public function testLogoutAndPromptLoginIfRequestHasPromptEqualsToLogin(): void
    {
        $identity = $this->makeIdentity();
        $request = (new ServerRequest([
            'query' => ['prompt' => 'login'],
            'url' => '/oauth/authorize?prompt=login',
        ]))->withAttribute('identity', $identity);
        $clients = Mockery::mock(ClientRepository::class);

        $server = Mockery::mock(AuthorizationServer::class);
        $server->shouldReceive('validateAuthorizationRequest')->once();

        $controller = $this->makeController($request);

        $response = $controller->authorize($server, $clients);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->getHeaderLine('Location'));
        $this->assertTrue((bool)$request->getSession()->read('promptedForLogin'));
    }

    /**
     * @return void
     */
    public function testUserShouldBeAuthenticated(): void
    {
        $request = new ServerRequest([
            'url' => '/oauth/authorize?client_id=1',
        ]);
        $clients = Mockery::mock(ClientRepository::class);

        $server = Mockery::mock(AuthorizationServer::class);
        $server->shouldReceive('validateAuthorizationRequest')->once();

        $controller = $this->makeController($request);

        $response = $controller->authorize($server, $clients);

        $this->assertSame(302, $response->getStatusCode());
        $location = $response->getHeaderLine('Location');
        $this->assertStringContainsString('/login', $location);
        $this->assertStringContainsString('redirect=', $location);
        $this->assertTrue((bool)$request->getSession()->read('promptedForLogin'));
    }

    /**
     * @param \Cake\Http\ServerRequest $request Request
     * @return \Crustum\Tessera\Test\TestCase\Unit\Controller\AuthorizationControllerTestStub
     */
    private function makeController(ServerRequest $request): AuthorizationControllerTestStub
    {
        $controller = new AuthorizationControllerTestStub($request);
        $controller->setRequest($request);

        return $controller;
    }

    /**
     * @param int $id Identity identifier
     * @return \Authentication\IdentityInterface
     */
    private function makeIdentity(int $id = 1): IdentityInterface
    {
        $identity = Mockery::mock(IdentityInterface::class);
        $identity->shouldReceive('getIdentifier')->andReturn($id);

        return $identity;
    }

    /**
     * @param bool $skipsAuthorization Whether authorization should be skipped
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    private function makeClient(bool $skipsAuthorization): Client
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('skipsAuthorization')
            ->andReturnUsing(static fn(object $user, array $scopes): bool => $skipsAuthorization);

        return $client;
    }

    /**
     * @param \Crustum\Tessera\Model\Entity\Client $client Client entity
     * @return \Crustum\Tessera\ClientRepository
     */
    private function makeClientRepository(Client $client): ClientRepository
    {
        $clients = Mockery::mock(ClientRepository::class);
        $clients->shouldReceive('find')->with('1')->andReturn($client);

        return $clients;
    }
}
