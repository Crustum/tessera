<?php
declare(strict_types=1);

namespace TestApp;

use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Middleware\AuthenticationMiddleware;
use Cake\Core\ContainerInterface;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\RouteBuilder;
use Crustum\Tessera\Authenticator\TokenAuthenticator;
use Crustum\Tessera\Support\AuthenticationCompat;
use Crustum\Tessera\Middleware\CheckTokenForAnyScopeMiddleware;
use Crustum\Tessera\Middleware\CheckTokenMiddleware;
use Crustum\Tessera\Middleware\ConvertOAuthExceptionsMiddleware;
use Crustum\Tessera\Middleware\CreateFreshApiTokenMiddleware;
use Crustum\Tessera\Middleware\EnsureClientIsResourceOwnerMiddleware;
use Crustum\Tessera\TesseraPlugin;
use Crustum\Tessera\ApiTokenCookieFactory;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ServerRequestInterface;
use TestApp\Middleware\CsrfHeaderMiddleware;

/**
 * Test application for Crustum Tessera.
 */
class Application extends BaseApplication implements AuthenticationServiceProviderInterface
{
    /**
     * Load application bootstrap and plugins.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        parent::bootstrap();

        $this->addPlugin(TesseraPlugin::class, ['routes' => true]);
    }

    /**
     * Setup the middleware queue.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue Middleware queue
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue
            ->add(new CsrfHeaderMiddleware())
            ->add(new ConvertOAuthExceptionsMiddleware())
            ->add(new BodyParserMiddleware())
            ->add(new AuthenticationMiddleware($this))
            ->add(new RoutingMiddleware($this));

        return $middlewareQueue;
    }

    /**
     * Define application routes.
     *
     * @param \Cake\Routing\RouteBuilder $routes Route builder
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        parent::routes($routes);

        $server = $this->getContainer()->get(ResourceServer::class);

        $routes->registerMiddleware(
            'tessera.ensure_create_delete',
            new EnsureClientIsResourceOwnerMiddleware($server, ['create', 'delete']),
        );
        $routes->registerMiddleware(
            'tessera.check_create_update',
            new CheckTokenMiddleware($server, ['create', 'update']),
        );
        $routes->registerMiddleware(
            'tessera.check_create_delete',
            new CheckTokenMiddleware($server, ['create', 'delete']),
        );
        $routes->registerMiddleware(
            'tessera.check_token',
            new CheckTokenMiddleware($server),
        );
        $routes->registerMiddleware(
            'tessera.check_admin_footest',
            new CheckTokenMiddleware($server, ['admin', 'footest']),
        );
        $routes->registerMiddleware(
            'tessera.check_any_admin_footest',
            new CheckTokenForAnyScopeMiddleware($server, ['admin', 'footest']),
        );
        $routes->registerMiddleware(
            'tessera.check_inherited',
            new CheckTokenMiddleware($server, ['foo:bar', 'baz:qux']),
        );
        $routes->registerMiddleware(
            'tessera.check_any_inherited',
            new CheckTokenForAnyScopeMiddleware($server, ['foo:baz', 'baz:qux']),
        );
        $routes->registerMiddleware(
            'tessera.check_any_testfoo',
            new CheckTokenForAnyScopeMiddleware($server, ['testFoo']),
        );
        $routes->registerMiddleware(
            'tessera.create_fresh',
            new CreateFreshApiTokenMiddleware($this->getContainer()->get(ApiTokenCookieFactory::class)),
        );

        $routes->scope('/', function (RouteBuilder $builder): void {
            $builder->connect('/foo', ['controller' => 'Protected', 'action' => 'index'])
                ->setMethods(['GET'])
                ->setMiddleware(['tessera.ensure_create_delete']);
            $builder->connect('/bar-missing-scope', ['controller' => 'Protected', 'action' => 'index'])
                ->setMethods(['GET'])
                ->setMiddleware(['tessera.check_create_update']);
            $builder->connect('/bar-ok-scopes', ['controller' => 'Protected', 'action' => 'index'])
                ->setMethods(['GET'])
                ->setMiddleware(['tessera.check_create_delete']);
            $builder->connect('/token-info', ['controller' => 'Protected', 'action' => 'tokenInfo'])
                ->setMethods(['GET']);
            $builder->connect('/whoami', ['controller' => 'Protected', 'action' => 'whoAmI'])
                ->setMethods(['GET']);
            $builder->connect('/guard', ['controller' => 'Protected', 'action' => 'guardInfo'])
                ->setMethods(['GET']);
            $builder->connect('/fresh', ['controller' => 'Protected', 'action' => 'index'])
                ->setMethods(['GET', 'POST'])
                ->setMiddleware(['tessera.create_fresh']);
            $builder->connect('/fresh-redirect', ['controller' => 'Protected', 'action' => 'redirectToFresh'])
                ->setMethods(['GET'])
                ->setMiddleware(['tessera.create_fresh']);
            $builder->connect('/fresh-existing', ['controller' => 'Protected', 'action' => 'existingCookie'])
                ->setMethods(['GET'])
                ->setMiddleware(['tessera.create_fresh']);
            $builder->connect('/acting-as/auth', ['controller' => 'Protected', 'action' => 'actingAsAuth'])
                ->setMethods(['GET']);
            $builder->connect('/acting-as/check-scopes', ['controller' => 'Protected', 'action' => 'index'])
                ->setMethods(['GET'])
                ->setMiddleware(['tessera.check_admin_footest']);
            $builder->connect('/acting-as/check-any-scope', ['controller' => 'Protected', 'action' => 'index'])
                ->setMethods(['GET'])
                ->setMiddleware(['tessera.check_any_admin_footest']);
            $builder->connect('/acting-as/check-inherited', ['controller' => 'Protected', 'action' => 'index'])
                ->setMethods(['GET'])
                ->setMiddleware(['tessera.check_inherited']);
            $builder->connect('/acting-as/check-any-inherited', ['controller' => 'Protected', 'action' => 'index'])
                ->setMethods(['GET'])
                ->setMiddleware(['tessera.check_any_inherited']);
            $builder->connect('/acting-as/client', ['controller' => 'Protected', 'action' => 'index'])
                ->setMethods(['GET'])
                ->setMiddleware(['tessera.check_token']);
            $builder->connect('/acting-as/client-scope', ['controller' => 'Protected', 'action' => 'index'])
                ->setMethods(['GET'])
                ->setMiddleware(['tessera.check_any_testfoo']);
            $builder->connect('/login', ['controller' => 'Login', 'action' => 'index'])
                ->setMethods(['GET']);
            $builder->fallbacks();
        });
    }

    /**
     * Register container services (plugin ServiceProviders attach via addPlugin).
     *
     * @param \Cake\Core\ContainerInterface $container DI container
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
    }

    /**
     * @inheritDoc
     */
    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $service = new AuthenticationService([
            'unauthenticatedRedirect' => '/login',
            'queryParam' => 'redirect',
        ]);

        $passwordIdentifier = AuthenticationCompat::passwordIdentifier();

        $service->loadAuthenticator(TokenAuthenticator::class, [
            'server' => $this->getContainer()->get(ResourceServer::class),
            'identifier' => $passwordIdentifier,
            'providerName' => 'users',
        ]);
        $service->loadAuthenticator('Authentication.Session', [
            'identifier' => $passwordIdentifier,
        ]);
        $service->loadAuthenticator('Authentication.Form', [
            'identifier' => $passwordIdentifier,
        ]);

        return $service;
    }
}
