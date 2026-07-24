<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;

/**
 * Bearer response that adds a fixed id_token for Feature coverage.
 */
class IdTokenResponse extends BearerTokenResponse
{
    /**
     * @param string $idToken OpenID-style id_token value
     */
    public function __construct(
        protected string $idToken,
    ) {
    }

    /**
     * @inheritDoc
     */
    protected function getExtraParams(AccessTokenEntityInterface $accessToken): array
    {
        return [
            'id_token' => $this->idToken,
        ];
    }
}
