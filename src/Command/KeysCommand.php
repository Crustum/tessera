<?php
declare(strict_types=1);

namespace Crustum\Tessera\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Crustum\Tessera\Tessera;
use Override;
use phpseclib4\Crypt\RSA;

/**
 * Generate OAuth encryption keys for Tessera.
 */
class KeysCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'tessera keys';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Create the encryption keys for API authentication';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->addOption('force', [
                'help' => 'Overwrite keys if they already exist',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('length', [
                'help' => 'The length of the private key',
                'default' => '4096',
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $publicKey = Tessera::keyPath('oauth-public.key');
        $privateKey = Tessera::keyPath('oauth-private.key');

        if ((is_file($publicKey) || is_file($privateKey)) && !$args->getOption('force')) {
            $io->error('Encryption keys already exist. Use the --force option to overwrite them.');

            return static::CODE_ERROR;
        }

        $length = (int)$args->getOption('length');
        $key = RSA::createKey($length > 0 ? $length : 4096);

        file_put_contents($publicKey, (string)$key->getPublicKey());
        file_put_contents($privateKey, (string)$key);

        if (DIRECTORY_SEPARATOR !== '\\') {
            chmod($publicKey, 0660);
            chmod($privateKey, 0600);
        }

        $io->success('Encryption keys generated successfully.');

        return static::CODE_SUCCESS;
    }
}
