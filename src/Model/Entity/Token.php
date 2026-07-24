<?php
declare(strict_types=1);

namespace Crustum\Tessera\Model\Entity;

use Cake\ORM\Entity;
use Crustum\Tessera\Contracts\ScopeAuthorizable;
use Crustum\Tessera\Trait\ResolvesInheritedScopesTrait;

/**
 * OAuth access token entity.
 *
 * @property string $id
 * @property int|string|null $user_id
 * @property string $client_id
 * @property string|null $name
 * @property list<string>|null $scopes
 * @property bool $revoked
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property \Cake\I18n\DateTime|null $expires
 */
class Token extends Entity implements ScopeAuthorizable
{
    use ResolvesInheritedScopesTrait;

    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => true,
        'client_id' => true,
        'name' => true,
        'scopes' => true,
        'revoked' => true,
        'expires' => true,
    ];

    /**
     * @inheritDoc
     */
    public function can(string $scope): bool
    {
        $scopes = $this->scopes ?? [];
        if ($scopes === []) {
            return false;
        }

        return in_array('*', $scopes, true)
            || $this->scopeExistsIn($scope, $scopes);
    }

    /**
     * @inheritDoc
     */
    public function cant(string $scope): bool
    {
        return !$this->can($scope);
    }

    /**
     * Mark the token as revoked in memory (persist via table save).
     *
     * @return $this
     */
    public function markRevoked()
    {
        $this->set('revoked', true);

        return $this;
    }
}
