<?php
declare(strict_types=1);

namespace Crustum\Tessera\Bridge;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\DeviceCodeEntityInterface;
use League\OAuth2\Server\Entities\Traits\DeviceCodeTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/**
 * League OAuth2 device code entity.
 */
class DeviceCode implements DeviceCodeEntityInterface
{
    use DeviceCodeTrait;
    use EntityTrait;
    use TokenEntityTrait;

    /**
     * Bind device-code fields for the League device authorization grant.
     *
     * @param non-empty-string|null $identifier Device code id
     * @param non-empty-string|null $userIdentifier User id
     * @param non-empty-string|null $clientIdentifier Client id
     * @param list<string> $scopes Scope identifiers
     * @param bool $userApproved Whether the user approved the device
     * @param \DateTimeImmutable|null $lastPolledAt Last poll timestamp
     * @param \DateTimeImmutable|null $expiryDateTime Expiry timestamp
     */
    public function __construct(
        ?string $identifier = null,
        ?string $userIdentifier = null,
        ?string $clientIdentifier = null,
        array $scopes = [],
        bool $userApproved = false,
        ?DateTimeImmutable $lastPolledAt = null,
        ?DateTimeImmutable $expiryDateTime = null,
    ) {
        if ($identifier !== null) {
            $this->setIdentifier($identifier);
        }

        if ($userIdentifier !== null) {
            $this->setUserIdentifier($userIdentifier);
        }

        if ($clientIdentifier !== null) {
            $this->setClient(new Client($clientIdentifier));
        }

        foreach ($scopes as $scope) {
            $this->addScope(new Scope($scope));
        }

        if ($userApproved) {
            $this->setUserApproved($userApproved);
        }

        if ($lastPolledAt instanceof DateTimeImmutable) {
            $this->setLastPolledAt($lastPolledAt);
        }

        if ($expiryDateTime instanceof DateTimeImmutable) {
            $this->setExpiryDateTime($expiryDateTime);
        }
    }
}
