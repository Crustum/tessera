<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Bridge;

use Cake\Event\EventManagerInterface;
use Cake\TestSuite\TestCase;
use Crustum\Tessera\Bridge\AccessToken;
use Crustum\Tessera\Bridge\Client;
use Crustum\Tessera\Bridge\RefreshToken;
use Crustum\Tessera\Bridge\RefreshTokenRepository;
use Crustum\Tessera\Tessera;
use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * Bridge refresh token repository feature tests.
 */
class BridgeRefreshTokenRepositoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testAccessTokensCanBePersisted(): void
    {
        $expiration = new DateTimeImmutable();

        $events = Mockery::mock(EventManagerInterface::class);
        $events->shouldReceive('dispatch')->once();

        $accessToken = new AccessToken('3', [], Mockery::mock(Client::class));
        $accessToken->setIdentifier('2');

        $refreshToken = new RefreshToken();
        $refreshToken->setIdentifier('1');
        $refreshToken->setExpiryDateTime($expiration);
        $refreshToken->setAccessToken($accessToken);

        $repository = new RefreshTokenRepository($events);
        $repository->persistNewRefreshToken($refreshToken);

        $token = Tessera::refreshTokensTable()->get('1');
        $this->assertSame('2', $token->access_token_id);
        $this->assertFalse($token->revoked);
        $this->assertNotNull($token->expires);
    }

    /**
     * @return void
     */
    public function testCanGetNewRefreshToken(): void
    {
        $events = Mockery::mock(EventManagerInterface::class);
        $repository = new RefreshTokenRepository($events);

        $token = $repository->getNewRefreshToken();

        $this->assertInstanceOf(RefreshToken::class, $token);
    }
}
