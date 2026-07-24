<?php
declare(strict_types=1);

namespace Crustum\Tessera\ServiceProvider;

use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\ServiceProvider;
use Cake\Utility\Security;
use Crustum\Tessera\ApiTokenCookieFactory;
use Crustum\Tessera\Bridge\AccessTokenRepository;
use Crustum\Tessera\Bridge\AuthCodeRepository;
use Crustum\Tessera\Bridge\ClientRepository as BridgeClientRepository;
use Crustum\Tessera\Bridge\DeviceCodeRepository;
use Crustum\Tessera\Bridge\PersonalAccessBearerTokenResponse;
use Crustum\Tessera\Bridge\PersonalAccessGrant;
use Crustum\Tessera\Bridge\RefreshTokenRepository;
use Crustum\Tessera\Bridge\ScopeRepository;
use Crustum\Tessera\Bridge\UserRepository;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\PersonalAccessTokenFactory;
use Crustum\Tessera\Tessera;
use DateInterval;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\DeviceCodeGrant;
use League\OAuth2\Server\Grant\ImplicitGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;

/**
 * Registers League OAuth2 servers and Tessera repositories for method DI.
 */
class TesseraServiceProvider extends ServiceProvider
{
    /**
     * @var list<string>
     */
    protected array $provides = [
        ClientRepository::class,
        BridgeClientRepository::class,
        ScopeRepository::class,
        AccessTokenRepository::class,
        AuthCodeRepository::class,
        RefreshTokenRepository::class,
        DeviceCodeRepository::class,
        UserRepository::class,
        AuthorizationServer::class,
        ResourceServer::class,
        PersonalAccessTokenFactory::class,
        ApiTokenCookieFactory::class,
    ];

    /**
     * @inheritDoc
     */
    public function services(ContainerInterface $container): void
    {
        $container->addShared(ClientRepository::class, fn(): ClientRepository => new ClientRepository());

        $container->addShared(
            BridgeClientRepository::class,
            fn(): BridgeClientRepository => new BridgeClientRepository(
                $container->get(ClientRepository::class),
            ),
        );
        $container->addShared(
            ScopeRepository::class,
            fn(): ScopeRepository => new ScopeRepository(
                $container->get(ClientRepository::class),
            ),
        );
        $container->addShared(
            AccessTokenRepository::class,
            fn(): AccessTokenRepository => new AccessTokenRepository(),
        );
        $container->addShared(
            AuthCodeRepository::class,
            fn(): AuthCodeRepository => new AuthCodeRepository(),
        );
        $container->addShared(
            RefreshTokenRepository::class,
            fn(): RefreshTokenRepository => new RefreshTokenRepository(),
        );
        $container->addShared(
            DeviceCodeRepository::class,
            fn(): DeviceCodeRepository => new DeviceCodeRepository(),
        );
        $container->addShared(
            UserRepository::class,
            fn(): UserRepository => new UserRepository(),
        );

        $this->registerAuthorizationServer($container);
        $this->registerResourceServer($container);
        $this->registerPersonalAccessTokenFactory($container);
        $this->registerApiTokenCookieFactory($container);
    }

    /**
     * Bind the shared authorization server with enabled grants.
     *
     * @param \Cake\Core\ContainerInterface $container DI container
     * @return void
     */
    protected function registerAuthorizationServer(ContainerInterface $container): void
    {
        $container->addShared(AuthorizationServer::class, function () use ($container): AuthorizationServer {
            $server = $this->makeAuthorizationServer($container);

            $server->enableGrantType(
                $this->makeAuthCodeGrant($container),
                Tessera::tokensExpireIn(),
            );
            $server->enableGrantType(
                $this->makeRefreshTokenGrant($container),
                Tessera::tokensExpireIn(),
            );

            if (Tessera::$passwordGrantEnabled) {
                $server->enableGrantType(
                    $this->makePasswordGrant($container),
                    Tessera::tokensExpireIn(),
                );
            }

            $server->enableGrantType(
                new ClientCredentialsGrant(),
                Tessera::clientCredentialsTokensExpireIn() ?? Tessera::tokensExpireIn(),
            );

            if (Tessera::$implicitGrantEnabled) {
                $server->enableGrantType(
                    $this->makeImplicitGrant(),
                    Tessera::tokensExpireIn(),
                );
            }

            if (Tessera::$deviceCodeGrantEnabled) {
                $server->enableGrantType(
                    $this->makeDeviceCodeGrant($container),
                    Tessera::tokensExpireIn(),
                );
            }

            return $server;
        });
    }

    /**
     * Bind the resource server used by TokenAuthenticator and scope middleware.
     *
     * @param \Cake\Core\ContainerInterface $container DI container
     * @return void
     */
    protected function registerResourceServer(ContainerInterface $container): void
    {
        $container->addShared(ResourceServer::class, fn(): ResourceServer => new ResourceServer(
            $container->get(AccessTokenRepository::class),
            $this->makeCryptKey('public'),
        ));
    }

    /**
     * Bind PersonalAccessTokenFactory with a dedicated AuthorizationServer.
     *
     * @param \Cake\Core\ContainerInterface $container DI container
     * @return void
     */
    protected function registerPersonalAccessTokenFactory(ContainerInterface $container): void
    {
        $container->addShared(PersonalAccessTokenFactory::class, function () use ($container): PersonalAccessTokenFactory {
            $server = $this->makeAuthorizationServer($container, new PersonalAccessBearerTokenResponse());
            $server->enableGrantType(new PersonalAccessGrant(), Tessera::personalAccessTokensExpireIn());

            $factory = new PersonalAccessTokenFactory($server);
            Tessera::$personalAccessTokenFactory = $factory;

            return $factory;
        });
    }

    /**
     * Bind the SPA API token cookie factory from Session config and Security salt.
     *
     * @param \Cake\Core\ContainerInterface $container DI container
     * @return void
     */
    protected function registerApiTokenCookieFactory(ContainerInterface $container): void
    {
        $container->addShared(ApiTokenCookieFactory::class, function (): ApiTokenCookieFactory {
            $session = Configure::read('Session');
            if (!is_array($session)) {
                $session = [];
            }

            return new ApiTokenCookieFactory(
                $session,
                Tessera::tokenEncryptionKey(Security::getSalt()),
            );
        });
    }

    /**
     * Create and configure an Auth Code grant.
     *
     * @param \Cake\Core\ContainerInterface $container DI container
     * @return \League\OAuth2\Server\Grant\AuthCodeGrant
     */
    protected function makeAuthCodeGrant(ContainerInterface $container): AuthCodeGrant
    {
        $grant = $this->buildAuthCodeGrant($container);
        $grant->setRefreshTokenTTL(Tessera::refreshTokensExpireIn());

        return $grant;
    }

    /**
     * Build the Auth Code grant instance.
     *
     * @param \Cake\Core\ContainerInterface $container DI container
     * @return \League\OAuth2\Server\Grant\AuthCodeGrant
     */
    protected function buildAuthCodeGrant(ContainerInterface $container): AuthCodeGrant
    {
        return new AuthCodeGrant(
            $container->get(AuthCodeRepository::class),
            $container->get(RefreshTokenRepository::class),
            new DateInterval('PT10M'),
        );
    }

    /**
     * Create and configure a Refresh Token grant.
     *
     * @param \Cake\Core\ContainerInterface $container DI container
     * @return \League\OAuth2\Server\Grant\RefreshTokenGrant
     */
    protected function makeRefreshTokenGrant(ContainerInterface $container): RefreshTokenGrant
    {
        $grant = new RefreshTokenGrant($container->get(RefreshTokenRepository::class));
        $grant->setRefreshTokenTTL(Tessera::refreshTokensExpireIn());

        return $grant;
    }

    /**
     * Create and configure a Password grant.
     *
     * @param \Cake\Core\ContainerInterface $container DI container
     * @return \League\OAuth2\Server\Grant\PasswordGrant
     */
    protected function makePasswordGrant(ContainerInterface $container): PasswordGrant
    {
        $grant = new PasswordGrant(
            $container->get(UserRepository::class),
            $container->get(RefreshTokenRepository::class),
        );
        $grant->setRefreshTokenTTL(Tessera::refreshTokensExpireIn());

        return $grant;
    }

    /**
     * Create an Implicit grant.
     *
     * @return \League\OAuth2\Server\Grant\ImplicitGrant
     */
    protected function makeImplicitGrant(): ImplicitGrant
    {
        return new ImplicitGrant(Tessera::tokensExpireIn());
    }

    /**
     * Create and configure a Device Code grant.
     *
     * @param \Cake\Core\ContainerInterface $container DI container
     * @return \League\OAuth2\Server\Grant\DeviceCodeGrant
     */
    protected function makeDeviceCodeGrant(ContainerInterface $container): DeviceCodeGrant
    {
        $grant = new DeviceCodeGrant(
            $container->get(DeviceCodeRepository::class),
            $container->get(RefreshTokenRepository::class),
            new DateInterval('PT10M'),
            $this->deviceVerificationUri(),
            5,
        );
        $grant->setRefreshTokenTTL(Tessera::refreshTokensExpireIn());
        $grant->setIncludeVerificationUriComplete(true);
        $grant->setIntervalVisibility(true);

        return $grant;
    }

    /**
     * Resolve the device user-code verification URI.
     *
     * @return string
     */
    protected function deviceVerificationUri(): string
    {
        $path = '/' . trim((string)(Configure::read('Tessera.path') ?? 'oauth'), '/') . '/device';
        $base = Configure::read('App.fullBaseUrl');
        if (is_string($base) && $base !== '') {
            return rtrim($base, '/') . $path;
        }

        return $path;
    }

    /**
     * Build a League AuthorizationServer with Tessera crypt keys and defaults.
     *
     * @param \Cake\Core\ContainerInterface $container DI container
     * @param \League\OAuth2\Server\ResponseTypes\ResponseTypeInterface|null $responseType Response type
     * @return \League\OAuth2\Server\AuthorizationServer
     */
    protected function makeAuthorizationServer(
        ContainerInterface $container,
        ?ResponseTypeInterface $responseType = null,
    ): AuthorizationServer {
        $server = new AuthorizationServer(
            $container->get(BridgeClientRepository::class),
            $container->get(AccessTokenRepository::class),
            $container->get(ScopeRepository::class),
            $this->makeCryptKey('private'),
            Tessera::tokenEncryptionKey(Security::getSalt()),
            $responseType ?? Tessera::$authorizationServerResponseType,
        );
        $server->setDefaultScope(Tessera::$defaultScope);
        $server->revokeRefreshTokens(Tessera::$revokeRefreshTokenAfterUse);

        return $server;
    }

    /**
     * Create a CryptKey from Configure or a key file on disk.
     *
     * @param string $type `private` or `public`
     * @return \League\OAuth2\Server\CryptKey
     */
    protected function makeCryptKey(string $type): CryptKey
    {
        $key = str_replace('\\n', "\n", (string)(Configure::read("Tessera.{$type}_key") ?? ''));

        if ($key === '') {
            $key = 'file://' . Tessera::keyPath('oauth-' . $type . '.key');
        }

        return new CryptKey($key, null, Tessera::$validateKeyPermissions);
    }
}
