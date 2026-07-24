<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;

/**
 * KeysCommand Feature coverage.
 */
class KeysCommandTest extends TesseraTestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @return void
     */
    public function testPrivateAndPublicKeysAreGenerated(): void
    {
        $this->assertFileExists(self::PUBLIC_KEY);
        $this->assertFileExists(self::PRIVATE_KEY);
    }

    /**
     * @return void
     */
    public function testPrivateAndPublicKeysShouldNotBeGeneratedTwice(): void
    {
        $this->exec('tessera keys');
        $this->assertExitError();
        $this->assertErrorContains(
            'Encryption keys already exist. Use the --force option to overwrite them.',
        );
    }
}
