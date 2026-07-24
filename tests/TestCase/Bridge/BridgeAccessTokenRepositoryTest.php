<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Bridge;

use Cake\Event\EventManagerInterface;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\Bridge\AccessToken;
use Crustum\Tessera\Bridge\AccessTokenRepository;
use Crustum\Tessera\Bridge\Client;
use Crustum\Tessera\Bridge\Scope;
use Crustum\Tessera\Tessera;
use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * Bridge access token repository feature tests.
 */
class BridgeAccessTokenRepositoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const CLIENT_ID = '019c9d23-9763-7303-9bdb-3a0a6bf0ef90';

    /**
     * @return void
     */
    public function testAccessTokensCanBePersisted(): void
    {
        $expiration = new DateTimeImmutable();

        $events = Mockery::mock(EventManagerInterface::class);
        $events->shouldReceive('dispatch')->once();

        $accessToken = new AccessToken('2', [new Scope('scopes')], new Client(self::CLIENT_ID, 'name', ['redirect']));
        $accessToken->setIdentifier('1');
        $accessToken->setExpiryDateTime($expiration);

        $repository = new AccessTokenRepository($events);
        $repository->persistNewAccessToken($accessToken);

        $token = Tessera::tokensTable()->get('1');
        $this->assertSame(2, $token->user_id);
        $this->assertSame(self::CLIENT_ID, $token->client_id);
        $this->assertSame(['scopes'], $token->scopes);
        $this->assertFalse($token->revoked);
        $this->assertNotNull($token->expires);
    }

    /**
     * @return void
     */
    public function testAccessTokensCanBeRevoked(): void
    {
        $events = Mockery::mock(EventManagerInterface::class);
        $events->shouldReceive('dispatch')->twice();

        $accessToken = new AccessToken('2', [], new Client(self::CLIENT_ID, 'name', ['redirect']));
        $accessToken->setIdentifier('token-id');
        $accessToken->setExpiryDateTime(new DateTimeImmutable());

        $repository = new AccessTokenRepository($events);
        $repository->persistNewAccessToken($accessToken);
        $repository->revokeAccessToken('token-id');

        $token = Tessera::tokensTable()->get('token-id');
        $this->assertTrue($token->revoked);
    }

    /**
     * @return void
     */
    public function testAccessTokenRevokeEventIsNotDispatchedWhenNothingHappened(): void
    {
        $events = Mockery::mock(EventManagerInterface::class);
        $events->shouldNotReceive('dispatch');

        $repository = new AccessTokenRepository($events);
        $repository->revokeAccessToken('token-id');
    }

    /**
     * @return void
     */
    public function testCanGetNewAccessToken(): void
    {
        $events = Mockery::mock(EventManagerInterface::class);
        $repository = new AccessTokenRepository($events);
        $client = new Client(self::CLIENT_ID, 'name', ['redirect']);
        $scopes = [new Scope('place-orders'), new Scope('check-status')];
        $userIdentifier = '123';

        $token = $repository->getNewToken($client, $scopes, $userIdentifier);

        $this->assertInstanceOf(AccessToken::class, $token);
        $this->assertEquals($client, $token->getClient());
        $this->assertEquals($scopes, $token->getScopes());
        $this->assertEquals($userIdentifier, $token->getUserIdentifier());
    }
}
