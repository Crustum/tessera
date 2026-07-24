<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\Tessera\Model\Entity\AuthCode;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Model\Entity\DeviceCode;
use Crustum\Tessera\Model\Entity\RefreshToken;
use Crustum\Tessera\Model\Entity\Token;
use Crustum\Tessera\Tessera;
use Crustum\Tessera\Test\TestCase\Authenticator\TokenAuthenticatorTestUser;
use RuntimeException;

/**
 * Tessera static API tests.
 */
class TesseraTest extends TestCase
{
    /**
     * @return void
     */
    public function testScopesCanBeManaged(): void
    {
        Tessera::tokensCan([
            'user' => 'get user information',
        ]);

        $this->assertTrue(Tessera::hasScope('user'));
        $this->assertEquals(['user'], Tessera::scopeIds());
        $this->assertSame('user', Tessera::scopes()[0]->id);
    }

    /**
     * @return void
     */
    public function testAuthCodeInstanceCanBeCreated(): void
    {
        $authCode = Tessera::authCode();

        $this->assertInstanceOf(AuthCode::class, $authCode);
        $this->assertInstanceOf(Tessera::authCodeModel(), $authCode);
    }

    /**
     * @return void
     */
    public function testClientInstanceCanBeCreated(): void
    {
        $client = Tessera::client();

        $this->assertInstanceOf(Client::class, $client);
        $this->assertInstanceOf(Tessera::clientModel(), $client);
    }

    /**
     * @return void
     */
    public function testTokenInstanceCanBeCreated(): void
    {
        $token = Tessera::token();

        $this->assertInstanceOf(Token::class, $token);
        $this->assertInstanceOf(Tessera::tokenModel(), $token);
    }

    /**
     * @return void
     */
    public function testRefreshTokenInstanceCanBeCreated(): void
    {
        $refreshToken = Tessera::refreshToken();

        $this->assertInstanceOf(RefreshToken::class, $refreshToken);
        $this->assertInstanceOf(Tessera::refreshTokenModel(), $refreshToken);
    }

    /**
     * @return void
     */
    public function testRefreshTokenModelCanBeChanged(): void
    {
        Tessera::useRefreshTokenModel(RefreshTokenStub::class);

        $refreshToken = Tessera::refreshToken();

        $this->assertInstanceOf(RefreshTokenStub::class, $refreshToken);
        $this->assertInstanceOf(Tessera::refreshTokenModel(), $refreshToken);

        Tessera::useRefreshTokenModel(RefreshToken::class);
    }

    /**
     * @return void
     */
    public function testDeviceCodeInstanceCanBeCreated(): void
    {
        $deviceCode = Tessera::deviceCode();

        $this->assertInstanceOf(DeviceCode::class, $deviceCode);
        $this->assertInstanceOf(Tessera::deviceCodeModel(), $deviceCode);
    }

    /**
     * @return void
     */
    public function testActingAsRequiresAllowFlag(): void
    {
        Tessera::allowActingAs(false);

        try {
            $this->expectException(RuntimeException::class);
            Tessera::actingAs(new TokenAuthenticatorTestUser());
        } finally {
            Tessera::allowActingAs(true);
            Tessera::clearActingAs();
        }
    }
}
