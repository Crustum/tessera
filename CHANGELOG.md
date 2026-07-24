# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
