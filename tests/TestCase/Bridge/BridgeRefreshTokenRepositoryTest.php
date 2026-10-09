<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Bridge;

use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Event\EventManagerInterface;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\Bridge\AccessToken;
use Crustum\Tessera\Bridge\Client;
use Crustum\Tessera\Bridge\RefreshToken;
use Crustum\Tessera\Bridge\RefreshTokenRepository;
use Crustum\Tessera\Event\RefreshTokenCreatedEvent;
use Crustum\Tessera\Tessera;
use DateTimeImmutable;

/**
 * Bridge refresh token repository feature tests.
 */
class BridgeRefreshTokenRepositoryTest extends TestCase
{
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

        $repository = new RefreshTokenRepository($this->eventManager());

        $accessToken = new AccessToken('3', [], new Client('3', 'name', ['redirect']));
        $accessToken->setIdentifier('2');

        $refreshToken = new RefreshToken();
        $refreshToken->setIdentifier('1');
        $refreshToken->setExpiryDateTime($expiration);
        $refreshToken->setAccessToken($accessToken);

        $repository->persistNewRefreshToken($refreshToken);

        $token = Tessera::refreshTokensTable()->get('1');
        $this->assertSame('2', $token->access_token_id);
        $this->assertFalse($token->revoked);
        $this->assertNotNull($token->expires);

        $created = $this->dispatchedEvent(RefreshTokenCreatedEvent::class);
        $this->assertSame('1', $created->getRefreshTokenId());
        $this->assertSame('2', $created->getAccessTokenId());
    }

    /**
     * @return void
     */
    public function testCanGetNewRefreshToken(): void
    {
        $repository = new RefreshTokenRepository(new EventManager());

        $token = $repository->getNewRefreshToken();

        $this->assertInstanceOf(RefreshToken::class, $token);
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
        $events->on(RefreshTokenCreatedEvent::NAME, function (EventInterface $event): void {
            $this->dispatched[] = $event;
        });

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
