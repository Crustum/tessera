<?php
declare(strict_types=1);

namespace Crustum\Tessera;

use Cake\Core\Configure;
use Cake\Utility\Security;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Model\Entity\RefreshToken;
use Crustum\Tessera\Model\Entity\Token;
use RuntimeException;

/**
 * Application-level OAuth client repository.
 */
class ClientRepository
{
    /**
     * Find a client by its identifier.
     *
     * @param string|int $id Client identifier
     * @return \Crustum\Tessera\Model\Entity\Client|null
     */
    public function find(string|int $id): ?Client
    {
        /** @var \Crustum\Tessera\Model\Entity\Client|null $client */
        $client = Tessera::clientsTable()->find()
            ->where(['id' => $id])
            ->first();

        return $client;
    }

    /**
     * Find a non-revoked client by its identifier.
     *
     * @param string|int $id Client identifier
     * @return \Crustum\Tessera\Model\Entity\Client|null
     */
    public function findActive(string|int $id): ?Client
    {
        $client = $this->find($id);

        return $client && !$client->revoked ? $client : null;
    }

    /**
     * Find a non-revoked client owned by the given user.
     *
     * @param string|int $clientId Client identifier
     * @param \Crustum\Tessera\Contracts\OAuthenticatable $user Owner
     * @return \Crustum\Tessera\Model\Entity\Client|null
     */
    public function findForUser(string|int $clientId, OAuthenticatable $user): ?Client
    {
        /** @var \Crustum\Tessera\Model\Entity\Client|null $client */
        $client = Tessera::clientsTable()->find()
            ->where([
                'id' => $clientId,
                'owner_id' => $user->getIdentifier(),
                'revoked' => false,
            ])
            ->first();

        return $client;
    }

    /**
     * List non-revoked clients owned by the given user, ordered by name.
     *
     * @param \Crustum\Tessera\Contracts\OAuthenticatable $user Owner
     * @return list<\Crustum\Tessera\Model\Entity\Client>
     */
    public function forUser(OAuthenticatable $user): array
    {
        /** @var list<\Crustum\Tessera\Model\Entity\Client> $clients */
        $clients = Tessera::clientsTable()->find()
            ->where([
                'owner_id' => $user->getIdentifier(),
                'revoked' => false,
            ])
            ->orderBy(['name' => 'ASC'])
            ->all()
            ->toList();

        return $clients;
    }

    /**
     * Resolve the latest personal-access client for a user provider.
     *
     * @param string $provider User provider name
     * @return \Crustum\Tessera\Model\Entity\Client
     * @throws \RuntimeException When no matching personal-access client exists
     */
    public function personalAccessClient(string $provider): Client
    {
        $defaultProvider = Configure::read('Tessera.provider')
            ?? Configure::read('Tessera.usersTable')
            ?? 'Users';

        $query = Tessera::clientsTable()->find()
            ->where(['revoked' => false]);

        if ($provider === $defaultProvider) {
            $query->andWhere([
                'OR' => [
                    ['provider IS' => null],
                    ['provider' => $provider],
                ],
            ]);
        } else {
            $query->andWhere(['provider' => $provider]);
        }

        $clients = $query->orderBy(['created' => 'DESC'])->all();

        foreach ($clients as $client) {
            if ($client instanceof Client && $client->hasGrantType('personal_access')) {
                return $client;
            }
        }

        throw new RuntimeException(
            "Personal access client not found for '{$provider}' user provider. Please create one.",
        );
    }

    /**
     * Persist a new OAuth client with the given grant types and owner.
     *
     * @param string $name Client name
     * @param list<string> $grantTypes Grant types
     * @param list<string> $redirectUris Redirect URIs
     * @param string|null $provider Provider name
     * @param bool $confidential Whether the client has a secret
     * @param \Crustum\Tessera\Contracts\OAuthenticatable|null $user Owner
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    protected function create(
        string $name,
        array $grantTypes,
        array $redirectUris = [],
        ?string $provider = null,
        bool $confidential = true,
        ?OAuthenticatable $user = null,
    ): Client {
        $table = Tessera::clientsTable();
        $client = $table->newEntity([
            'name' => $name,
            'secret' => $confidential ? Security::randomString(40) : null,
            'provider' => $provider,
            'revoked' => false,
            'redirect_uris' => $redirectUris,
            'grant_types' => $grantTypes,
        ]);

        if ($user instanceof OAuthenticatable) {
            $client->set('owner_id', (string)$user->getIdentifier());
            $client->set('owner_type', $user::class);
        }

        return $table->saveOrFail($client);
    }

    /**
     * Create a personal-access grant client.
     *
     * @param string $name Client name
     * @param string|null $provider Provider name
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    public function createPersonalAccessGrantClient(string $name, ?string $provider = null): Client
    {
        return $this->create($name, ['personal_access'], [], $provider);
    }

    /**
     * Create a password grant client (with refresh token).
     *
     * @param string $name Client name
     * @param string|null $provider Provider name
     * @param bool $confidential Whether the client has a secret
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    public function createPasswordGrantClient(
        string $name,
        ?string $provider = null,
        bool $confidential = false,
    ): Client {
        return $this->create($name, ['password', 'refresh_token'], [], $provider, $confidential);
    }

    /**
     * Create a client-credentials grant client.
     *
     * @param string $name Client name
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    public function createClientCredentialsGrantClient(string $name): Client
    {
        return $this->create($name, ['client_credentials']);
    }

    /**
     * Create an implicit grant client.
     *
     * @param string $name Client name
     * @param list<string> $redirectUris Redirect URIs
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    public function createImplicitGrantClient(string $name, array $redirectUris): Client
    {
        return $this->create($name, ['implicit'], $redirectUris, null, false);
    }

    /**
     * Create a device-authorization grant client.
     *
     * @param string $name Client name
     * @param bool $confidential Whether the client has a secret
     * @param \Crustum\Tessera\Contracts\OAuthenticatable|null $user Owner
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    public function createDeviceAuthorizationGrantClient(
        string $name,
        bool $confidential = true,
        ?OAuthenticatable $user = null,
    ): Client {
        return $this->create(
            $name,
            ['urn:ietf:params:oauth:grant-type:device_code', 'refresh_token'],
            [],
            null,
            $confidential,
            $user,
        );
    }

    /**
     * Create an authorization-code grant client (optionally with device flow).
     *
     * @param string $name Client name
     * @param list<string> $redirectUris Redirect URIs
     * @param bool $confidential Whether the client has a secret
     * @param \Crustum\Tessera\Contracts\OAuthenticatable|null $user Owner
     * @param bool $enableDeviceFlow Whether to enable device code grant
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    public function createAuthorizationCodeGrantClient(
        string $name,
        array $redirectUris,
        bool $confidential = true,
        ?OAuthenticatable $user = null,
        bool $enableDeviceFlow = false,
    ): Client {
        $grantTypes = ['authorization_code', 'refresh_token'];

        if ($enableDeviceFlow) {
            $grantTypes[] = 'urn:ietf:params:oauth:grant-type:device_code';
        }

        return $this->create($name, $grantTypes, $redirectUris, null, $confidential, $user);
    }

    /**
     * Update a client's name and redirect URIs.
     *
     * @param \Crustum\Tessera\Model\Entity\Client $client Client
     * @param string $name Name
     * @param list<string> $redirectUris Redirect URIs
     * @return bool
     */
    public function update(Client $client, string $name, array $redirectUris): bool
    {
        $client->set('name', $name);
        $client->set('redirect_uris', $redirectUris);

        return (bool)Tessera::clientsTable()->save($client);
    }

    /**
     * Issue a new client secret and persist it.
     *
     * @param \Crustum\Tessera\Model\Entity\Client $client Client
     * @return bool
     */
    public function regenerateSecret(Client $client): bool
    {
        $client->set('secret', Security::randomString(40));

        return (bool)Tessera::clientsTable()->save($client);
    }

    /**
     * Revoke the client and all of its access and refresh tokens.
     *
     * @param \Crustum\Tessera\Model\Entity\Client $client Client
     * @return void
     */
    public function delete(Client $client): void
    {
        $tokens = Tessera::tokensTable()->find()
            ->contain(['RefreshTokens'])
            ->where(['client_id' => $client->id])
            ->all();

        foreach ($tokens as $token) {
            if (!$token instanceof Token) {
                continue;
            }

            $refresh = $token->get('refresh_token');
            if ($refresh instanceof RefreshToken) {
                Tessera::refreshTokensTable()->revoke($refresh);
            }

            Tessera::tokensTable()->revoke($token);
        }

        $client->set('revoked', true);
        Tessera::clientsTable()->saveOrFail($client);
    }
}
