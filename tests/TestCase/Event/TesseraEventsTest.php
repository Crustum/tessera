<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Event;

use Cake\TestSuite\TestCase;
use Crustum\Tessera\Event\AccessTokenCreatedEvent;
use Crustum\Tessera\Event\AccessTokenRevokedEvent;
use Crustum\Tessera\Event\RefreshTokenCreatedEvent;

/**
 * Tessera CakePHP Event subclass smoke tests.
 */
class TesseraEventsTest extends TestCase
{
    /**
     * @return void
     */
    public function testAccessTokenCreatedEvent(): void
    {
        $event = new AccessTokenCreatedEvent('tok-1', 'user-1', 'client-1');

        $this->assertSame(AccessTokenCreatedEvent::NAME, $event->getName());
        $this->assertSame('tok-1', $event->getTokenId());
        $this->assertSame('user-1', $event->getUserId());
        $this->assertSame('client-1', $event->getClientId());
    }

    /**
     * @return void
     */
    public function testAccessTokenRevokedEvent(): void
    {
        $event = new AccessTokenRevokedEvent('tok-2');

        $this->assertSame(AccessTokenRevokedEvent::NAME, $event->getName());
        $this->assertSame('tok-2', $event->getTokenId());
    }

    /**
     * @return void
     */
    public function testRefreshTokenCreatedEvent(): void
    {
        $event = new RefreshTokenCreatedEvent('ref-1', 'tok-3');

        $this->assertSame(RefreshTokenCreatedEvent::NAME, $event->getName());
        $this->assertSame('ref-1', $event->getRefreshTokenId());
        $this->assertSame('tok-3', $event->getAccessTokenId());
    }
}
