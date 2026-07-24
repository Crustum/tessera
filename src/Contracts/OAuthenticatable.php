<?php
declare(strict_types=1);

namespace Crustum\Tessera\Contracts;

use Crustum\Tessera\PersonalAccessTokenResult;

/**
 * Host user that can own OAuth clients and access tokens.
 *
 * Host identity comes from cakephp/authentication.
 */
interface OAuthenticatable
{
    /**
     * Primary identity key for the user.
     *
     * @return array|string|int|null
     */
    public function getIdentifier(): array|string|int|null;

    /**
     * Get all the user's registered OAuth applications.
     *
     * @return iterable
     */
    public function oauthApps(): iterable;

    /**
     * Get all the access tokens for the user.
     *
     * @return iterable
     */
    public function tokens(): iterable;

    /**
     * Determine if the current API token has a given scope.
     *
     * @param string $scope Scope name
     * @return bool
     */
    public function tokenCan(string $scope): bool;

    /**
     * Determine if the current API token is missing a given scope.
     *
     * @param string $scope Scope name
     * @return bool
     */
    public function tokenCant(string $scope): bool;

    /**
     * Create a new personal access token for the user.
     *
     * @param string $name Token name
     * @param list<string> $scopes Scopes
     * @return \Crustum\Tessera\PersonalAccessTokenResult
     */
    public function createToken(string $name, array $scopes = []): PersonalAccessTokenResult;

    /**
     * Get the access token currently associated with the user.
     *
     * @return \Crustum\Tessera\Contracts\ScopeAuthorizable|null
     */
    public function currentAccessToken(): ?ScopeAuthorizable;

    /**
     * Set the current access token for the user.
     *
     * @param \Crustum\Tessera\Contracts\ScopeAuthorizable|null $accessToken Access token
     * @return static
     */
    public function withAccessToken(?ScopeAuthorizable $accessToken): static;

    /**
     * Get the authentication provider / identifier name for this user type.
     *
     * @return string
     */
    public function getProviderName(): string;
}
