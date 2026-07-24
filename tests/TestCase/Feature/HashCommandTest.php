<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleInput;
use Cake\Console\TestSuite\StubConsoleOutput;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Command\HashCommand;
use Crustum\Tessera\Tessera;
use Cake\Utility\Text;

/**
 * HashCommand Feature coverage.
 */
class HashCommandTest extends TesseraTestCase
{
    /**
     * @return void
     */
    public function testItCanProperlyHashClientSecrets(): void
    {
        $plainSecret = Text::uuid() . Text::uuid();
        $client = (new ClientRepository())->createClientCredentialsGrantClient('Hash Client');

        $updated = Tessera::clientsTable()->updateAll(
            ['secret' => $plainSecret],
            ['id' => $client->id],
        );
        $this->assertSame(1, $updated);
        $this->assertSame($plainSecret, Tessera::clientsTable()->get($client->id)->get('secret'));

        $out = new StubConsoleOutput();
        $err = new StubConsoleOutput();
        $io = new ConsoleIo($out, $err, new StubConsoleInput([]));
        $result = (new HashCommand())->execute(
            new Arguments([], ['force' => true], ['force']),
            $io,
        );

        $this->assertSame(HashCommand::CODE_SUCCESS, $result);
        $this->assertStringContainsString(
            'All client secrets were successfully hashed.',
            implode("\n", $out->messages()),
        );

        $hashed = Tessera::clientsTable()->get($client->id)->get('secret');
        $this->assertIsString($hashed);
        $this->assertNotSame($plainSecret, $hashed);
        $this->assertTrue(password_verify($plainSecret, $hashed));
    }
}
