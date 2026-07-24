<?php
declare(strict_types=1);

namespace Crustum\Tessera\Bridge;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

/**
 * League OAuth2 client entity.
 */
class Client implements ClientEntityInterface
{
    use ClientTrait;
    use EntityTrait;

    /**
     * Bind League client identity, redirect URIs, confidentiality, and grant types.
     *
     * @param non-empty-string $identifier Client identifier
     * @param string|null $name Display name
     * @param list<string> $redirectUri Redirect URIs
     * @param bool $isConfidential Whether the client keeps a secret
     * @param string|null $provider User provider name
     * @param list<string> $grantTypes Allowed grant types
     */
    public function __construct(
        string $identifier,
        ?string $name = null,
        array $redirectUri = [],
        bool $isConfidential = false,
        public ?string $provider = null,
        public array $grantTypes = [],
    ) {
        $this->setIdentifier($identifier);

        if ($name !== null) {
            $this->name = $name;
        }

        $this->isConfidential = $isConfidential;
        $this->redirectUri = $redirectUri;
    }

    /**
     * @inheritDoc
     */
    public function supportsGrantType(string $grantType): bool
    {
        return in_array($grantType, $this->grantTypes, true);
    }
}
