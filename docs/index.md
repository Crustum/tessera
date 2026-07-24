# Crustum Tessera

- [Introduction](#introduction)
- [Installation](#installation)
    - [Deploying Tessera](#deploying-tessera)
    - [Loading Keys From the Environment](#loading-keys-from-the-environment)
- [Configuration](#configuration)
    - [Token Lifetimes](#token-lifetimes)
    - [Overriding Default Models](#overriding-default-models)
    - [Overriding Routes](#overriding-routes)
- [Authorization Code Grant](#authorization-code-grant)
    - [Managing Clients](#managing-clients)
    - [Requesting Tokens](#requesting-tokens)
    - [Managing Tokens](#managing-tokens)
    - [Refreshing Tokens](#refreshing-tokens)
    - [Revoking Tokens](#revoking-tokens)
    - [Purging Tokens](#purging-tokens)
- [Authorization Code Grant With PKCE](#code-grant-pkce)
    - [Creating the Client](#creating-a-auth-pkce-grant-client)
    - [Requesting Tokens](#requesting-auth-pkce-grant-tokens)
- [Device Authorization Grant](#device-authorization-grant)
    - [Creating a Device Authorization Grant Client](#creating-a-device-authorization-grant-client)
    - [Requesting Tokens](#requesting-device-authorization-grant-tokens)
- [Password Grant](#password-grant)
    - [Creating a Password Grant Client](#creating-a-password-grant-client)
    - [Requesting Tokens](#requesting-password-grant-tokens)
    - [Requesting All Scopes](#requesting-all-scopes)
    - [Customizing the User Provider](#customizing-the-user-provider)
    - [Customizing the Username Field](#customizing-the-username-field)
    - [Customizing the Password Validation](#customizing-the-password-validation)
- [Implicit Grant](#implicit-grant)
- [Client Credentials Grant](#client-credentials-grant)
    - [Retrieving Tokens](#retrieving-tokens)
- [Personal Access Tokens](#personal-access-tokens)
    - [Creating a Personal Access Client](#creating-a-personal-access-client)
    - [Customizing the User Provider](#customizing-the-user-provider-for-pat)
    - [Managing Personal Access Tokens](#managing-personal-access-tokens)
- [Protecting Routes](#protecting-routes)
    - [Via Middleware](#via-middleware)
    - [Passing the Access Token](#passing-the-access-token)
- [Token Scopes](#token-scopes)
    - [Defining Scopes](#defining-scopes)
    - [Default Scope](#default-scope)
    - [Assigning Scopes to Tokens](#assigning-scopes-to-tokens)
    - [Checking Scopes](#checking-scopes)
- [SPA Authentication](#spa-authentication)
- [Events](#events)
- [Testing](#testing)

<a name="introduction"></a>
## Introduction

[Crustum Tessera](https://github.com/crustum/tessera) provides a full OAuth2 server implementation for your CakePHP application. Tessera is built on top of the [League OAuth2 server](https://github.com/thephpleague/oauth2-server) and integrates with [cakephp/authentication](https://book.cakephp.org/authentication/3/en/index.html) so your host application can issue and validate API access tokens using familiar CakePHP patterns.

> [!NOTE]
> This documentation assumes you are already familiar with OAuth2. If you do not know anything about OAuth2, consider familiarizing yourself with the general [terminology](https://oauth2.thephpleague.com/terminology/) and features of OAuth2 before continuing.

Tessera is the right choice when your application needs to act as an OAuth2 authorization server — for example when third-party applications must obtain authorization codes, when machine-to-machine clients need client credentials, when limited-input devices need the device authorization grant, or when you want personal access tokens issued from your own API.

<a name="installation"></a>
## Installation

Install via Composer:

```bash
composer require crustum/tessera
```

> [!NOTE]
> This plugin should be registered in your `config/plugins.php` file.

```bash
bin/cake plugin load Crustum/Tessera
```

> [!TIP]
> **After the plugin registers itself**, install configuration and migrations with the manifest system:

```bash
bin/cake manifest install --plugin Crustum/Tessera
```

The Tessera plugin will create the `config/tessera.php` configuration file, copy the OAuth database migrations into your application's migrations directory, and append loading of `config/tessera.php` to `config/bootstrap.php`. The plugin does not generate encryption keys during the manifest install; you run `bin/cake tessera keys` after migrations.

Alternatively, you can load the plugin in your `Application.php`:

```php
// In src/Application.php
public function bootstrap(): void
{
    parent::bootstrap();

    $this->addPlugin(\Crustum\Tessera\TesseraPlugin::class);
}
```

After installing assets, run migrations and generate encryption keys:

```bash
bin/cake migrations migrate
bin/cake tessera keys
```

After the migrations have run, add the `HasApiTokensTrait` trait and `OAuthenticatable` interface to your user entity. This trait provides helper methods that allow you to inspect the authenticated user's token and scopes:

```php
<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Trait\HasApiTokensTrait;

class User extends Entity implements OAuthenticatable
{
    use HasApiTokensTrait;

    public function getIdentifier(): array|string|int|null
    {
        return $this->id;
    }
}
```

Finally, register Tessera's `TokenAuthenticator` in your host `AuthenticationService`. Place it before the Session authenticator when API Bearer tokens should take precedence over an interactive session:

```php
use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Crustum\Tessera\Authenticator\TokenAuthenticator;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ServerRequestInterface;

public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
{
    $service = new AuthenticationService();

    $passwordIdentifier = [
        'Authentication.Password' => [
            'fields' => [
                'username' => 'email',
                'password' => 'password',
            ],
            'resolver' => [
                'className' => 'Authentication.Orm',
                'userModel' => 'Users',
            ],
        ],
    ];

    $service->loadAuthenticator(TokenAuthenticator::class, [
        'server' => $this->getContainer()->get(ResourceServer::class),
        'identifier' => $passwordIdentifier,
        'providerName' => 'users',
    ]);
    $service->loadAuthenticator('Authentication.Session', [
        'identifier' => $passwordIdentifier,
    ]);

    return $service;
}
```

Align `Tessera.usersTable` in `config/tessera.php` with your host users table alias when needed. CakeDC/Users is not a Tessera dependency; if you use it, point Tessera at the same users table alias your host already configures.

Set `Tessera.userIdType` **before** running Tessera migrations so `oauth_auth_codes`, `oauth_access_tokens`, and `oauth_device_codes.user_id` match your users primary key. Migrations read the type via `Crustum\Tessera\Support\UserId` (not the `Tessera` facade):

| Value | Column | Use when |
|-------|--------|----------|
| `integer` (default) | `integer` | Auto-increment integer user ids (Tessera's own tests) |
| `uuid` | `uuid` | UUID user ids (e.g. CakeDC/Users) |
| `string` | `string(255)` | Custom string identifiers |

```php
'userIdType' => 'uuid',
```

Changing `userIdType` after migrations have run requires altering or re-creating those tables.

### CakeDC Auth public routes

Tessera does not depend on CakeDC/Users. If your host uses CakeDC Auth RBAC, OAuth endpoints must be listed as public (`bypassAuth`) or anonymous clients cannot reach token / authorize / consent.

Merge the plugin config fragment into `config/permissions.php`:

```php
use Cake\Core\Plugin;

$permissions = array_merge(
    $permissions,
    require Plugin::path('Crustum/Tessera') . 'config' . DS . 'permissions.php',
);
```

The fragment lives in `config/permissions.php` inside the Tessera plugin (token, authorize, approve/deny, device endpoints). Rules already set `bypassAuth => true`.

`bypassAuth` only skips CakeDC's "must be logged in" gate for those routes. It does **not** make OAuth anonymous free access. `AccessToken::issueToken` still validates client credentials and the grant body (including PKCE and secrets where required). Authorization, Approve, and Deny still require a session identity plus the consent `auth_token`. `TransientToken::refresh` still requires an authenticated identity and a CSRF claim when minting cookies. Device controllers still bind device and user codes. Do not copy these rules into a blanket "all `Crustum/Tessera` = public" policy.

For authorize guests, Tessera redirects to `Tessera.loginUrl` (default `/login`) with `Tessera.loginRedirectQueryParam` (default `redirect`). Set `loginUrl` in host `config/tessera.php` if your login path differs (e.g. a CakeDC/Users named route array).

All of your application's Tessera configuration is stored in the `config/tessera.php` configuration file after a manifest install:

```php
return [
    'Tessera' => [
        'path' => 'oauth',
        'guard' => 'users',
        'middleware' => [],
        'usersTable' => 'Users',
        'userIdType' => 'integer',
        'provider' => null,
        'private_key' => env('TESSERA_PRIVATE_KEY'),
        'public_key' => env('TESSERA_PUBLIC_KEY'),
        'connection' => env('TESSERA_CONNECTION'),
        'TokenAuthenticator' => [
            'className' => \Crustum\Tessera\Authenticator\TokenAuthenticator::class,
            'providerName' => 'users',
        ],
    ],
];
```

Personal access clients are OAuth client rows (create via `bin/cake tessera client --personal` / factories), not secrets in `config/tessera.php`.
<a name="deploying-tessera"></a>
### Deploying Tessera

When deploying Tessera to your application's servers for the first time, you will typically need to run the `tessera keys` command. This command generates the encryption keys Tessera needs in order to generate access tokens. The generated keys are not typically kept in source control:

```bash
bin/cake tessera keys
```

If necessary, you may define the path where Tessera's keys should be loaded from. You may use the `Tessera::loadKeysFrom` method to accomplish this. Typically, this method should be called during application bootstrap:

```php
use Crustum\Tessera\Tessera;

Tessera::loadKeysFrom(CONFIG . 'oauth-keys');
```

You may force regeneration of existing keys with `bin/cake tessera keys --force`. The RSA key length defaults to 4096 bits and may be changed with `--length`.

<a name="loading-keys-from-the-environment"></a>
#### Loading Keys From the Environment

Alternatively, you may load your application's encryption keys by defining them as environment variables. The published `config/tessera.php` file already reads `TESSERA_PRIVATE_KEY` and `TESSERA_PUBLIC_KEY`:

```ini
TESSERA_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----
<private key here>
-----END RSA PRIVATE KEY-----"

TESSERA_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----
<public key here>
-----END PUBLIC KEY-----"
```

You may also write PEM contents into Configure at runtime:

```php
use Cake\Core\Configure;

Configure::write('Tessera.private_key', env('TESSERA_PRIVATE_KEY'));
Configure::write('Tessera.public_key', env('TESSERA_PUBLIC_KEY'));
```

<a name="configuration"></a>
## Configuration

<a name="token-lifetimes"></a>
### Token Lifetimes

By default, Tessera issues long-lived access tokens that expire after one year. If you would like to configure a longer / shorter token lifetime, you may use the `tokensExpireIn`, `refreshTokensExpireIn`, and `personalAccessTokensExpireIn` methods. These methods should typically be called from application bootstrap:

```php
use Crustum\Tessera\Tessera;
use DateInterval;

Tessera::tokensExpireIn(new DateInterval('P15D'));
Tessera::refreshTokensExpireIn(new DateInterval('P30D'));
Tessera::personalAccessTokensExpireIn(new DateInterval('P6M'));
```

Client credentials token lifetime may be customized independently with `Tessera::clientCredentialsTokensExpireIn()`.

> [!WARNING]
> The `expires` columns on Tessera's database tables are read-only and for display purposes only. When issuing tokens, Tessera stores the expiration information within the signed and encrypted tokens. If you need to invalidate a token you should [revoke it](#revoking-tokens).

<a name="overriding-default-models"></a>
### Overriding Default Models

You are free to extend the entities used internally by Tessera by defining your own entity and extending the corresponding Tessera entity:

```php
use Crustum\Tessera\Model\Entity\Client as TesseraClient;

class Client extends TesseraClient
{
    // ...
}
```

After defining your entity, you may instruct Tessera to use your custom model via the `Crustum\Tessera\Tessera` class. Typically, you should inform Tessera about your custom models during application bootstrap:

```php
use App\Model\Entity\OAuth\AuthCode;
use App\Model\Entity\OAuth\Client;
use App\Model\Entity\OAuth\DeviceCode;
use App\Model\Entity\OAuth\RefreshToken;
use App\Model\Entity\OAuth\Token;
use Crustum\Tessera\Tessera;

Tessera::useTokenModel(Token::class);
Tessera::useRefreshTokenModel(RefreshToken::class);
Tessera::useAuthCodeModel(AuthCode::class);
Tessera::useClientModel(Client::class);
Tessera::useDeviceCodeModel(DeviceCode::class);
```

<a name="overriding-routes"></a>
### Overriding Routes

Sometimes you may wish to customize the routes defined by Tessera. To achieve this, ignore the routes registered by Tessera by calling `Tessera::ignoreRoutes` during application bootstrap:

```php
use Crustum\Tessera\Tessera;

Tessera::ignoreRoutes();
```

Then register the OAuth endpoints you need in your application's `config/routes.php`, pointing at Tessera controllers under the `Crustum/Tessera` plugin namespace. By default Tessera mounts routes under `/oauth` (configurable via `Tessera.path` in `config/tessera.php`). The plugin registers named routes such as `tessera:token`, `tessera:authorizations.authorize`, `tessera:authorizations.approve`, and `tessera:authorizations.deny`. When device authorization is enabled, Tessera also registers `tessera:device`, `tessera:device.code`, and the device authorize / approve / deny routes.

<a name="authorization-code-grant"></a>
## Authorization Code Grant

Using OAuth2 via authorization codes is how most developers are familiar with OAuth2. When using authorization codes, a client application will redirect a user to your server where they will either approve or deny the request to issue an access token to the client.

To get started, we need to instruct Tessera how to return our "authorization" view.

All the authorization view's rendering logic may be customized using the appropriate methods available via the `Crustum\Tessera\Tessera` class. Typically, you should call this method from application bootstrap:

```php
use Crustum\Tessera\Tessera;

// By providing a template name...
Tessera::authorizationView('Tessera.Authorize/authorize');

// By providing a closure (arrays are returned as JSON by Tessera controllers)...
Tessera::authorizationView(function (array $parameters) {
    return [
        'request' => $parameters['request'],
        'authToken' => $parameters['authToken'],
        'client' => $parameters['client'],
        'user' => $parameters['user'],
        'scopes' => $parameters['scopes'],
    ];
});
```

Tessera will automatically define the `/oauth/authorize` route that returns this view. Your consent template should include a form that makes a POST request to the `tessera:authorizations.approve` route to approve the authorization and a form that makes a DELETE request to the `tessera:authorizations.deny` route to deny the authorization. The approve and deny routes expect `state`, `client_id`, and `auth_token` fields.

<a name="managing-clients"></a>
### Managing Clients

Developers building applications that need to interact with your application's API will need to register their application with yours by creating a "client". Typically, this consists of providing the name of their application and a URI that your application can redirect to after users approve their request for authorization.

<a name="managing-first-party-clients"></a>
#### First-Party Clients

The simplest way to create a client is using the `tessera client` console command. This command may be used to create first-party clients or to test your OAuth2 functionality. When you run the `tessera client` command, Tessera will prompt you for more information about your client and will provide you with a client ID and secret:

```bash
bin/cake tessera client
```

If you would like to allow multiple redirect URIs for your client, you may specify them using a comma-delimited list when prompted for the URI by the `tessera client` command, or via the `--redirect_uri` option. Any URIs which contain commas should be URI encoded:

```text
https://third-party-app.com/callback,https://example.com/oauth/redirect
```

You may also pass `--name`, `--provider`, and `--public` when you want a non-interactive create for a public (PKCE-capable) client.

<a name="managing-third-party-clients"></a>
#### Third-Party Clients

Since your application's users will not be able to utilize the `tessera client` command, you may use the `createAuthorizationCodeGrantClient` method of the `Crustum\Tessera\ClientRepository` class to register a client for a given user:

```php
use App\Model\Entity\User;
use Crustum\Tessera\ClientRepository;

$user = $this->fetchTable('Users')->get($userId);

$clients = new ClientRepository();

$client = $clients->createAuthorizationCodeGrantClient(
    'Example App',
    ['https://third-party-app.com/callback'],
    confidential: false,
    user: $user,
    enableDeviceFlow: true,
);

$userApps = $user->oauthApps()->all();
```

The `createAuthorizationCodeGrantClient` method returns an instance of `Crustum\Tessera\Model\Entity\Client`. You may display the `$client->id` as the client ID and `$client->plainSecret` as the client secret to the user. The plain secret is only available immediately after creation.

<a name="requesting-tokens"></a>
### Requesting Tokens

<a name="requesting-tokens-redirecting-for-authorization"></a>
#### Redirecting for Authorization

Once a client has been created, developers may use their client ID and secret to request an authorization code and access token from your application. First, the consuming application should make a redirect request to your application's `/oauth/authorize` route like so:

```php
use Cake\Http\Session;
use Cake\Utility\Security;

$session = $this->request->getSession();
$state = Security::randomString(40);
$session->write('oauth.state', $state);

$query = http_build_query([
    'client_id' => 'your-client-id',
    'redirect_uri' => 'https://third-party-app.com/callback',
    'response_type' => 'code',
    'scope' => 'user:read orders:create',
    'state' => $state,
]);

return $this->redirect('https://tessera-app.test/oauth/authorize?' . $query);
```

The `prompt` parameter may be used to specify the authentication behavior of the Tessera application.

If the `prompt` value is `none`, Tessera will always throw an authentication error if the user is not already authenticated with the Tessera application. If the value is `consent`, Tessera will always display the authorization approval screen, even if all scopes were previously granted to the consuming application. When the value is `login`, the Tessera application will always prompt the user to re-login to the application, even if they already have an existing session.

If no `prompt` value is provided, the user will be prompted for authorization only if they have not previously authorized access to the consuming application for the requested scopes.

> [!NOTE]
> Remember, the `/oauth/authorize` route is already defined by Tessera when plugin routes are enabled. You do not need to manually define this route unless you called `Tessera::ignoreRoutes()`.

<a name="approving-the-request"></a>
#### Approving the Request

When receiving authorization requests, Tessera will automatically respond based on the value of the `prompt` parameter (if present) and may display a template to the user allowing them to approve or deny the authorization request. If they approve the request, they will be redirected back to the `redirect_uri` that was specified by the consuming application. The `redirect_uri` must match a redirect URI that was registered when the client was created.

Sometimes you may wish to skip the authorization prompt, such as when authorizing a first-party client. You may accomplish this by [extending the `Client` entity](#overriding-default-models) and defining a `skipsAuthorization` method. If `skipsAuthorization` returns `true` the client will be approved and the user will be redirected back to the `redirect_uri` immediately, unless the consuming application has explicitly set the `prompt` parameter when redirecting for authorization:

```php
<?php
declare(strict_types=1);

namespace App\Model\Entity\OAuth;

use Crustum\Tessera\Model\Entity\Client as BaseClient;

class Client extends BaseClient
{
    /**
     * Determine if the client should skip the authorization prompt.
     *
     * @param object $user Authenticated user
     * @param list<\Crustum\Tessera\Scope> $scopes Requested scopes
     * @return bool
     */
    public function skipsAuthorization(object $user, array $scopes): bool
    {
        return $this->firstParty();
    }
}
```

<a name="requesting-tokens-converting-authorization-codes-to-access-tokens"></a>
#### Converting Authorization Codes to Access Tokens

If the user approves the authorization request, they will be redirected back to the consuming application. The consumer should first verify the `state` parameter against the value that was stored prior to the redirect. If the state parameter matches then the consumer should issue a `POST` request to your application to request an access token. The request should include the authorization code that was issued by your application when the user approved the authorization request:

```php
$state = $this->request->getSession()->consume('oauth.state');

if ($state === null || $state !== $this->request->getQuery('state')) {
    throw new \InvalidArgumentException('Invalid state value.');
}

$http = new \Cake\Http\Client();
$response = $http->post('https://tessera-app.test/oauth/token', [
    'grant_type' => 'authorization_code',
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'redirect_uri' => 'https://third-party-app.com/callback',
    'code' => $this->request->getQuery('code'),
]);

return $this->response->withType('application/json')
    ->withStringBody((string)$response->getBody());
```

This `/oauth/token` route will return a JSON response containing `access_token`, `refresh_token`, and `expires_in` attributes. The `expires_in` attribute contains the number of seconds until the access token expires.

> [!NOTE]
> Like the `/oauth/authorize` route, the `/oauth/token` route is defined for you by Tessera. There is no need to manually define this route unless you called `Tessera::ignoreRoutes()`.

<a name="managing-tokens"></a>
### Managing Tokens

You may retrieve a user's authorized tokens using the `tokens` method of the `HasApiTokensTrait` trait. For example, this may be used to offer your users a dashboard to keep track of their connections with third-party applications:

```php
use Cake\I18n\DateTime;
use Crustum\Tessera\Model\Entity\Token;

$user = $this->fetchTable('Users')->get($userId);

$tokens = $user->tokens()
    ->where([
        'Tokens.revoked' => false,
        'Tokens.expires >' => DateTime::now(),
    ])
    ->all();

$connections = collection($tokens)
    ->reject(fn (Token $token) => $token->client->firstParty())
    ->groupBy(fn (Token $token) => $token->client_id)
    ->map(function ($group) {
        $tokens = collection($group);

        return [
            'client' => $tokens->first()->client,
            'scopes' => $tokens->extract('scopes')->unfold()->unique()->toList(),
            'tokens_count' => $tokens->count(),
        ];
    })
    ->toList();
```

<a name="refreshing-tokens"></a>
### Refreshing Tokens

If your application issues short-lived access tokens, users will need to refresh their access tokens via the refresh token that was provided to them when the access token was issued:

```php
$http = new \Cake\Http\Client();
$response = $http->post('https://tessera-app.test/oauth/token', [
    'grant_type' => 'refresh_token',
    'refresh_token' => 'the-refresh-token',
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'scope' => 'user:read orders:create',
]);

return $response->getJson();
```

This `/oauth/token` route will return a JSON response containing `access_token`, `refresh_token`, and `expires_in` attributes. The `expires_in` attribute contains the number of seconds until the access token expires. The `client_secret` is required for confidential clients only.

<a name="revoking-tokens"></a>
### Revoking Tokens

You may revoke a token by using the `revoke` method on Tessera's tokens table. You may revoke a token's refresh token using the `revoke` method on the refresh tokens table:

```php
use Crustum\Tessera\Tessera;

$token = Tessera::tokensTable()->get($tokenId);
Tessera::tokensTable()->revoke($token);

$refresh = $token->get('refresh_token');
if ($refresh !== null) {
    Tessera::refreshTokensTable()->revoke($refresh);
}

$user = $this->fetchTable('Users')->get($userId);
foreach ($user->tokens() as $token) {
    Tessera::tokensTable()->revoke($token);
    $refresh = $token->get('refresh_token');
    if ($refresh !== null) {
        Tessera::refreshTokensTable()->revoke($refresh);
    }
}
```

<a name="purging-tokens"></a>
### Purging Tokens

When tokens have been revoked or expired, you might want to purge them from the database. Tessera's included `tessera purge` console command can do this for you:

```bash
# Purge revoked and expired tokens, auth codes, and device codes...
bin/cake tessera purge

# Only purge tokens expired for more than 6 hours...
bin/cake tessera purge --hours 6

# Only purge revoked tokens, auth codes, and device codes...
bin/cake tessera purge --revoked

# Only purge expired tokens, auth codes, and device codes...
bin/cake tessera purge --expired
```

By default the command purges both revoked items and items expired for more than 168 hours. Device authorization codes are included when device grants are enabled. You may also schedule the purge from your application's cron or Cake scheduler so tokens are pruned automatically.

<a name="code-grant-pkce"></a>
## Authorization Code Grant With PKCE

The Authorization Code grant with "Proof Key for Code Exchange" (PKCE) is a secure way to authenticate single page applications or mobile applications to access your API. This grant should be used when you can't guarantee that the client secret will be stored confidentially or in order to mitigate the threat of having the authorization code intercepted by an attacker. A combination of a "code verifier" and a "code challenge" replaces the client secret when exchanging the authorization code for an access token.

<a name="creating-a-auth-pkce-grant-client"></a>
### Creating the Client

Before your application can issue tokens via the authorization code grant with PKCE, you will need to create a PKCE-enabled client. You may do this using the `tessera client` console command with the `--public` option:

```bash
bin/cake tessera client --public
```

<a name="requesting-auth-pkce-grant-tokens"></a>
### Requesting Tokens

<a name="code-verifier-code-challenge"></a>
#### Code Verifier and Code Challenge

As this authorization grant does not provide a client secret, developers will need to generate a combination of a code verifier and a code challenge in order to request a token.

The code verifier should be a random string of between 43 and 128 characters containing letters, numbers, and `"-"`, `"."`, `"_"`, `"~"` characters, as defined in the [RFC 7636 specification](https://tools.ietf.org/html/rfc7636).

The code challenge should be a Base64 encoded string with URL and filename-safe characters. The trailing `'='` characters should be removed and no line breaks, whitespace, or other additional characters should be present.

```php
$encoded = base64_encode(hash('sha256', $codeVerifier, true));

$codeChallenge = strtr(rtrim($encoded, '='), '+/', '-_');
```

<a name="code-grant-pkce-redirecting-for-authorization"></a>
#### Redirecting for Authorization

Once a client has been created, you may use the client ID and the generated code verifier and code challenge to request an authorization code and access token from your application. First, the consuming application should make a redirect request to your application's `/oauth/authorize` route:

```php
use Cake\Utility\Security;

$session = $this->request->getSession();
$state = Security::randomString(40);
$codeVerifier = Security::randomString(128);
$session->write('oauth.state', $state);
$session->write('oauth.code_verifier', $codeVerifier);

$codeChallenge = strtr(rtrim(
    base64_encode(hash('sha256', $codeVerifier, true)),
    '='
), '+/', '-_');

$query = http_build_query([
    'client_id' => 'your-client-id',
    'redirect_uri' => 'https://third-party-app.com/callback',
    'response_type' => 'code',
    'scope' => 'user:read orders:create',
    'state' => $state,
    'code_challenge' => $codeChallenge,
    'code_challenge_method' => 'S256',
]);

return $this->redirect('https://tessera-app.test/oauth/authorize?' . $query);
```

<a name="code-grant-pkce-converting-authorization-codes-to-access-tokens"></a>
#### Converting Authorization Codes to Access Tokens

If the user approves the authorization request, they will be redirected back to the consuming application. The consumer should verify the `state` parameter against the value that was stored prior to the redirect, as in the standard Authorization Code Grant.

If the state parameter matches, the consumer should issue a `POST` request to your application to request an access token. The request should include the authorization code that was issued by your application when the user approved the authorization request along with the originally generated code verifier:

```php
$state = $this->request->getSession()->consume('oauth.state');
$codeVerifier = $this->request->getSession()->consume('oauth.code_verifier');

if ($state === null || $state !== $this->request->getQuery('state')) {
    throw new \InvalidArgumentException('Invalid state value.');
}

$http = new \Cake\Http\Client();
$response = $http->post('https://tessera-app.test/oauth/token', [
    'grant_type' => 'authorization_code',
    'client_id' => 'your-client-id',
    'redirect_uri' => 'https://third-party-app.com/callback',
    'code_verifier' => $codeVerifier,
    'code' => $this->request->getQuery('code'),
]);

return $response->getJson();
```

<a name="device-authorization-grant"></a>
## Device Authorization Grant

The OAuth2 device authorization grant allows browserless or limited input devices, such as TVs and game consoles, to obtain an access token by exchanging a "device code". When using device flow, the device client will instruct the user to use a secondary device, such as a computer or a smartphone and connect to your server where they will enter the provided "user code" and either approve or deny the access request.

To get started, we need to instruct Tessera how to return our "user code" and "authorization" views.

All the device view's rendering logic may be customized using the appropriate methods available via the `Crustum\Tessera\Tessera` class. Typically, you should call these methods from application bootstrap:

```php
use Crustum\Tessera\Tessera;

Tessera::deviceUserCodeView('Tessera.Device/user_code');
Tessera::deviceAuthorizationView('Tessera.Device/authorize');

Tessera::deviceUserCodeView(function (array $parameters) {
    return $parameters;
});

Tessera::deviceAuthorizationView(function (array $parameters) {
    return [
        'request' => $parameters['request'],
        'authToken' => $parameters['authToken'],
        'client' => $parameters['client'],
        'user' => $parameters['user'],
        'scopes' => $parameters['scopes'],
    ];
});
```

Tessera will automatically define routes that return these views when device authorization is enabled. Your user-code template should include a form that makes a GET request to the `tessera:device.authorizations.authorize` route. That route expects a `user_code` query parameter.

Your device authorization template should include a form that makes a POST request to the `tessera:device.authorizations.approve` route to approve the authorization and a form that makes a DELETE request to the `tessera:device.authorizations.deny` route to deny the authorization. The approve and deny routes expect `state`, `client_id`, and `auth_token` fields.

<a name="creating-a-device-authorization-grant-client"></a>
### Creating a Device Authorization Grant Client

Before your application can issue tokens via the device authorization grant, you will need to create a device flow enabled client. You may do this using the `tessera client` console command with the `--device` option. This command will create a first-party device flow enabled client and provide you with a client ID and secret:

```bash
bin/cake tessera client --device
```

Additionally, you may use the `createDeviceAuthorizationGrantClient` method on the `ClientRepository` class to register a third-party client that belongs to the given user:

```php
use Crustum\Tessera\ClientRepository;

$user = $this->fetchTable('Users')->get($userId);
$clients = new ClientRepository();

$client = $clients->createDeviceAuthorizationGrantClient(
    'Example Device',
    confidential: false,
    user: $user,
);
```

<a name="requesting-device-authorization-grant-tokens"></a>
### Requesting Tokens

<a name="device-code"></a>
#### Requesting a Device Code

Once a client has been created, developers may use their client ID to request a device code from your application. First, the consuming device should make a `POST` request to your application's `/oauth/device/code` route to request a device code:

```php
$http = new \Cake\Http\Client();
$response = $http->post('https://tessera-app.test/oauth/device/code', [
    'client_id' => 'your-client-id',
    'scope' => 'user:read orders:create',
]);

return $response->getJson();
```

This will return a JSON response containing `device_code`, `user_code`, `verification_uri`, `interval`, and `expires_in` attributes. The `expires_in` attribute contains the number of seconds until the device code expires. The `interval` attribute contains the number of seconds the consuming device should wait between requests when polling the `/oauth/token` route to avoid rate limit errors.

> [!NOTE]
> Remember, the `/oauth/device/code` route is already defined by Tessera when device authorization is enabled. You do not need to manually define this route unless you called `Tessera::ignoreRoutes()`.

<a name="user-code"></a>
#### Displaying the Verification URI and User Code

Once a device code request has been obtained, the consuming device should instruct the user to use another device and visit the provided `verification_uri` and enter the `user_code` in order to approve the authorization request.

<a name="polling-token-request"></a>
#### Polling Token Request

Since the user will be using a separate device to grant (or deny) access, the consuming device should poll your application's `/oauth/token` route to determine when the user has responded to the request. The consuming device should use the minimum polling `interval` provided in the JSON response when requesting a device code to avoid rate limit errors:

```php
$http = new \Cake\Http\Client();
$interval = 5;

do {
    sleep($interval);

    $response = $http->post('https://tessera-app.test/oauth/token', [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
        'client_id' => 'your-client-id',
        'client_secret' => 'your-client-secret',
        'device_code' => 'the-device-code',
    ]);

    $payload = $response->getJson();
    if (($payload['error'] ?? null) === 'slow_down') {
        $interval += 5;
    }
} while (in_array($payload['error'] ?? null, ['authorization_pending', 'slow_down'], true));

return $payload;
```

If the user has approved the authorization request, this will return a JSON response containing `access_token`, `refresh_token`, and `expires_in` attributes. The `expires_in` attribute contains the number of seconds until the access token expires. The `client_secret` is required for confidential clients only.

<a name="password-grant"></a>
## Password Grant

> [!WARNING]
> We no longer recommend using password grant tokens. Instead, you should choose [a grant type that is currently recommended by OAuth2 Server](https://oauth2.thephpleague.com/authorization-server/which-grant/).

The OAuth2 password grant allows your other first-party clients, such as a mobile application, to obtain an access token using an email address / username and password. This allows you to issue access tokens securely to your first-party clients without requiring your users to go through the entire OAuth2 authorization code redirect flow.

The password grant is disabled by default. To enable it, call the `enablePasswordGrant` method during application bootstrap:

```php
use Crustum\Tessera\Tessera;

Tessera::enablePasswordGrant();
```

<a name="creating-a-password-grant-client"></a>
### Creating a Password Grant Client

Before your application can issue tokens via the password grant, you will need to create a password grant client. You may do this using the `tessera client` console command with the `--password` option:

```bash
bin/cake tessera client --password
```

<a name="requesting-password-grant-tokens"></a>
### Requesting Tokens

Once you have enabled the grant and have created a password grant client, you may request an access token by issuing a `POST` request to the `/oauth/token` route with the user's email address and password. Remember, this route is already registered by Tessera so there is no need to define it manually. If the request is successful, you will receive an `access_token` and `refresh_token` in the JSON response from the server:

```php
$http = new \Cake\Http\Client();
$response = $http->post('https://tessera-app.test/oauth/token', [
    'grant_type' => 'password',
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'username' => 'taylor@example.com',
    'password' => 'my-password',
    'scope' => 'user:read orders:create',
]);

return $response->getJson();
```

> [!NOTE]
> Remember, access tokens are long-lived by default. However, you are free to [configure your maximum access token lifetime](#configuration) if needed.

<a name="requesting-all-scopes"></a>
### Requesting All Scopes

When using the password grant or client credentials grant, you may wish to authorize the token for all of the scopes supported by your application. You can do this by requesting the `*` scope. If you request the `*` scope, the `can` method on the token instance will always return `true`. This scope may only be assigned to a token that is issued using the `password` or `client_credentials` grant:

```php
$http = new \Cake\Http\Client();
$response = $http->post('https://tessera-app.test/oauth/token', [
    'grant_type' => 'password',
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'username' => 'taylor@example.com',
    'password' => 'my-password',
    'scope' => '*',
]);
```

<a name="customizing-the-user-provider"></a>
### Customizing the User Provider

If your application authenticates more than one type of user, you may specify which provider the password grant client uses by providing a `--provider` option when creating the client via `bin/cake tessera client --password`. The given provider name should match the provider name you configure for `TokenAuthenticator` and related Tessera settings. You can then [protect your routes using middleware](#via-middleware) so only tokens issued for that provider are accepted for a given API surface.

<a name="customizing-the-username-field"></a>
### Customizing the Username Field

When authenticating using the password grant, Tessera resolves users through your configured users table and authentication identifiers. You may customize lookup by defining a `findForTessera` method on your users table, or a combined `findAndValidateForTessera` method when you want to resolve and validate credentials in one place:

```php
<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Crustum\Tessera\Bridge\Client as BridgeClient;
use League\OAuth2\Server\Entities\ClientEntityInterface;

class UsersTable extends Table
{
    /**
     * Find the user instance for the given username.
     *
     * @param string $username Username from the token request
     * @param \League\OAuth2\Server\Entities\ClientEntityInterface $client OAuth client
     * @return \App\Model\Entity\User|null
     */
    public function findForTessera(string $username, ClientEntityInterface $client)
    {
        return $this->find()
            ->where(['username' => $username])
            ->first();
    }
}
```

<a name="customizing-the-password-validation"></a>
### Customizing the Password Validation

When authenticating using the password grant, Tessera will use the `password` attribute of your user entity to validate the given password via the password hasher. If your entity does not have a `password` attribute or you wish to customize the password validation logic, you can define a `validateForTesseraPasswordGrant` method on your user entity:

```php
<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\ORM\Entity;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Trait\HasApiTokensTrait;

class User extends Entity implements OAuthenticatable
{
    use HasApiTokensTrait;

    /**
     * Validate the password of the user for the Tessera password grant.
     *
     * @param string $password Plain password from the token request
     * @return bool
     */
    public function validateForTesseraPasswordGrant(string $password): bool
    {
        return (new DefaultPasswordHasher())->check($password, (string)$this->password);
    }

    public function getIdentifier(): array|string|int|null
    {
        return $this->id;
    }
}
```

<a name="implicit-grant"></a>
## Implicit Grant

> [!WARNING]
> We no longer recommend using implicit grant tokens. Instead, you should choose [a grant type that is currently recommended by OAuth2 Server](https://oauth2.thephpleague.com/authorization-server/which-grant/).

The implicit grant is similar to the authorization code grant; however, the token is returned to the client without exchanging an authorization code. This grant is most commonly used for JavaScript or mobile applications where the client credentials can't be securely stored. The implicit grant is disabled by default. To enable it, call the `enableImplicitGrant` method during application bootstrap:

```php
use Crustum\Tessera\Tessera;

Tessera::enableImplicitGrant();
```

Before your application can issue tokens via the implicit grant, you will need to create an implicit grant client. You may do this using the `tessera client` console command with the `--implicit` option:

```bash
bin/cake tessera client --implicit
```

Once the grant has been enabled and an implicit client has been created, developers may use their client ID to request an access token from your application. The consuming application should make a redirect request to your application's `/oauth/authorize` route like so:

```php
use Cake\Utility\Security;

$session = $this->request->getSession();
$state = Security::randomString(40);
$session->write('oauth.state', $state);

$query = http_build_query([
    'client_id' => 'your-client-id',
    'redirect_uri' => 'https://third-party-app.com/callback',
    'response_type' => 'token',
    'scope' => 'user:read orders:create',
    'state' => $state,
]);

return $this->redirect('https://tessera-app.test/oauth/authorize?' . $query);
```

> [!NOTE]
> Remember, the `/oauth/authorize` route is already defined by Tessera. You do not need to manually define this route unless you called `Tessera::ignoreRoutes()`.

<a name="client-credentials-grant"></a>
## Client Credentials Grant

The client credentials grant is suitable for machine-to-machine authentication. For example, you might use this grant in a scheduled job which is performing maintenance tasks over an API.

Before your application can issue tokens via the client credentials grant, you will need to create a client credentials grant client. You may do this using the `--client` option of the `tessera client` console command:

```bash
bin/cake tessera client --client
```

Next, assign `EnsureClientIsResourceOwnerMiddleware` to your host API routes. Resolve League's `ResourceServer` from the application container inside `Application::routes()` (or an equivalent place that has `$this->getContainer()`). Cake builds the container lazily on the first `getContainer()` call: that build runs your application's `services()` method and every loaded plugin's `services()` — including `TesseraPlugin`, which registers `ResourceServer` — before returning the container. You do not need `routes()` to run after a separate earlier `services()` phase.

The string values passed to the middleware constructor are OAuth scope names you define with `Tessera::tokensCan()` (for example `servers:read`). They are not Cake route names and are not Laravel-specific syntax.

```php
// In src/Application.php
use Crustum\Tessera\Middleware\EnsureClientIsResourceOwnerMiddleware;
use League\OAuth2\Server\ResourceServer;

public function routes(\Cake\Routing\RouteBuilder $routes): void
{
    parent::routes($routes);

    $server = $this->getContainer()->get(ResourceServer::class);

    $routes->registerMiddleware(
        'tessera.client_owner',
        new EnsureClientIsResourceOwnerMiddleware($server, ['servers:read', 'servers:create']),
    );

    $routes->scope('/api', function (\Cake\Routing\RouteBuilder $builder): void {
        $builder->applyMiddleware('tessera.client_owner');
        $builder->connect('/orders', ['controller' => 'Orders', 'action' => 'index']);
        $builder->connect('/servers', ['controller' => 'Servers', 'action' => 'index']);
    });
}
```

> [!WARNING]
> The [underlying OAuth2 server](https://oauth2.thephpleague.com/database-setup/#:~:text=Please%20note%20that,the%20bearer%20token.) sets the token's `sub` claim to the client's identifier for client credentials tokens. Tessera client identifiers are UUIDs by default, so this cannot collide with a user's integer primary key. If you change client identifier generation, be careful that a client credentials token cannot inadvertently resolve a user whose ID matches the client's ID.

<a name="retrieving-tokens"></a>
### Retrieving Tokens

To retrieve a token using this grant type, make a request to the `/oauth/token` endpoint:

```php
$http = new \Cake\Http\Client();
$response = $http->post('https://tessera-app.test/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'scope' => 'servers:read servers:create',
]);

return $response->getJson()['access_token'];
```

<a name="personal-access-tokens"></a>
## Personal Access Tokens

Sometimes, your users may want to issue access tokens to themselves without going through the typical authorization code redirect flow. Allowing users to issue tokens to themselves via your application's UI can be useful for allowing users to experiment with your API or may serve as a simpler approach to issuing access tokens in general.

<a name="creating-a-personal-access-client"></a>
### Creating a Personal Access Client

Before your application can issue personal access tokens, you will need to create a personal access client. You may do this by executing the `tessera client` console command with the `--personal` option:

```bash
bin/cake tessera client --personal
```

<a name="customizing-the-user-provider-for-pat"></a>
### Customizing the User Provider

If your application authenticates more than one type of user, you may specify which provider the personal access grant client uses by providing a `--provider` option when creating the client via `bin/cake tessera client --personal`. The given provider name should match the provider name you configure for Tessera and `TokenAuthenticator`.

<a name="managing-personal-access-tokens"></a>
### Managing Personal Access Tokens

Once you have created a personal access client, you may issue tokens for a given user using the `createToken` method on the user entity. The `createToken` method accepts the name of the token as its first argument and an optional array of [scopes](#token-scopes) as its second argument:

```php
use Cake\I18n\DateTime;
use Crustum\Tessera\Model\Entity\Token;

$user = $this->fetchTable('Users')->get($userId);

$result = $user->createToken('My Token');
$jwt = $result->accessToken;
$tokenEntity = $result->getToken();

$result = $user->createToken('My Token', ['user:read', 'orders:create']);
$jwt = $result->accessToken;

$result = $user->createToken('My Token', ['*']);
$jwt = $result->accessToken;

$tokens = $user->tokens()
    ->contain(['Clients'])
    ->where([
        'Tokens.revoked' => false,
        'Tokens.expires >' => DateTime::now(),
    ])
    ->all()
    ->filter(fn (Token $token) => $token->client->hasGrantType('personal_access'));
```

<a name="protecting-routes"></a>
## Protecting Routes

<a name="via-middleware"></a>
### Via Middleware

Tessera authenticates API requests through `TokenAuthenticator` in your host `AuthenticationService`. Once the authenticator is registered, any route that goes through CakePHP authentication will receive a validated Bearer token identity when present.

Scope enforcement is handled by PSR-15 middleware that you register as named CakePHP route middleware. Pass the required OAuth scope names as constructor arguments when you register the middleware. Resolve `ResourceServer` with `$this->getContainer()->get(ResourceServer::class)` from `Application::routes()` — see [Client Credentials Grant](#client-credentials-grant) for why that call is safe even though `services()` is not invoked as a separate earlier step.

You typically register a small number of named middleware aliases for common scope sets, then apply them once to a route scope so many host API endpoints share the same gate:

```php
// In src/Application.php
use Crustum\Tessera\Middleware\CheckTokenMiddleware;
use League\OAuth2\Server\ResourceServer;

public function routes(\Cake\Routing\RouteBuilder $routes): void
{
    parent::routes($routes);

    $server = $this->getContainer()->get(ResourceServer::class);

    $routes->registerMiddleware(
        'tessera.check_orders',
        new CheckTokenMiddleware($server, ['orders:read', 'orders:create']),
    );

    $routes->scope('/api', function (\Cake\Routing\RouteBuilder $builder): void {
        $builder->applyMiddleware('tessera.check_orders');
        $builder->connect('/orders', ['controller' => 'Orders', 'action' => 'index']);
        $builder->connect('/orders/{id}', ['controller' => 'Orders', 'action' => 'view'])
            ->setPatterns(['id' => '\d+']);
    });
}
```

> [!WARNING]
> If you are using the [client credentials grant](#client-credentials-grant), you should use [`EnsureClientIsResourceOwnerMiddleware`](#client-credentials-grant) to protect your routes instead of relying only on a user-oriented authentication check.

<a name="multiple-authentication-providers"></a>
#### Multiple Authentication Providers

If your application authenticates different types of users that use entirely different ORM models, you will likely need to configure more than one Tessera provider and corresponding `TokenAuthenticator` wiring. This allows you to protect requests intended for specific user providers. For example, you may register one authenticator with `providerName` set to `users` and another with `providerName` set to `customers`, then attach different named scope middleware to the routes that serve each API surface.

When creating password or personal access clients for a non-default provider, pass `--provider` to `bin/cake tessera client` so issued tokens are associated with the correct provider.

<a name="passing-the-access-token"></a>
### Passing the Access Token

When calling routes that are protected by Tessera, your application's API consumers should specify their access token as a `Bearer` token in the `Authorization` header of their request:

```bash
curl --request GET \
    --url 'https://tessera-app.test/api/user' \
    --header 'Accept: application/json' \
    --header 'Authorization: Bearer <access-token>'
```

```php
$http = new \Cake\Http\Client();
$response = $http->get('https://tessera-app.test/api/user', [], [
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $accessToken,
    ],
]);

return $response->getJson();
```

<a name="token-scopes"></a>
## Token Scopes

Scopes allow your API clients to request a specific set of permissions when requesting authorization to access an account. For example, if you are building an e-commerce application, not all API consumers will need the ability to place orders. Instead, you may allow the consumers to only request authorization to access order shipment statuses. In other words, scopes allow your application's users to limit the actions a third-party application can perform on their behalf.

<a name="defining-scopes"></a>
### Defining Scopes

You may define your API's scopes using the `Tessera::tokensCan` method during application bootstrap. The `tokensCan` method accepts an array of scope names and scope descriptions. The scope description may be anything you wish and will be displayed to users on the authorization approval screen:

```php
use Crustum\Tessera\Tessera;

Tessera::tokensCan([
    'user:read' => 'Retrieve the user info',
    'orders:create' => 'Place orders',
    'orders:read:status' => 'Check order status',
]);
```

<a name="default-scope"></a>
### Default Scope

If a client does not request any specific scopes, you may configure your Tessera server to attach default scopes to the token using the `defaultScopes` method. Typically, you should call this method from application bootstrap:

```php
use Crustum\Tessera\Tessera;

Tessera::tokensCan([
    'user:read' => 'Retrieve the user info',
    'orders:create' => 'Place orders',
    'orders:read:status' => 'Check order status',
]);

Tessera::defaultScopes([
    'user:read',
    'orders:create',
]);
```

<a name="assigning-scopes-to-tokens"></a>
### Assigning Scopes to Tokens

<a name="when-requesting-authorization-codes"></a>
#### When Requesting Authorization Codes

When requesting an access token using the authorization code grant, consumers should specify their desired scopes as the `scope` query string parameter. The `scope` parameter should be a space-delimited list of scopes:

```php
$query = http_build_query([
    'client_id' => 'your-client-id',
    'redirect_uri' => 'https://third-party-app.com/callback',
    'response_type' => 'code',
    'scope' => 'user:read orders:create',
]);

return $this->redirect('https://tessera-app.test/oauth/authorize?' . $query);
```

<a name="when-issuing-personal-access-tokens"></a>
#### When Issuing Personal Access Tokens

If you are issuing personal access tokens using the user entity's `createToken` method, you may pass the array of desired scopes as the second argument to the method:

```php
$result = $user->createToken('My Token', ['orders:create']);
$jwt = $result->accessToken;
```

<a name="checking-scopes"></a>
### Checking Scopes

Tessera includes middleware that may be used to verify that an incoming request is authenticated with a token that has been granted a given scope.

<a name="check-for-all-scopes"></a>
#### Check For All Scopes

The `CheckTokenMiddleware` may be assigned to a route to verify that the incoming request's access token has all the listed scopes. Register it once in `Application::routes()`, then attach the alias to a route or an entire `/api` scope:

```php
$server = $this->getContainer()->get(\League\OAuth2\Server\ResourceServer::class);

$routes->registerMiddleware(
    'tessera.check_orders_all',
    new \Crustum\Tessera\Middleware\CheckTokenMiddleware($server, ['orders:read', 'orders:create']),
);

$routes->connect('/orders', ['controller' => 'Orders', 'action' => 'index'])
    ->setMiddleware(['tessera.check_orders_all']);
```

<a name="check-for-any-scopes"></a>
#### Check for Any Scopes

The `CheckTokenForAnyScopeMiddleware` may be assigned to a route to verify that the incoming request's access token has *at least one* of the listed scopes:

```php
$server = $this->getContainer()->get(\League\OAuth2\Server\ResourceServer::class);

$routes->registerMiddleware(
    'tessera.check_orders_any',
    new \Crustum\Tessera\Middleware\CheckTokenForAnyScopeMiddleware($server, ['orders:read', 'orders:create']),
);

$routes->connect('/orders', ['controller' => 'Orders', 'action' => 'index'])
    ->setMiddleware(['tessera.check_orders_any']);
```

<a name="checking-scopes-on-a-token-instance"></a>
#### Checking Scopes on a Token Instance

Once `TokenAuthenticator` has attached an access token to the authenticated user, you may determine if the token has a given scope using the `tokenCan` and `tokenCant` methods:

```php
if ($user->tokenCan('orders:create')) {
    // ...
}

if ($user->tokenCant('orders:create')) {
    // ...
}
```

> [!IMPORTANT]
> For SPA cookie authentication, the attached token is transient and **always** authorizes every scope. See [Cookie Auth and Scopes](#spa-cookie-scopes). Scope checks are meaningful for Bearer / personal access tokens, not for the first-party API cookie.

You may also inspect the current access token instance directly:

```php
$token = $user->currentAccessToken();

if ($token !== null && $token->can('orders:read')) {
    // ...
}

if ($token !== null && $token->cant('orders:create')) {
    // ...
}
```

<a name="additional-scope-methods"></a>
#### Additional Scope Methods

Tessera also provides helpers for working with the defined scope catalog:

```php
use Crustum\Tessera\Tessera;

$ids = Tessera::scopeIds();
$scopes = Tessera::scopes();
$hasOrders = Tessera::hasScope('orders:create');
$valid = Tessera::validScopes(['orders:create', 'unknown']);
```

<a name="spa-authentication"></a>
## SPA Authentication

When building an API, it can be extremely useful to be able to consume your own API from your JavaScript application. This approach to API development allows your own application to consume the same API that you are sharing with the world. The same API may be consumed by your web application, mobile applications, third-party applications, and any SDKs that you may publish on various package managers.

Typically, if you want to consume your API from your JavaScript application, you would need to manually send an access token to the application and pass it with each request to your application. However, Tessera includes middleware that can handle this for you. All you need to do is append the `CreateFreshApiTokenMiddleware` to the appropriate middleware queue or route scope in your CakePHP application:

```php
use Crustum\Tessera\Middleware\CreateFreshApiTokenMiddleware;

$middlewareQueue->add(new CreateFreshApiTokenMiddleware());
```

> [!WARNING]
> You should ensure that the `CreateFreshApiTokenMiddleware` is the last middleware that can still attach cookies to the response before the response is sent.

CakePHP's CSRF middleware must run earlier so the `csrfToken` request attribute is set. If that attribute is missing or empty, Tessera **will not** mint an API token cookie (and `TransientToken::refresh` fails closed). Do not treat an empty CSRF claim as acceptable.

This middleware will attach a `tessera_token` cookie to your outgoing responses. This cookie contains an encrypted JWT that Tessera will use to authenticate API requests from your JavaScript application. Now, since the browser will automatically send the cookie with all subsequent requests, you may make requests to your application's API without explicitly passing an access token:

```js
fetch('/api/user', {
    credentials: 'same-origin',
    headers: {
        'Accept': 'application/json',
        'X-CSRF-Token': csrfToken,
    },
}).then((response) => response.json())
    .then((data) => {
        console.log(data);
    });
```

<a name="spa-cookie-scopes"></a>
### Cookie Auth and Scopes (Hard Invariant)

A cookie-authenticated first-party identity uses a **transient** token. That token's `can()` / `tokenCan()` checks **always succeed** for every scope — the SPA cookie is treated as full first-party API privilege (same model as the upstream OAuth server Tessera follows).

XSS or a CSRF failure on the first-party origin can therefore reach any API route that accepts cookie auth. Do **not** rely on `CheckTokenMiddleware` or `tokenCan()` to limit privilege for cookie-authenticated requests. Prefer Bearer access tokens or personal access tokens for high-privilege admin APIs; keep cookie SPA auth on a reduced surface, or add host policies that reject transient tokens via `$token->transient()`. Keep CSRF middleware enabled and never call `Tessera::ignoreCsrfToken()` in production.

<a name="customizing-the-cookie-name"></a>
### Customizing the Cookie Name

If needed, you can customize the `tessera_token` cookie's name using the `Tessera::cookie` method. Typically, this method should be called from application bootstrap:

```php
use Crustum\Tessera\Tessera;

Tessera::cookie('custom_name');
```

<a name="csrf-protection"></a>
### CSRF Protection

When using this method of authentication, you will need to ensure a valid CSRF token header is included in your requests. CakePHP's CSRF middleware provides the token your frontend should send on same-origin requests.

> [!NOTE]
> Only call `Tessera::ignoreCsrfToken()` when you understand the security implications. Ignoring CSRF for cookie-based SPA authentication weakens the protection this flow relies on. Do **not** enable it in production.

<a name="events"></a>
## Events

Tessera dispatches CakePHP events when issuing access tokens and refresh tokens. You may listen for these events with `EventManager` to prune or revoke other access tokens in your database:

| Event class | Event name |
| --- | --- |
| `Crustum\Tessera\Event\AccessTokenCreatedEvent` | `Tessera.AccessToken.created` |
| `Crustum\Tessera\Event\AccessTokenRevokedEvent` | `Tessera.AccessToken.revoked` |
| `Crustum\Tessera\Event\RefreshTokenCreatedEvent` | `Tessera.RefreshToken.created` |

```php
use Cake\Event\EventManager;
use Crustum\Tessera\Event\AccessTokenCreatedEvent;

EventManager::instance()->on(
    AccessTokenCreatedEvent::NAME,
    function (AccessTokenCreatedEvent $event): void {
        // ...
    },
);
```

<a name="testing"></a>
## Testing

Enable Feature-test stubs once in your test bootstrap (the plugin's PHPUnit bootstrap already does this):

```php
use Crustum\Tessera\Tessera;

Tessera::allowActingAs(true);
```

Never call `Tessera::allowActingAs(true)` in production. When `debug` is false, plugin bootstrap clears stubs and disables actingAs. On long-lived workers, always `Tessera::clearActingAs()` after tests or tinker sessions.

Tessera's `actingAs` method may be used to specify the currently authenticated user as well as its scopes. The first argument given to the `actingAs` method is the user instance and the second is an array of scopes that should be granted to the user's token:

```php
use App\Model\Entity\User;
use Crustum\Tessera\Tessera;

public function testOrdersCanBeCreated(): void
{
    Tessera::actingAs(
        $this->fetchTable('Users')->get(1),
        ['orders:create'],
    );

    $this->post('/api/orders');

    $this->assertResponseCode(201);
}
```

Tessera's `actingAsClient` method may be used to specify the currently authenticated client as well as its scopes. The first argument given to the `actingAsClient` method is the client instance and the second is an array of scopes that should be granted to the client's token:

```php
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;

public function testServersCanBeRetrieved(): void
{
    Tessera::actingAsClient(
        $this->getTableLocator()->get('Tessera.Clients')->get($clientId),
        ['servers:read'],
    );

    $this->get('/api/servers');

    $this->assertResponseCode(200);
}
```

You may inspect the stubbed client with `Tessera::tokenClient()` and should always clear stubs in tearDown with `Tessera::clearActingAs()`. Feature tests can extend the plugin's `TesseraTestCase`, which bootstraps keys, TestApp wiring, and empty OAuth fixtures through CakePHP's `PHPUnitExtension`.

`Tessera::actingAs()` / `actingAsClient()` throw unless `Tessera::allowActingAs(true)` was called. Authenticator and token middleware ignore leftover static stubs when actingAs is not allowed.

Console commands use space-separated names rather than colons: `tessera keys`, `tessera hash`, `tessera purge`, and `tessera client`. Client secrets may be hashed in place with `bin/cake tessera hash` after you decide to store only hashed secrets.
