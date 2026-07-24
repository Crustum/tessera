<?php
declare(strict_types=1);

namespace Crustum\Tessera;

use Cake\I18n\DateTime;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Model\Entity\Token;
use Crustum\Tessera\Support\UserId;

/**
 * Application-level OAuth access token repository.
 */
class TokenRepository
{
    /**
     * Find a non-revoked, unexpired token for the given user.
     *
     * @param string $id Token identifier
     * @param \Crustum\Tessera\Contracts\OAuthenticatable $user Owner
     * @return \Crustum\Tessera\Model\Entity\Token|null
     */
    public function findForUser(string $id, OAuthenticatable $user): ?Token
    {
        /** @var \Crustum\Tessera\Model\Entity\Token|null $token */
        $token = Tessera::tokensTable()->find()
            ->contain(['Clients'])
            ->where([
                'Tokens.id' => $id,
                'Tokens.user_id' => UserId::normalize($user->getIdentifier()),
                'Tokens.revoked' => false,
                'Tokens.expires >' => DateTime::now(),
            ])
            ->first();

        return $token;
    }

    /**
     * List non-revoked, unexpired tokens for the given user.
     *
     * @param \Crustum\Tessera\Contracts\OAuthenticatable $user Owner
     * @return list<\Crustum\Tessera\Model\Entity\Token>
     */
    public function forUser(OAuthenticatable $user): array
    {
        /** @var list<\Crustum\Tessera\Model\Entity\Token> $tokens */
        $tokens = Tessera::tokensTable()->find()
            ->contain(['Clients'])
            ->where([
                'Tokens.user_id' => UserId::normalize($user->getIdentifier()),
                'Tokens.revoked' => false,
                'Tokens.expires >' => DateTime::now(),
            ])
            ->all()
            ->toList();

        return $tokens;
    }
}
