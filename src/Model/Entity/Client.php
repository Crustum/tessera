<?php
declare(strict_types=1);

namespace Crustum\Tessera\Model\Entity;

use Cake\ORM\Entity;
use Crustum\Tessera\Trait\ResolvesInheritedScopesTrait;

/**
 * OAuth client entity.
 *
 * @property string $id
 * @property string|null $owner_id
 * @property string|null $owner_type
 * @property string $name
 * @property string|null $secret
 * @property string|null $provider
 * @property list<string>|null $redirect_uris
 * @property list<string>|null $grant_types
 * @property list<string>|null $scopes
 * @property bool $revoked
 * @property bool|null $password_client
 * @property bool|null $personal_access_client
 * @property string|null $redirect
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property string|null $plainSecret
 */
class Client extends Entity
{
    use ResolvesInheritedScopesTrait;

    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'owner_id' => true,
        'owner_type' => true,
        'name' => true,
        'secret' => true,
        'provider' => true,
        'redirect_uris' => true,
        'grant_types' => true,
        'scopes' => true,
        'revoked' => true,
        'redirect' => true,
        'password_client' => true,
        'personal_access_client' => true,
    ];

    /**
     * @var list<string>
     */
    protected array $_hidden = [
        'secret',
    ];

    /**
     * Plain-text secret available only during the creating request.
     *
     * @var string|null
     */
    protected ?string $plainSecret = null;

    /**
     * Hash secret on set and retain the plain value temporarily.
     *
     * @param string|null $secret Plain secret
     * @return string|null
     */
    protected function _setSecret(?string $secret): ?string
    {
        if ($secret === null || $secret === '') {
            $this->plainSecret = null;

            return null;
        }

        $this->plainSecret = $secret;

        return password_hash($secret, PASSWORD_BCRYPT);
    }

    /**
     * Temporary plain-text secret.
     *
     * @return string|null
     */
    protected function _getPlainSecret(): ?string
    {
        return $this->plainSecret;
    }

    /**
     * Redirect URIs with legacy `redirect` column fallback.
     *
     * @param list<string>|null $uris Stored redirect URIs
     * @return list<string>
     */
    protected function _getRedirectUris(?array $uris): array
    {
        if ($uris !== null && $uris !== []) {
            return $uris;
        }

        $redirect = $this->get('redirect');
        if (is_string($redirect) && $redirect !== '') {
            return array_values(array_filter(explode(',', $redirect)));
        }

        return $uris ?? [];
    }

    /**
     * Grant types, or computed defaults when the column is empty.
     *
     * @param list<string>|null $types Stored grant types
     * @return list<string>
     */
    protected function _getGrantTypes(?array $types): array
    {
        if ($types !== null) {
            return $types;
        }

        return array_keys(array_filter([
            'authorization_code' => $this->redirect_uris !== [],
            'client_credentials' => $this->confidential() && $this->firstParty(),
            'implicit' => $this->redirect_uris !== [],
            'password' => (bool)$this->get('password_client'),
            'personal_access' => (bool)$this->get('personal_access_client') && $this->confidential(),
            'refresh_token' => true,
            'urn:ietf:params:oauth:grant-type:device_code' => true,
        ]));
    }

    /**
     * Whether the client is first-party (no owner).
     *
     * @return bool
     */
    public function firstParty(): bool
    {
        return empty($this->owner_id);
    }

    /**
     * Whether the client should skip the authorization prompt.
     *
     * @param object $user Authenticated user identity
     * @param list<\Crustum\Tessera\Scope> $scopes Requested scopes
     * @return bool
     */
    public function skipsAuthorization(object $user, array $scopes): bool
    {
        return false;
    }

    /**
     * Whether the client has the given grant type.
     *
     * @param string $grantType Grant type
     * @return bool
     */
    public function hasGrantType(string $grantType): bool
    {
        return in_array($grantType, $this->grant_types, true);
    }

    /**
     * Whether the client allows the given scope.
     *
     * @param string $scope Scope
     * @return bool
     */
    public function hasScope(string $scope): bool
    {
        if (!$this->has('scopes') || $this->get('scopes') === null) {
            return true;
        }

        $scopes = $this->get('scopes');
        if (!is_array($scopes)) {
            return true;
        }

        return $this->scopeExistsIn($scope, $scopes);
    }

    /**
     * Whether the client is confidential (has a secret).
     *
     * @return bool
     */
    public function confidential(): bool
    {
        $secret = $this->get('secret');

        return $secret !== null && $secret !== '';
    }
}
