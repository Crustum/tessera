<?php
declare(strict_types=1);

namespace Crustum\Tessera;

use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Closure;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Model\Entity\AuthCode;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Model\Entity\DeviceCode;
use Crustum\Tessera\Model\Entity\RefreshToken;
use Crustum\Tessera\Model\Entity\Token;
use Crustum\Tessera\Model\Table\AuthCodesTable;
use Crustum\Tessera\Model\Table\ClientsTable;
use Crustum\Tessera\Model\Table\DeviceCodesTable;
use Crustum\Tessera\Model\Table\RefreshTokensTable;
use Crustum\Tessera\Model\Table\TokensTable;
use Crustum\Tessera\Scope as ScopeDefinition;
use DateInterval;
use DateTimeInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use RuntimeException;

/**
 * Tessera OAuth configuration API.
 */
class Tessera
{
    /**
     * Whether encryption key file permissions should be validated.
     *
     * @var bool
     */
    public static bool $validateKeyPermissions = true;

    /**
     * Whether the refresh token should be revoked after use.
     *
     * @var bool
     */
    public static bool $revokeRefreshTokenAfterUse = true;

    /**
     * Whether the device authorization grant type is enabled.
     *
     * @var bool
     */
    public static bool $deviceCodeGrantEnabled = true;

    /**
     * Whether the implicit grant type is enabled.
     *
     * @var bool
     */
    public static bool $implicitGrantEnabled = false;

    /**
     * Whether the password grant type is enabled.
     *
     * @var bool
     */
    public static bool $passwordGrantEnabled = false;

    /**
     * Default OAuth scope string (space-delimited).
     *
     * @var string
     */
    public static string $defaultScope = '';

    /**
     * Defined OAuth scopes (id => description).
     *
     * @var array<string, string>
     */
    public static array $scopes = [];

    /**
     * Access token TTL.
     *
     * @var \DateInterval|null
     */
    public static ?DateInterval $tokensExpireIn = null;

    /**
     * Refresh token TTL.
     *
     * @var \DateInterval|null
     */
    public static ?DateInterval $refreshTokensExpireIn = null;

    /**
     * Personal access token TTL.
     *
     * @var \DateInterval|null
     */
    public static ?DateInterval $personalAccessTokensExpireIn = null;

    /**
     * Client credentials token TTL.
     *
     * @var \DateInterval|null
     */
    public static ?DateInterval $clientCredentialsTokensExpireIn = null;

    /**
     * API token cookie name.
     *
     * @var string
     */
    public static string $cookie = 'tessera_token';

    /**
     * Whether incoming CSRF tokens should be ignored.
     *
     * @var bool
     */
    public static bool $ignoreCsrfToken = false;

    /**
     * Whether {@see actingAs()} / {@see actingAsClient()} stubs may be set and honored.
     *
     * @var bool
     */
    public static bool $actingAsAllowed = false;

    /**
     * Storage location of encryption keys.
     *
     * @var string|null
     */
    public static ?string $keyPath = null;

    /**
     * Bridge access token entity class.
     *
     * @var class-string<\Crustum\Tessera\Bridge\AccessToken>
     */
    public static string $accessTokenEntity = Bridge\AccessToken::class;

    /**
     * Auth code entity class name.
     *
     * @var class-string<\Crustum\Tessera\Model\Entity\AuthCode>
     */
    public static string $authCodeModel = Model\Entity\AuthCode::class;

    /**
     * Device code entity class name.
     *
     * @var class-string<\Crustum\Tessera\Model\Entity\DeviceCode>
     */
    public static string $deviceCodeModel = Model\Entity\DeviceCode::class;

    /**
     * Client entity class name.
     *
     * @var class-string<\Crustum\Tessera\Model\Entity\Client>
     */
    public static string $clientModel = Model\Entity\Client::class;

    /**
     * Whether client primary keys are UUIDs.
     *
     * @var bool
     */
    public static bool $clientUuids = true;

    /**
     * Access token entity class name.
     *
     * @var class-string<\Crustum\Tessera\Model\Entity\Token>
     */
    public static string $tokenModel = Model\Entity\Token::class;

    /**
     * Refresh token entity class name.
     *
     * @var class-string<\Crustum\Tessera\Model\Entity\RefreshToken>
     */
    public static string $refreshTokenModel = Model\Entity\RefreshToken::class;

    /**
     * Whether cookies should be unserialized.
     *
     * @var bool
     */
    public static bool $unserializesCookies = false;

    /**
     * Whether cookies should be decrypted.
     *
     * @var bool
     */
    public static bool $decryptsCookies = true;

    /**
     * Callback used to derive the JWT encryption key.
     *
     * @var (\Closure(string): string)|null
     */
    public static ?Closure $tokenEncryptionKeyCallback = null;

    /**
     * Indicate that inherited scopes should be resolved (e.g. `user:read` implies `user`).
     *
     * @var bool
     */
    public static bool $withInheritedScopes = false;

    /**
     * Authorization server response type override.
     *
     * @var \League\OAuth2\Server\ResponseTypes\ResponseTypeInterface|null
     */
    public static ?ResponseTypeInterface $authorizationServerResponseType = null;

    /**
     * User stubbed by {@see actingAs()} for Feature / Integration tests.
     *
     * @var \Crustum\Tessera\Contracts\OAuthenticatable|null
     */
    public static ?OAuthenticatable $actingAsUser = null;

    /**
     * Client stubbed by {@see actingAsClient()} for Feature / Integration tests.
     *
     * @var \Crustum\Tessera\Model\Entity\Client|null
     */
    public static ?Client $actingAsClient = null;

    /**
     * Access token attributes stubbed by {@see actingAsClient()}.
     *
     * @var \Crustum\Tessera\AccessToken|null
     */
    public static ?AccessToken $actingAsClientToken = null;

    /**
     * Whether plugin routes should be registered.
     *
     * @var bool
     */
    public static bool $registersRoutes = true;

    /**
     * Whether JSON API routes should be registered.
     *
     * @var bool
     */
    public static bool $registersJsonApiRoutes = false;

    /**
     * Authorization consent template name or callback (controller renders later).
     *
     * @var (\Closure(array<string, mixed>): mixed)|string|null
     */
    public static Closure|string|null $authorizationView = null;

    /**
     * Device authorization template name or callback.
     *
     * @var (\Closure(array<string, mixed>): mixed)|string|null
     */
    public static Closure|string|null $deviceAuthorizationView = null;

    /**
     * Device user-code template name or callback.
     *
     * @var (\Closure(array<string, mixed>): mixed)|string|null
     */
    public static Closure|string|null $deviceUserCodeView = null;

    /**
     * Personal access token factory override (tests / host wiring).
     *
     * @var \Crustum\Tessera\PersonalAccessTokenFactory|null
     */
    public static ?PersonalAccessTokenFactory $personalAccessTokenFactory = null;

    /**
     * Enable the implicit grant type.
     *
     * @return void
     */
    public static function enableImplicitGrant(): void
    {
        static::$implicitGrantEnabled = true;
    }

    /**
     * Enable the password grant type.
     *
     * @return void
     */
    public static function enablePasswordGrant(): void
    {
        static::$passwordGrantEnabled = true;
    }

    /**
     * Get or set the default scopes.
     *
     * @param list<string>|string|null $scopes Scopes to set, or null to read
     * @return list<string>
     */
    public static function defaultScopes(array|string|null $scopes = null): array
    {
        if ($scopes !== null) {
            static::$defaultScope = is_array($scopes) ? implode(' ', $scopes) : $scopes;
        }

        return static::$defaultScope !== ''
            ? explode(' ', static::$defaultScope)
            : [];
    }

    /**
     * Return the scopes in the given list that are defined for the application.
     *
     * @param list<string> $scopes Candidate scopes
     * @return list<string>
     */
    public static function validScopes(array $scopes): array
    {
        return array_values(array_unique(array_intersect($scopes, array_keys(static::$scopes))));
    }

    /**
     * Get all defined scope IDs.
     *
     * @return list<string>
     */
    public static function scopeIds(): array
    {
        $ids = [];
        foreach (static::scopes() as $scope) {
            $ids[] = $scope->id;
        }

        return $ids;
    }

    /**
     * Determine if the given scope has been defined.
     *
     * @param string $id Scope identifier
     * @return bool
     */
    public static function hasScope(string $id): bool
    {
        return $id === '*' || array_key_exists($id, static::$scopes);
    }

    /**
     * Get all scopes defined for the application.
     *
     * @return list<\Crustum\Tessera\Scope>
     */
    public static function scopes(): array
    {
        $scopes = [];
        foreach (static::$scopes as $id => $description) {
            $scopes[] = new ScopeDefinition($id, $description);
        }

        return $scopes;
    }

    /**
     * Get scopes matching the given IDs.
     *
     * @param list<string> $ids Scope identifiers
     * @return list<\Crustum\Tessera\Scope>
     */
    public static function scopesFor(array $ids): array
    {
        $scopes = [];
        foreach ($ids as $id) {
            if (isset(static::$scopes[$id])) {
                $scopes[] = new ScopeDefinition($id, static::$scopes[$id]);
            }
        }

        return $scopes;
    }

    /**
     * Define the scopes for the application.
     *
     * @param array<string, string> $scopes Scope id => description
     * @return void
     */
    public static function tokensCan(array $scopes): void
    {
        static::$scopes = $scopes;
    }

    /**
     * Get or set when access tokens expire.
     *
     * @param \DateTimeInterface|\DateInterval|null $date Expiry or interval
     * @return \DateInterval
     */
    public static function tokensExpireIn(DateTimeInterface|DateInterval|null $date = null): DateInterval
    {
        if ($date === null) {
            return static::$tokensExpireIn ??= new DateInterval('P1Y');
        }

        return static::$tokensExpireIn = $date instanceof DateTimeInterface
            ? DateTime::now()->diff($date)
            : $date;
    }

    /**
     * Get or set when refresh tokens expire.
     *
     * @param \DateTimeInterface|\DateInterval|null $date Expiry or interval
     * @return \DateInterval
     */
    public static function refreshTokensExpireIn(DateTimeInterface|DateInterval|null $date = null): DateInterval
    {
        if ($date === null) {
            return static::$refreshTokensExpireIn ??= new DateInterval('P1Y');
        }

        return static::$refreshTokensExpireIn = $date instanceof DateTimeInterface
            ? DateTime::now()->diff($date)
            : $date;
    }

    /**
     * Get or set when personal access tokens expire.
     *
     * @param \DateTimeInterface|\DateInterval|null $date Expiry or interval
     * @return \DateInterval
     */
    public static function personalAccessTokensExpireIn(DateTimeInterface|DateInterval|null $date = null): DateInterval
    {
        if ($date === null) {
            return static::$personalAccessTokensExpireIn ??= new DateInterval('P1Y');
        }

        return static::$personalAccessTokensExpireIn = $date instanceof DateTimeInterface
            ? DateTime::now()->diff($date)
            : $date;
    }

    /**
     * Get or set when client credentials grant tokens expire.
     *
     * @param \DateTimeInterface|\DateInterval|null $date Expiry or interval
     * @return \DateInterval|null
     */
    public static function clientCredentialsTokensExpireIn(DateTimeInterface|DateInterval|null $date = null): ?DateInterval
    {
        if ($date === null) {
            return static::$clientCredentialsTokensExpireIn;
        }

        return static::$clientCredentialsTokensExpireIn = $date instanceof DateTimeInterface
            ? DateTime::now()->diff($date)
            : $date;
    }

    /**
     * Get or set the name for API token cookies.
     *
     * @param string|null $cookie Cookie name
     * @return string
     */
    public static function cookie(?string $cookie = null): string
    {
        if ($cookie === null) {
            return static::$cookie;
        }

        return static::$cookie = $cookie;
    }

    /**
     * Indicate that Tessera should ignore incoming CSRF tokens.
     *
     * @param bool $ignoreCsrfToken Whether to ignore CSRF
     * @return void
     */
    public static function ignoreCsrfToken(bool $ignoreCsrfToken = true): void
    {
        static::$ignoreCsrfToken = $ignoreCsrfToken;
    }

    /**
     * Allow or deny Feature-test {@see actingAs()} / {@see actingAsClient()} stubs.
     *
     * @param bool $allow Whether stubs may be set and honored
     * @return void
     */
    public static function allowActingAs(bool $allow = true): void
    {
        static::$actingAsAllowed = $allow;
    }

    /**
     * Stub an authenticated API user with scopes for Feature / Integration tests.
     *
     * {@see Authenticator\TokenAuthenticator} and token middleware read {@see $actingAsUser}.
     *
     * @param \Crustum\Tessera\Contracts\OAuthenticatable $user User
     * @param list<string> $scopes Scopes
     * @param \Crustum\Tessera\Model\Entity\Client|null $client Optional client
     * @return \Crustum\Tessera\Contracts\OAuthenticatable
     */
    public static function actingAs(
        OAuthenticatable $user,
        array $scopes = [],
        ?Client $client = null,
    ): OAuthenticatable {
        static::ensureActingAsAllowed();

        $token = new AccessToken([
            'oauth_client_id' => $client?->id,
            'oauth_user_id' => $user->getIdentifier(),
            'oauth_scopes' => $scopes,
        ]);

        static::$actingAsUser = $user->withAccessToken($token);

        return static::$actingAsUser;
    }

    /**
     * Stub an authenticated OAuth client with scopes for Feature / Integration tests.
     *
     * Token middleware reads {@see $actingAsClientToken}; {@see tokenClient()} returns the client.
     *
     * @param \Crustum\Tessera\Model\Entity\Client $client Client
     * @param list<string> $scopes Scopes
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    public static function actingAsClient(Client $client, array $scopes = []): Client
    {
        static::ensureActingAsAllowed();

        static::$actingAsClient = $client;
        static::$actingAsClientToken = new AccessToken([
            'oauth_client_id' => $client->id,
            'oauth_user_id' => null,
            'oauth_scopes' => $scopes,
        ]);

        return $client;
    }

    /**
     * Client stubbed by {@see actingAsClient()} for Feature / Integration tests.
     *
     * @return \Crustum\Tessera\Model\Entity\Client|null
     */
    public static function tokenClient(): ?Client
    {
        return static::$actingAsClient;
    }

    /**
     * Clear Feature-test actingAs stubs.
     *
     * @return void
     */
    public static function clearActingAs(): void
    {
        static::$actingAsUser = null;
        static::$actingAsClient = null;
        static::$actingAsClientToken = null;
    }

    /**
     * Ensure actingAs stubs are enabled before setting them.
     *
     * @return void
     * @throws \RuntimeException
     */
    protected static function ensureActingAsAllowed(): void
    {
        if (static::$actingAsAllowed) {
            return;
        }

        throw new RuntimeException(
            'Tessera::actingAs() / actingAsClient() require Tessera::allowActingAs(true) (tests only).',
        );
    }

    /**
     * Set the storage location of the encryption keys.
     *
     * @param string $path Key directory
     * @return void
     */
    public static function loadKeysFrom(string $path): void
    {
        static::$keyPath = $path;
    }

    /**
     * Resolve the path to an encryption key file.
     *
     * @param string $file Relative key file name
     * @return string
     */
    public static function keyPath(string $file): string
    {
        $file = ltrim($file, '/\\');

        if (static::$keyPath !== null) {
            return rtrim(static::$keyPath, '/\\') . DIRECTORY_SEPARATOR . $file;
        }

        $configured = Configure::read('Tessera.keyPath');
        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/\\') . DIRECTORY_SEPARATOR . $file;
        }

        if (defined('CONFIG')) {
            return CONFIG . $file;
        }

        return $file;
    }

    /**
     * Set the access token entity class name.
     *
     * @param class-string<\Crustum\Tessera\Bridge\AccessToken> $accessTokenEntity Entity class
     * @return void
     */
    public static function useAccessTokenEntity(string $accessTokenEntity): void
    {
        static::$accessTokenEntity = $accessTokenEntity;
    }

    /**
     * Set the auth code model class name.
     *
     * @param class-string<\Crustum\Tessera\Model\Entity\AuthCode> $authCodeModel Entity class
     * @return void
     */
    public static function useAuthCodeModel(string $authCodeModel): void
    {
        static::$authCodeModel = $authCodeModel;
    }

    /**
     * Get the auth code model class name.
     *
     * @return class-string<\Crustum\Tessera\Model\Entity\AuthCode>
     */
    public static function authCodeModel(): string
    {
        return static::$authCodeModel;
    }

    /**
     * Get a new auth code entity instance.
     *
     * @return \Crustum\Tessera\Model\Entity\AuthCode
     */
    public static function authCode(): AuthCode
    {
        return new static::$authCodeModel();
    }

    /**
     * Set the default ResponseType used by the authorization server.
     *
     * @param \League\OAuth2\Server\ResponseTypes\ResponseTypeInterface|null $authorizationServerResponseType Response type
     * @return void
     */
    public static function useAuthorizationServerResponseType(?ResponseTypeInterface $authorizationServerResponseType): void
    {
        static::$authorizationServerResponseType = $authorizationServerResponseType;
    }

    /**
     * Set the device code model class name.
     *
     * @param class-string<\Crustum\Tessera\Model\Entity\DeviceCode> $deviceCodeModel Entity class
     * @return void
     */
    public static function useDeviceCodeModel(string $deviceCodeModel): void
    {
        static::$deviceCodeModel = $deviceCodeModel;
    }

    /**
     * Get the device code model class name.
     *
     * @return class-string<\Crustum\Tessera\Model\Entity\DeviceCode>
     */
    public static function deviceCodeModel(): string
    {
        return static::$deviceCodeModel;
    }

    /**
     * Get a new device code entity instance.
     *
     * @return \Crustum\Tessera\Model\Entity\DeviceCode
     */
    public static function deviceCode(): DeviceCode
    {
        return new static::$deviceCodeModel();
    }

    /**
     * Set the client model class name.
     *
     * @param class-string<\Crustum\Tessera\Model\Entity\Client> $clientModel Entity class
     * @return void
     */
    public static function useClientModel(string $clientModel): void
    {
        static::$clientModel = $clientModel;
    }

    /**
     * Get the client model class name.
     *
     * @return class-string<\Crustum\Tessera\Model\Entity\Client>
     */
    public static function clientModel(): string
    {
        return static::$clientModel;
    }

    /**
     * Get a new client entity instance.
     *
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    public static function client(): Client
    {
        return new static::$clientModel();
    }

    /**
     * Set the token model class name.
     *
     * @param class-string<\Crustum\Tessera\Model\Entity\Token> $tokenModel Entity class
     * @return void
     */
    public static function useTokenModel(string $tokenModel): void
    {
        static::$tokenModel = $tokenModel;
    }

    /**
     * Get the token model class name.
     *
     * @return class-string<\Crustum\Tessera\Model\Entity\Token>
     */
    public static function tokenModel(): string
    {
        return static::$tokenModel;
    }

    /**
     * Get a new token entity instance.
     *
     * @return \Crustum\Tessera\Model\Entity\Token
     */
    public static function token(): Token
    {
        return new static::$tokenModel();
    }

    /**
     * Set the refresh token model class name.
     *
     * @param class-string<\Crustum\Tessera\Model\Entity\RefreshToken> $refreshTokenModel Entity class
     * @return void
     */
    public static function useRefreshTokenModel(string $refreshTokenModel): void
    {
        static::$refreshTokenModel = $refreshTokenModel;
    }

    /**
     * Get the refresh token model class name.
     *
     * @return class-string<\Crustum\Tessera\Model\Entity\RefreshToken>
     */
    public static function refreshTokenModel(): string
    {
        return static::$refreshTokenModel;
    }

    /**
     * Get a new refresh token entity instance.
     *
     * @return \Crustum\Tessera\Model\Entity\RefreshToken
     */
    public static function refreshToken(): RefreshToken
    {
        return new static::$refreshTokenModel();
    }

    /**
     * Specify the callback used to generate JWT encryption keys.
     *
     * @param (\Closure(string): string)|null $callback Callback receiving the default key
     * @return void
     */
    public static function encryptTokensUsing(?Closure $callback): void
    {
        static::$tokenEncryptionKeyCallback = $callback;
    }

    /**
     * Resolve the encryption key used for JWT cookie tokens.
     *
     * @param string $defaultKey Default key (e.g. Security.salt)
     * @return string
     */
    public static function tokenEncryptionKey(string $defaultKey): string
    {
        return is_callable(static::$tokenEncryptionKeyCallback)
            ? (static::$tokenEncryptionKeyCallback)($defaultKey)
            : $defaultKey;
    }

    /**
     * Register consent views using conventional names under a plugin namespace.
     *
     * @param string $namespace View namespace prefix (without trailing `::`)
     * @return void
     */
    public static function viewNamespace(string $namespace): void
    {
        static::viewPrefix($namespace . '::');
    }

    /**
     * Register consent views using conventional names under a prefix.
     *
     * @param string $prefix View name prefix
     * @return void
     */
    public static function viewPrefix(string $prefix): void
    {
        $prefix = rtrim($prefix, '.');

        static::authorizationView($prefix . '.authorize');
        static::deviceAuthorizationView($prefix . '.device.authorize');
        static::deviceUserCodeView($prefix . '.device.user-code');
    }

    /**
     * Specify which template (or callback) controllers use for authorization consent.
     *
     * Rendering is done by the controller in Phase 8 — this only stores the name/callback.
     *
     * @param (\Closure(array<string, mixed>): mixed)|string $view Template name or callback
     * @return void
     */
    public static function authorizationView(Closure|string $view): void
    {
        static::$authorizationView = $view;
    }

    /**
     * Specify which template (or callback) controllers use for device authorization.
     *
     * @param (\Closure(array<string, mixed>): mixed)|string $view Template name or callback
     * @return void
     */
    public static function deviceAuthorizationView(Closure|string $view): void
    {
        static::$deviceAuthorizationView = $view;
    }

    /**
     * Specify which template (or callback) controllers use for the device user code screen.
     *
     * @param (\Closure(array<string, mixed>): mixed)|string $view Template name or callback
     * @return void
     */
    public static function deviceUserCodeView(Closure|string $view): void
    {
        static::$deviceUserCodeView = $view;
    }

    /**
     * Configure Tessera to not register its routes.
     *
     * @return void
     */
    public static function ignoreRoutes(): void
    {
        static::$registersRoutes = false;
    }

    /**
     * Instruct Tessera to enable cookie serialization.
     *
     * @return void
     */
    public static function withCookieSerialization(): void
    {
        static::$unserializesCookies = true;
    }

    /**
     * Instruct Tessera to disable cookie serialization.
     *
     * @return void
     */
    public static function withoutCookieSerialization(): void
    {
        static::$unserializesCookies = false;
    }

    /**
     * Instruct Tessera to enable cookie encryption.
     *
     * @return void
     */
    public static function withCookieEncryption(): void
    {
        static::$decryptsCookies = true;
    }

    /**
     * Instruct Tessera to disable cookie encryption.
     *
     * @return void
     */
    public static function withoutCookieEncryption(): void
    {
        static::$decryptsCookies = false;
    }

    /**
     * Resolve the personal access token factory.
     *
     * @return \Crustum\Tessera\PersonalAccessTokenFactory
     * @throws \RuntimeException When no factory has been registered
     */
    public static function personalAccessTokenFactory(): PersonalAccessTokenFactory
    {
        if (static::$personalAccessTokenFactory instanceof PersonalAccessTokenFactory) {
            return static::$personalAccessTokenFactory;
        }

        throw new RuntimeException(
            'PersonalAccessTokenFactory is not registered. Bind it via Tessera::$personalAccessTokenFactory or plugin services.',
        );
    }

    /**
     * Resolve the OAuth clients table locator entry.
     *
     * @return \Crustum\Tessera\Model\Table\ClientsTable
     */
    public static function clientsTable(): ClientsTable
    {
        $table = TableRegistry::getTableLocator()->get('Tessera.Clients', [
            'className' => ClientsTable::class,
        ]);
        if (!$table instanceof ClientsTable) {
            throw new RuntimeException('Tessera.Clients must resolve to ClientsTable.');
        }

        return $table;
    }

    /**
     * Resolve the OAuth access tokens table locator entry.
     *
     * @return \Crustum\Tessera\Model\Table\TokensTable
     */
    public static function tokensTable(): TokensTable
    {
        $table = TableRegistry::getTableLocator()->get('Tessera.Tokens', [
            'className' => TokensTable::class,
        ]);
        if (!$table instanceof TokensTable) {
            throw new RuntimeException('Tessera.Tokens must resolve to TokensTable.');
        }

        return $table;
    }

    /**
     * Resolve the OAuth refresh tokens table locator entry.
     *
     * @return \Crustum\Tessera\Model\Table\RefreshTokensTable
     */
    public static function refreshTokensTable(): RefreshTokensTable
    {
        $table = TableRegistry::getTableLocator()->get('Tessera.RefreshTokens', [
            'className' => RefreshTokensTable::class,
        ]);
        if (!$table instanceof RefreshTokensTable) {
            throw new RuntimeException('Tessera.RefreshTokens must resolve to RefreshTokensTable.');
        }

        return $table;
    }

    /**
     * Resolve the OAuth authorization codes table locator entry.
     *
     * @return \Crustum\Tessera\Model\Table\AuthCodesTable
     */
    public static function authCodesTable(): AuthCodesTable
    {
        $table = TableRegistry::getTableLocator()->get('Tessera.AuthCodes', [
            'className' => AuthCodesTable::class,
        ]);
        if (!$table instanceof AuthCodesTable) {
            throw new RuntimeException('Tessera.AuthCodes must resolve to AuthCodesTable.');
        }

        return $table;
    }

    /**
     * Resolve the OAuth device codes table locator entry.
     *
     * @return \Crustum\Tessera\Model\Table\DeviceCodesTable
     */
    public static function deviceCodesTable(): DeviceCodesTable
    {
        $table = TableRegistry::getTableLocator()->get('Tessera.DeviceCodes', [
            'className' => DeviceCodesTable::class,
        ]);
        if (!$table instanceof DeviceCodesTable) {
            throw new RuntimeException('Tessera.DeviceCodes must resolve to DeviceCodesTable.');
        }

        return $table;
    }
}
