<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\I18n\DateTime;
use Cake\Utility\Text;
use Crustum\Tessera\Tessera;

/**
 * PurgeCommand Feature coverage.
 */
class PurgeCommandTest extends TesseraTestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        DateTime::setTestNow();
        Tessera::$deviceCodeGrantEnabled = true;

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testItCanPurgeTokens(): void
    {
        DateTime::setTestNow('2000-01-08 00:00:00');
        $this->seedPurgeRows(revoked: true, expiredAt: '1999-12-31 00:00:00');
        $this->seedPurgeRows(revoked: false, expiredAt: '2000-01-10 00:00:00');

        $this->exec('tessera purge');
        $this->assertExitSuccess();
        $this->assertOutputContains('Purged revoked items');
        $this->assertOutputContains('expired');

        $this->assertSame(1, Tessera::tokensTable()->find()->count());
        $this->assertSame(1, Tessera::authCodesTable()->find()->count());
        $this->assertSame(1, Tessera::refreshTokensTable()->find()->count());
        $this->assertSame(1, Tessera::deviceCodesTable()->find()->count());
    }

    /**
     * @return void
     */
    public function testItCanPurgeRevokedTokens(): void
    {
        $this->seedPurgeRows(revoked: true, expiredAt: '2000-01-10 00:00:00');
        $this->seedPurgeRows(revoked: false, expiredAt: '2000-01-10 00:00:00');

        $this->exec('tessera purge --revoked');
        $this->assertExitSuccess();
        $this->assertOutputContains('Purged revoked items.');

        $this->assertSame(1, Tessera::tokensTable()->find()->count());
        $this->assertSame(0, Tessera::tokensTable()->find()->where(['revoked' => true])->count());
    }

    /**
     * @return void
     */
    public function testItCanPurgeExpiredTokens(): void
    {
        DateTime::setTestNow('2000-01-08 00:00:00');
        $this->seedPurgeRows(revoked: false, expiredAt: '1999-12-31 00:00:00');
        $this->seedPurgeRows(revoked: false, expiredAt: '2000-01-10 00:00:00');

        $this->exec('tessera purge --expired');
        $this->assertExitSuccess();
        $this->assertOutputContains('Purged items expired');

        $this->assertSame(1, Tessera::tokensTable()->find()->count());
        $this->assertSame(1, Tessera::authCodesTable()->find()->count());
        $this->assertSame(1, Tessera::refreshTokensTable()->find()->count());
        $this->assertSame(1, Tessera::deviceCodesTable()->find()->count());
    }

    /**
     * @return void
     */
    public function testItCanPurgeRevokedAndExpiredTokens(): void
    {
        DateTime::setTestNow('2000-01-08 00:00:00');
        $this->seedPurgeRows(revoked: true, expiredAt: '2000-01-10 00:00:00');
        $this->seedPurgeRows(revoked: false, expiredAt: '1999-12-31 00:00:00');
        $this->seedPurgeRows(revoked: false, expiredAt: '2000-01-10 00:00:00');

        $this->exec('tessera purge --revoked --expired');
        $this->assertExitSuccess();
        $this->assertOutputContains('Purged revoked items');
        $this->assertOutputContains('expired');

        $this->assertSame(1, Tessera::tokensTable()->find()->count());
    }

    /**
     * @return void
     */
    public function testItCanPurgeTokensByHours(): void
    {
        DateTime::setTestNow('2000-01-01 02:00:00');
        $this->seedPurgeRows(revoked: false, expiredAt: '1999-12-31 23:00:00');
        $this->seedPurgeRows(revoked: false, expiredAt: '2000-01-01 01:30:00');

        $this->exec('tessera purge --hours 2');
        $this->assertExitSuccess();
        $this->assertOutputContains('Purged revoked items');
        $this->assertOutputContains('expired');

        $this->assertSame(1, Tessera::tokensTable()->find()->count());
    }

    /**
     * Insert one revoked/expired set of OAuth rows for purge assertions.
     *
     * @param bool $revoked Whether rows are revoked
     * @param string $expiredAt Expiry datetime
     * @return void
     */
    protected function seedPurgeRows(bool $revoked, string $expiredAt): void
    {
        $clientId = Text::uuid();
        $tokenId = Text::uuid();
        $expires = new DateTime($expiredAt);

        Tessera::clientsTable()->saveOrFail(Tessera::clientsTable()->newEntity([
            'id' => $clientId,
            'name' => 'Purge Client',
            'secret' => null,
            'provider' => null,
            'revoked' => false,
            'redirect_uris' => [],
            'grant_types' => ['client_credentials'],
        ], ['guard' => false]));

        $token = Tessera::tokensTable()->newEmptyEntity();
        $token->patch([
            'id' => $tokenId,
            'user_id' => null,
            'client_id' => $clientId,
            'name' => null,
            'scopes' => [],
            'revoked' => $revoked,
            'expires' => $expires,
        ], ['guard' => false]);
        Tessera::tokensTable()->saveOrFail($token);

        $authCode = Tessera::authCodesTable()->newEmptyEntity();
        $authCode->patch([
            'id' => Text::uuid(),
            'user_id' => 1,
            'client_id' => $clientId,
            'scopes' => [],
            'revoked' => $revoked,
            'expires' => $expires,
        ], ['guard' => false]);
        Tessera::authCodesTable()->saveOrFail($authCode);

        $refresh = Tessera::refreshTokensTable()->newEmptyEntity();
        $refresh->patch([
            'id' => Text::uuid(),
            'access_token_id' => $tokenId,
            'revoked' => $revoked,
            'expires' => $expires,
        ], ['guard' => false]);
        Tessera::refreshTokensTable()->saveOrFail($refresh);

        $device = Tessera::deviceCodesTable()->newEmptyEntity();
        $device->patch([
            'id' => Text::uuid(),
            'user_id' => null,
            'client_id' => $clientId,
            'user_code' => strtoupper(substr(str_replace('-', '', Text::uuid()), 0, 8)),
            'scopes' => [],
            'revoked' => $revoked,
            'expires' => $expires,
        ], ['guard' => false]);
        Tessera::deviceCodesTable()->saveOrFail($device);
    }
}
