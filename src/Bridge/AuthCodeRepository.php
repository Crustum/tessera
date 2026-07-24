<?php
declare(strict_types=1);

namespace Crustum\Tessera\Bridge;

use Crustum\Tessera\Support\UserId;
use Crustum\Tessera\Tessera;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;

/**
 * League OAuth2 authorization code repository.
 */
class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCode();
    }

    /**
     * @inheritDoc
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $table = Tessera::authCodesTable();
        $code = $table->newEntity([
            'user_id' => UserId::normalize($authCodeEntity->getUserIdentifier()),
            'client_id' => $authCodeEntity->getClient()->getIdentifier(),
            'scopes' => $this->scopeIdentifiers($authCodeEntity->getScopes()),
            'revoked' => false,
            'expires' => $authCodeEntity->getExpiryDateTime(),
        ]);
        $code->set('id', $authCodeEntity->getIdentifier());

        $table->saveOrFail($code);
    }

    /**
     * @inheritDoc
     */
    public function revokeAuthCode(string $codeId): void
    {
        Tessera::authCodesTable()->updateAll(
            ['revoked' => true],
            ['id' => $codeId],
        );
    }

    /**
     * @inheritDoc
     */
    public function isAuthCodeRevoked(string $codeId): bool
    {
        return !Tessera::authCodesTable()->exists([
            'id' => $codeId,
            'revoked' => false,
        ]);
    }

    /**
     * Extract scope identifier strings from League scope entities.
     *
     * @param array<\League\OAuth2\Server\Entities\ScopeEntityInterface> $scopes Scopes
     * @return list<string>
     */
    protected function scopeIdentifiers(array $scopes): array
    {
        $ids = [];
        foreach ($scopes as $scope) {
            $ids[] = $scope->getIdentifier();
        }

        return $ids;
    }
}
