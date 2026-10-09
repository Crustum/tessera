# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-10-09

### Added

- HTTP-integration coverage: token guard (`TokenGuardTest`, `/guard` + `/whoami` probe routes), fresh-cookie issuance (`CreateFreshApiTokenTest`, `/fresh*` routes), transient-token refresh over HTTP, DB-backed bridge client repository matrix, bridge event payload assertions, grant-level purge regression, malformed-UUID client cases
- `Client::isValidUniqueId()` model hook for client identifier validation (UUID by default, overridable for custom key types via `Tessera::useClientModel()`)
- `CsrfHeaderMiddleware` test helper mapping the CSRF header to the `csrfToken` request attribute
- `testClientCredentialsTokensExpireInFallsBackToTokensExpireIn` TTL fallback test
- `Support\AuthenticationCompat::passwordIdentifier()` / `::identifier()` helper building version-correct identifier config for cakephp/authentication 3 and 4

### Changed

- `ClientRepository::find()` validates identifiers through the client model instead of a hardcoded UUID check; malformed IDs return null without querying when unique-ID clients are enabled
- `Tessera::clientCredentialsTokensExpireIn()` is now non-nullable and falls back to `tokensExpireIn()`; the service provider no longer applies its own fallback
- Purge keeps access tokens linked to active refresh tokens
- Client lookups support the legacy `user_id` column alongside `owner_id` (owner columns by default, with explicit legacy-column HTTP cases)
- `CreateFreshApiTokenMiddleware` skips redirect responses and unwraps identity objects, so session users receive cookies over HTTP
- `Tessera::useClientModel()` now affects table reads (`ClientsTable` entity class), fixing model-override authorization checks
- Test stack migrated from Mockery to `jasonmccreary/double` (`VerifiesDoubles` on the base case, real objects instead of partial doubles, controller unit stubs consolidated into feature HTTP tests)
- Service-provider / keys tests pass explicit RSA-4096 arguments to `openssl_pkey_new()`
- `phpseclib/phpseclib` upgraded to `^4.0` (keys command uses the v4 namespace)
- `cakephp/authentication` supported versions widened to `^3.0 | ^4.0`; `TokenAuthenticator` accepts a null identifier

## [1.0.0]

### Added

- Full OAuth2 authorization server for CakePHP 5 (`crustum/tessera`) built on League OAuth2 Server with cakephp/authentication integration
- Plugin Manifest install for `config/tessera.php`, OAuth migrations, and bootstrap wiring via `bin/cake manifest install --plugin Crustum/Tessera`
- Encryption key management with `bin/cake tessera keys` (force regenerate, configurable RSA length) and optional PEM loading from environment or Configure
- User entity support via `HasApiTokensTrait` and `OAuthenticatable` for inspecting the current access token and scopes
- `TokenAuthenticator` for Bearer access-token authentication in the host AuthenticationService
- Authorization Code Grant with first-party and third-party client management, authorize/approve/deny flow, refresh, revoke, and purge
- Authorization Code Grant with PKCE (code verifier / code challenge) for public clients
- Device Authorization Grant with device code request, verification URI display, and token polling
- Password Grant with customizable user provider, username field, and password validation
- Implicit Grant support for browser-based clients
- Client Credentials Grant for machine-to-machine token retrieval
- Personal Access Tokens with dedicated client setup and token create/list/revoke helpers
- Route protection via authentication middleware and documented Bearer token passing
- Token scopes: define scopes, default scope, assign on authorize/PAT issue, and check with token token helpers (`tokenCan`, any/all scope checks)
- SPA authentication cookie flow with configurable cookie name and CSRF considerations
- Configurable token lifetimes (`tokensExpireIn`, `refreshTokensExpireIn`, `personalAccessTokensExpireIn`) plus model and route overrides
- CakePHP events for access token created/revoked and refresh token created
- Testing helpers `Tessera::allowActingAs()`, `Tessera::actingAs()`, `Tessera::actingAsClient()`, `Tessera::tokenClient()`, `Tessera::clearActingAs()`, and `TesseraTestCase`
- Console commands `tessera keys`, `tessera hash`, `tessera purge`, and `tessera client`
- Optional CakeDC Auth public-route fragment (`config/permissions.php`) for OAuth endpoints when using CakeDC RBAC
- Comprehensive documentation covering installation, grants, protecting routes, scopes, SPA auth, events, and testing
