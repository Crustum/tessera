<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\Bridge\AccessToken;
use Crustum\Tessera\Bridge\AccessTokenRepository;
use Crustum\Tessera\Bridge\AuthCode;
use Crustum\Tessera\Bridge\AuthCodeRepository;
use Crustum\Tessera\Bridge\Client;
use Crustum\Tessera\Bridge\DeviceCode;
use Crustum\Tessera\Bridge\DeviceCodeRepository;
use Crustum\Tessera\Bridge\RefreshToken;
use Crustum\Tessera\Bridge\RefreshTokenRepository;
use Crustum\Tessera\Event\AccessTokenCreatedEvent;
use Crustum\Tessera\Event\AccessTokenRevokedEvent;
use Crustum\Tessera\Event\RefreshTokenCreatedEvent;
use DateTimeImmutable;

/**
 * Bridge repository revoked-state Feature coverage.
 */
class RevokedTest extends TestCase
{
    private const CLIENT_ID = '019c9d23-9763-7303-9bdb-3a0a6bf0ef90';

    private const TOKEN_ID = 'token-id-001';

    private const ACCESS_TOKEN_ID = 'access-token-id-001';

    private const DEVICE_CODE_ID = 'device-code-id-001';

    /**
     * @var list<object>
     */
    protected array $dispatched = [];

    /**
     * @return void
     */
    public function testItCanDetermineIfAAccessTokenIsRevoked(): void
    {
        $repository = $this->accessTokenRepository();
        $this->persistNewAccessToken($repository, self::TOKEN_ID);

        $repository->revokeAccessToken(self::TOKEN_ID);

        $this->assertTrue($repository->isAccessTokenRevoked(self::TOKEN_ID));

        $created = $this->dispatchedEvent(AccessTokenCreatedEvent::class);
        $this->assertSame(self::TOKEN_ID, $created->getTokenId());
        $this->assertSame('1', $created->getUserId());
        $this->assertSame(self::CLIENT_ID, $created->getClientId());

        $revoked = $this->dispatchedEvent(AccessTokenRevokedEvent::class);
        $this->assertSame(self::TOKEN_ID, $revoked->getTokenId());
    }

    /**
     * @return void
     */
    public function testAAccessTokenIsAlsoRevokedIfItCannotBeFound(): void
    {
        $repository = $this->accessTokenRepository();

        $this->assertTrue($repository->isAccessTokenRevoked('notExistingTokenId'));
    }

    /**
     * @return void
     */
    public function testItCanDetermineIfAAccessTokenIsNotRevoked(): void
    {
        $repository = $this->accessTokenRepository();
        $this->persistNewAccessToken($repository, self::TOKEN_ID);

        $this->assertFalse($repository->isAccessTokenRevoked(self::TOKEN_ID));
        $this->assertNotNull($this->dispatchedEvent(AccessTokenCreatedEvent::class));
        $this->assertNull($this->findDispatchedEvent(AccessTokenRevokedEvent::class));
    }

    /**
     * @return void
     */
    public function testItCanDetermineIfAAuthCodeIsRevoked(): void
    {
        $repository = $this->authCodeRepository();
        $this->persistNewAuthCode($repository, self::TOKEN_ID);

        $repository->revokeAuthCode(self::TOKEN_ID);

        $this->assertTrue($repository->isAuthCodeRevoked(self::TOKEN_ID));
    }

    /**
     * @return void
     */
    public function testAAuthCodeIsAlsoRevokedIfItCannotBeFound(): void
    {
        $repository = $this->authCodeRepository();

        $this->assertTrue($repository->isAuthCodeRevoked('notExistingTokenId'));
    }

    /**
     * @return void
     */
    public function testItCanDetermineIfAAuthCodeIsNotRevoked(): void
    {
        $repository = $this->authCodeRepository();
        $this->persistNewAuthCode($repository, self::TOKEN_ID);

        $this->assertFalse($repository->isAuthCodeRevoked(self::TOKEN_ID));
    }

    /**
     * @return void
     */
    public function testItCanDetermineIfARefreshTokenIsRevoked(): void
    {
        $repository = $this->refreshTokenRepository();
        $this->persistNewRefreshToken($repository, self::TOKEN_ID);

        $repository->revokeRefreshToken(self::TOKEN_ID);

        $this->assertTrue($repository->isRefreshTokenRevoked(self::TOKEN_ID));

        $created = $this->dispatchedEvent(RefreshTokenCreatedEvent::class);
        $this->assertSame(self::TOKEN_ID, $created->getRefreshTokenId());
        $this->assertSame(self::ACCESS_TOKEN_ID, $created->getAccessTokenId());
    }

    /**
     * @return void
     */
    public function testARefreshTokenIsAlsoRevokedIfItCannotBeFound(): void
    {
        $repository = $this->refreshTokenRepository();

        $this->assertTrue($repository->isRefreshTokenRevoked('notExistingTokenId'));
    }

    /**
     * @return void
     */
    public function testItCanDetermineIfARefreshTokenIsNotRevoked(): void
    {
        $repository = $this->refreshTokenRepository();
        $this->persistNewRefreshToken($repository, self::TOKEN_ID);

        $this->assertFalse($repository->isRefreshTokenRevoked(self::TOKEN_ID));
        $this->assertNotNull($this->dispatchedEvent(RefreshTokenCreatedEvent::class));
    }

    /**
     * @return void
     */
    public function testItCanDetermineIfADeviceCodeIsRevoked(): void
    {
        $repository = $this->deviceCodeRepository();
        $this->persistNewDeviceCode($repository, self::DEVICE_CODE_ID);

        $repository->revokeDeviceCode(self::DEVICE_CODE_ID);

        $this->assertTrue($repository->isDeviceCodeRevoked(self::DEVICE_CODE_ID));
    }

    /**
     * @return void
     */
    public function testADeviceCodeIsAlsoRevokedIfItCannotBeFound(): void
    {
        $repository = $this->deviceCodeRepository();

        $this->assertTrue($repository->isDeviceCodeRevoked('notExistingDeviceCodeId'));
    }

    /**
     * @return void
     */
    public function testItCanDetermineIfADeviceCodeIsNotRevoked(): void
    {
        $repository = $this->deviceCodeRepository();
        $this->persistNewDeviceCode($repository, self::DEVICE_CODE_ID);

        $this->assertFalse($repository->isDeviceCodeRevoked(self::DEVICE_CODE_ID));
    }

    /**
     * @return \Crustum\Tessera\Bridge\AccessTokenRepository
     */
    protected function accessTokenRepository(): AccessTokenRepository
    {
        return new AccessTokenRepository($this->eventManager([
            AccessTokenCreatedEvent::NAME,
            AccessTokenRevokedEvent::NAME,
        ]));
    }

    /**
     * @param \Crustum\Tessera\Bridge\AccessTokenRepository $repository Repository
     * @param string $id Token id
     * @return void
     */
    protected function persistNewAccessToken(AccessTokenRepository $repository, string $id): void
    {
        $accessToken = new AccessToken('1', [], new Client(self::CLIENT_ID, 'name', ['redirect']));
        $accessToken->setIdentifier($id);
        $accessToken->setExpiryDateTime(new DateTimeImmutable());

        $repository->persistNewAccessToken($accessToken);
    }

    /**
     * @return \Crustum\Tessera\Bridge\AuthCodeRepository
     */
    protected function authCodeRepository(): AuthCodeRepository
    {
        return new AuthCodeRepository();
    }

    /**
     * @param \Crustum\Tessera\Bridge\AuthCodeRepository $repository Repository
     * @param string $id Auth code id
     * @return void
     */
    protected function persistNewAuthCode(AuthCodeRepository $repository, string $id): void
    {
        $authCode = new AuthCode();
        $authCode->setIdentifier($id);
        $authCode->setUserIdentifier('1');
        $authCode->setClient(new Client(self::CLIENT_ID, 'name', ['redirect']));
        $authCode->setExpiryDateTime(new DateTimeImmutable());

        $repository->persistNewAuthCode($authCode);
    }

    /**
     * @return \Crustum\Tessera\Bridge\RefreshTokenRepository
     */
    protected function refreshTokenRepository(): RefreshTokenRepository
    {
        return new RefreshTokenRepository($this->eventManager([
            RefreshTokenCreatedEvent::NAME,
        ]));
    }

    /**
     * @param \Crustum\Tessera\Bridge\RefreshTokenRepository $repository Repository
     * @param string $id Refresh token id
     * @return void
     */
    protected function persistNewRefreshToken(RefreshTokenRepository $repository, string $id): void
    {
        $accessToken = new AccessToken('1', [], new Client(self::CLIENT_ID, 'name', ['redirect']));
        $accessToken->setIdentifier(self::ACCESS_TOKEN_ID);

        $refreshToken = new RefreshToken();
        $refreshToken->setIdentifier($id);
        $refreshToken->setAccessToken($accessToken);
        $refreshToken->setExpiryDateTime(new DateTimeImmutable());

        $repository->persistNewRefreshToken($refreshToken);
    }

    /**
     * Build an event manager capturing the given lifecycle events.
     *
     * @param list<string> $names Event names
     * @return \Cake\Event\EventManager
     */
    protected function eventManager(array $names): EventManager
    {
        $this->dispatched = [];

        $events = new EventManager();
        foreach ($names as $name) {
            $events->on($name, function (EventInterface $event): void {
                $this->dispatched[] = $event;
            });
        }

        return $events;
    }

    /**
     * Find the first captured event of the given class.
     *
     * @template T of object
     * @param class-string<T> $class Event class
     * @return T|null
     */
    protected function findDispatchedEvent(string $class): ?object
    {
        foreach ($this->dispatched as $event) {
            if ($event instanceof $class) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Find the first captured event of the given class, failing when absent.
     *
     * @template T of object
     * @param class-string<T> $class Event class
     * @return T
     */
    protected function dispatchedEvent(string $class): object
    {
        $event = $this->findDispatchedEvent($class);
        $this->assertNotNull($event, sprintf('Expected %s to have been dispatched.', $class));

        return $event;
    }

    /**
     * @return \Crustum\Tessera\Bridge\DeviceCodeRepository
     */
    protected function deviceCodeRepository(): DeviceCodeRepository
    {
        return new DeviceCodeRepository();
    }

    /**
     * @param \Crustum\Tessera\Bridge\DeviceCodeRepository $repository Repository
     * @param string $id Device code id
     * @return void
     */
    protected function persistNewDeviceCode(DeviceCodeRepository $repository, string $id): void
    {
        $deviceCode = new DeviceCode(
            $id,
            null,
            self::CLIENT_ID,
            [],
            false,
            null,
            new DateTimeImmutable(),
        );
        $deviceCode->setUserCode('userCode');

        $repository->persistDeviceCode($deviceCode);
    }
}
