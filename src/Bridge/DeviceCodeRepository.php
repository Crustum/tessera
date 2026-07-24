<?php
declare(strict_types=1);

namespace Crustum\Tessera\Bridge;

use Cake\I18n\DateTime;
use Crustum\Tessera\Model\Entity\DeviceCode as DeviceCodeModel;
use Crustum\Tessera\Support\UserId;
use Crustum\Tessera\Tessera;
use DateTimeImmutable;
use DateTimeInterface;
use League\OAuth2\Server\Entities\DeviceCodeEntityInterface;
use League\OAuth2\Server\Repositories\DeviceCodeRepositoryInterface;

/**
 * League OAuth2 device code repository.
 */
class DeviceCodeRepository implements DeviceCodeRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function getNewDeviceCode(): DeviceCodeEntityInterface
    {
        return new DeviceCode();
    }

    /**
     * @inheritDoc
     */
    public function persistDeviceCode(DeviceCodeEntityInterface $deviceCodeEntity): void
    {
        $table = Tessera::deviceCodesTable();
        $id = $deviceCodeEntity->getIdentifier();

        if ($deviceCodeEntity->getUserIdentifier() !== null) {
            $table->updateAll(
                [
                    'user_id' => UserId::normalize($deviceCodeEntity->getUserIdentifier()),
                    'user_approved' => $deviceCodeEntity->getUserApproved() ? DateTime::now() : null,
                ],
                ['id' => $id],
            );

            return;
        }

        if ($deviceCodeEntity->getLastPolledAt() instanceof DateTimeImmutable) {
            $table->updateAll(
                ['last_polled' => $deviceCodeEntity->getLastPolledAt()],
                ['id' => $id],
            );

            return;
        }

        $code = $table->newEntity([
            'user_id' => null,
            'client_id' => $deviceCodeEntity->getClient()->getIdentifier(),
            'user_code' => $deviceCodeEntity->getUserCode(),
            'scopes' => $this->scopeIdentifiers($deviceCodeEntity->getScopes()),
            'revoked' => false,
            'user_approved' => null,
            'last_polled' => null,
            'expires' => $deviceCodeEntity->getExpiryDateTime(),
        ]);
        $code->set('id', $id);

        $table->saveOrFail($code);
    }

    /**
     * @inheritDoc
     */
    public function getDeviceCodeEntityByDeviceCode(string $deviceCode): ?DeviceCodeEntityInterface
    {
        /** @var \Crustum\Tessera\Model\Entity\DeviceCode|null $record */
        $record = Tessera::deviceCodesTable()->find()
            ->where([
                'id' => $deviceCode,
                'revoked' => false,
            ])
            ->first();

        return $record ? $this->fromDeviceCodeModel($record) : null;
    }

    /**
     * Find a device code by its user-facing code.
     *
     * @param string $userCode User code
     * @return \League\OAuth2\Server\Entities\DeviceCodeEntityInterface|null
     */
    public function getDeviceCodeEntityByUserCode(string $userCode): ?DeviceCodeEntityInterface
    {
        /** @var \Crustum\Tessera\Model\Entity\DeviceCode|null $record */
        $record = Tessera::deviceCodesTable()->find()
            ->where([
                'user_code' => $userCode,
                'user_id IS' => null,
                'expires >' => DateTime::now(),
                'revoked' => false,
            ])
            ->first();

        return $record ? $this->fromDeviceCodeModel($record) : null;
    }

    /**
     * @inheritDoc
     */
    public function revokeDeviceCode(string $codeId): void
    {
        Tessera::deviceCodesTable()->updateAll(
            ['revoked' => true],
            ['id' => $codeId],
        );
    }

    /**
     * @inheritDoc
     */
    public function isDeviceCodeRevoked(string $codeId): bool
    {
        return !Tessera::deviceCodesTable()->exists([
            'id' => $codeId,
            'revoked' => false,
        ]);
    }

    /**
     * Map a Tessera device code entity to a League device code entity.
     *
     * @param \Crustum\Tessera\Model\Entity\DeviceCode $model Device code entity
     * @return \League\OAuth2\Server\Entities\DeviceCodeEntityInterface
     */
    protected function fromDeviceCodeModel(DeviceCodeModel $model): DeviceCodeEntityInterface
    {
        $userId = $model->user_id;
        $lastPolled = $model->last_polled;
        $expires = $model->expires;

        return new DeviceCode(
            (string)$model->id,
            $userId !== null ? (string)$userId : null,
            (string)$model->client_id,
            $model->scopes ?? [],
            $model->user_approved !== null,
            $this->toImmutable($lastPolled),
            $this->toImmutable($expires),
        );
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

    /**
     * Convert a date value to DateTimeImmutable when present.
     *
     * @param \DateTimeInterface|null $value Date value
     * @return \DateTimeImmutable|null
     */
    protected function toImmutable(?DateTimeInterface $value): ?DateTimeImmutable
    {
        if (!$value instanceof DateTimeInterface) {
            return null;
        }

        return DateTimeImmutable::createFromInterface($value);
    }
}
