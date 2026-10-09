<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Bridge;

use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Event\EventManagerInterface;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\Bridge\AccessToken;
use Crustum\Tessera\Bridge\AccessTokenRepository;
use Crustum\Tessera\Bridge\Client;
use Crustum\Tessera\Bridge\Scope;
use Crustum\Tessera\Event\AccessTokenCreatedEvent;
use Crustum\Tessera\Event\AccessTokenRevokedEvent;
use Crustum\Tessera\Tessera;
use DateTimeImmutable;

/**
 * Bridge access token repository feature tests.
 */
class BridgeAccessTokenRepositoryTest extends TestCase
{
    private const CLIENT_ID = '019c9d23-9763-7303-9bdb-3a0a6bf0ef90';

    /**
     * @var list<object>
     */
    protected array $dispatched = [];

    /**
     * @return void
     */
    public function testAccessTokensCanBePersisted(): void
    {
        $expiration = new DateTimeImmutable();

        $repository = new AccessTokenRepository($this->eventManager());

        $accessToken = new AccessToken('2', [new Scope('scopes')], new Client(self::CLIENT_ID, 'name', ['redirect']));
        $accessToken->setIdentifier('1');
        $accessToken->setExpiryDateTime($expiration);

        $repository->persistNewAccessToken($accessToken);

        $token = Tessera::tokensTable()->get('1');
        $this->assertSame(2, $token->user_id);
        $this->assertSame(self::CLIENT_ID, $token->client_id);
        $this->assertSame(['scopes'], $token->scopes);
        $this->assertFalse($token->revoked);
        $this->assertNotNull($token->expires);

        $created = $this->dispatchedEvent(AccessTokenCreatedEvent::class);
        $this->assertSame('1', $created->getTokenId());
        $this->assertSame('2', $created->getUserId());
        $this->assertSame(self::CLIENT_ID, $created->getClientId());
    }

    /**
     * @return void
     */
    public function testAccessTokensCanBeRevoked(): void
    {
        $repository = new AccessTokenRepository($this->eventManager());

        $accessToken = new AccessToken('2', [], new Client(self::CLIENT_ID, 'name', ['redirect']));
        $accessToken->setIdentifier('token-id');
        $accessToken->setExpiryDateTime(new DateTimeImmutable());

        $repository->persistNewAccessToken($accessToken);
        $repository->revokeAccessToken('token-id');

        $token = Tessera::tokensTable()->get('token-id');
        $this->assertTrue($token->revoked);

        $revoked = $this->dispatchedEvent(AccessTokenRevokedEvent::class);
        $this->assertSame('token-id', $revoked->getTokenId());
    }

    /**
     * @return void
     */
    public function testAccessTokenRevokeEventIsNotDispatchedWhenNothingHappened(): void
    {
        $repository = new AccessTokenRepository($this->eventManager());
        $repository->revokeAccessToken('token-id');

        $this->assertSame([], $this->dispatched);
    }

    /**
     * @return void
     */
    public function testCanGetNewAccessToken(): void
    {
        $repository = new AccessTokenRepository(new EventManager());
        $client = new Client(self::CLIENT_ID, 'name', ['redirect']);
        $scopes = [new Scope('place-orders'), new Scope('check-status')];
        $userIdentifier = '123';

        $token = $repository->getNewToken($client, $scopes, $userIdentifier);

        $this->assertInstanceOf(AccessToken::class, $token);
        $this->assertEquals($client, $token->getClient());
        $this->assertEquals($scopes, $token->getScopes());
        $this->assertEquals($userIdentifier, $token->getUserIdentifier());
    }

    /**
     * Build an event manager capturing every dispatched lifecycle event.
     *
     * @return \Cake\Event\EventManagerInterface
     */
    protected function eventManager(): EventManagerInterface
    {
        $this->dispatched = [];

        $events = new EventManager();
        foreach ([AccessTokenCreatedEvent::NAME, AccessTokenRevokedEvent::NAME] as $name) {
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
     * @return T
     */
    protected function dispatchedEvent(string $class): object
    {
        foreach ($this->dispatched as $event) {
            if ($event instanceof $class) {
                return $event;
            }
        }

        $this->fail(sprintf('Expected %s to have been dispatched.', $class));
    }
}
