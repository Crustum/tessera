<?php
declare(strict_types=1);

namespace Crustum\Tessera\Bridge;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;

/**
 * Bearer token response that includes the access token id.
 */
class PersonalAccessBearerTokenResponse extends BearerTokenResponse
{
    /**
     * @inheritDoc
     */
    protected function getExtraParams(AccessTokenEntityInterface $accessToken): array
    {
        return [
            'access_token_id' => $accessToken->getIdentifier(),
        ];
    }
}
