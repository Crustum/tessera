<?php
declare(strict_types=1);

namespace Crustum\Tessera\Command;

use Cake\Command\Command;
use Cake\Console\ConsoleOptionParser;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;
use Override;

/**
 * Hash plain-text client secrets already stored in the clients table.
 */
class HashCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'tessera hash';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Hash all of the existing secrets in the clients table';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->addOption('force', [
                'help' => 'Force the operation to run without confirmation prompt',
                'boolean' => true,
                'default' => false,
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(): ?int
    {
        if (!$this->args->getOption('force')) {
            $confirm = strtolower($this->io->ask(
                'Are you sure you want to hash all client secrets? This cannot be undone. (yes/no)',
                'no',
            ));
            if (!in_array($confirm, ['yes', 'y'], true)) {
                return static::CODE_SUCCESS;
            }
        }

        $clients = Tessera::clientsTable()->find()
            ->where(['secret IS NOT' => null])
            ->all();
        foreach ($clients as $client) {
            if (!$client instanceof Client) {
                continue;
            }

            $secret = $client->get('secret');
            if (!is_string($secret)) {
                continue;
            }

            if ($secret === '') {
                continue;
            }

            if ($this->isPasswordHash($secret)) {
                continue;
            }

            Tessera::clientsTable()->updateAll(
                ['secret' => password_hash($secret, PASSWORD_BCRYPT)],
                ['id' => $client->id],
            );
        }

        $this->io->success('All client secrets were successfully hashed.');

        return static::CODE_SUCCESS;
    }

    /**
     * Whether the value looks like a password_hash() digest.
     *
     * @param string $value Secret value
     * @return bool
     */
    protected function isPasswordHash(string $value): bool
    {
        $info = password_get_info($value);

        return $info['algoName'] !== 'unknown';
    }
}
