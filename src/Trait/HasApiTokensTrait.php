<?php
declare(strict_types=1);

namespace Crustum\Tessera\Trait;

use Cake\Core\Configure;
use Cake\ORM\Query\SelectQuery;
use Crustum\Tessera\Contracts\ScopeAuthorizable;
use Crustum\Tessera\PersonalAccessTokenResult;
use Crustum\Tessera\Support\UserId;
use Crustum\Tessera\Tessera;
use LogicException;

/**
 * Adds OAuth client / token helpers to a host user entity.
 *
 * @phpstan-require-implements \Crustum\Tessera\Contracts\OAuthenticatable
 */
trait HasApiTokensTrait
{
    /**
     * @var \Crustum\Tessera\Contracts\ScopeAuthorizable|null
     */
    protected ?ScopeAuthorizable $accessToken = null;

    /**
     * Get all of the user's registered OAuth applications.
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function oauthApps(): SelectQuery
    {
        return Tessera::clientsTable()->find()
            ->where([
                'owner_id' => $this->getIdentifier(),
                'owner_type' => static::class,
            ]);
    }

    /**
     * Get all of the access tokens for the user.
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function tokens(): SelectQuery
    {
        $provider = $this->getProviderName();
        $defaultProvider = Configure::read('Tessera.provider')
            ?? Configure::read('Tessera.usersTable')
            ?? 'Users';

        $query = Tessera::tokensTable()->find()
            ->contain(['Clients'])
            ->where([
                'Tokens.user_id' => UserId::normalize($this->getIdentifier()),
            ]);

        $query->matching('Clients', function (SelectQuery $q) use ($provider, $defaultProvider): SelectQuery {
            if ($provider === $defaultProvider) {
                return $q->where([
                    'OR' => [
                        ['Clients.provider IS' => null],
                        ['Clients.provider' => $provider],
                    ],
                ]);
            }

            return $q->where(['Clients.provider' => $provider]);
        });

        return $query;
    }

    /**
     * Get the access token currently associated with the user.
     *
     * @return \Crustum\Tessera\Contracts\ScopeAuthorizable|null
     */
    public function currentAccessToken(): ?ScopeAuthorizable
    {
        return $this->accessToken;
    }

    /**
     * Determine if the current API token has a given scope.
     *
     * @param string $scope Scope name
     * @return bool
     */
    public function tokenCan(string $scope): bool
    {
        return $this->accessToken !== null && $this->accessToken->can($scope);
    }

    /**
     * Determine if the current API token is missing a given scope.
     *
     * @param string $scope Scope name
     * @return bool
     */
    public function tokenCant(string $scope): bool
    {
        return !$this->tokenCan($scope);
    }

    /**
     * Create a new personal access token for the user.
     *
     * @param string $name Token name
     * @param list<string> $scopes Scopes
     * @return \Crustum\Tessera\PersonalAccessTokenResult
     */
    public function createToken(string $name, array $scopes = []): PersonalAccessTokenResult
    {
        return Tessera::personalAccessTokenFactory()->make(
            $this->getIdentifier() ?? '',
            $name,
            $scopes,
            $this->getProviderName(),
        );
    }

    /**
     * Resolve the Tessera provider name for this user class.
     *
     * Reads `Tessera.guards` / `Tessera.providers` from Configure.
     *
     * @return string
     * @throws \LogicException When no matching provider is configured
     */
    public function getProviderName(): string
    {
        /** @var array<string, array<string, mixed>> $guards */
        $guards = Configure::read('Tessera.guards') ?? [];
        $providers = [];
        foreach ($guards as $guard) {
            if (($guard['driver'] ?? null) === 'tessera' && isset($guard['provider'])) {
                $providers[] = $guard['provider'];
            }
        }

        /** @var array<string, array<string, mixed>> $providerConfig */
        $providerConfig = Configure::read('Tessera.providers') ?? [];

        foreach ($providerConfig as $provider => $config) {
            if (
                in_array($provider, $providers, true)
                && ($config['driver'] ?? null) === 'orm'
                && isset($config['model'])
                && is_a($this, (string)$config['model'])
            ) {
                return (string)$provider;
            }
        }

        throw new LogicException('Unable to determine authentication provider for this model from configuration.');
    }

    /**
     * Set the current access token for the user.
     *
     * @param \Crustum\Tessera\Contracts\ScopeAuthorizable|null $accessToken Access token
     * @return static
     */
    public function withAccessToken(?ScopeAuthorizable $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }
}
