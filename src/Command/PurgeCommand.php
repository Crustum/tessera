<?php
declare(strict_types=1);

namespace Crustum\Tessera\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\DateTime;
use Crustum\Tessera\Tessera;
use Override;

/**
 * Purge revoked and/or expired OAuth tokens and codes.
 */
class PurgeCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'tessera purge';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Purge revoked and / or expired tokens and authentication codes';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->addOption('revoked', [
                'help' => 'Only purge revoked tokens and authentication codes',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('expired', [
                'help' => 'Only purge expired tokens and authentication codes',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('hours', [
                'help' => 'The number of hours to retain expired tokens',
                'default' => '168',
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $revokedOnly = (bool)$args->getOption('revoked');
        $expiredOnly = (bool)$args->getOption('expired');
        $purgeRevoked = $revokedOnly || !$expiredOnly;
        $purgeExpired = $expiredOnly || !$revokedOnly;

        $expiredBefore = $purgeExpired
            ? DateTime::now()->subHours((int)$args->getOption('hours'))
            : null;

        $conditions = $this->purgeConditions($purgeRevoked, $expiredBefore);

        Tessera::tokensTable()->deleteAll($conditions);
        Tessera::authCodesTable()->deleteAll($conditions);
        Tessera::refreshTokensTable()->deleteAll($conditions);

        if (Tessera::$deviceCodeGrantEnabled) {
            Tessera::deviceCodesTable()->deleteAll($conditions);
        }

        $parts = array_filter([
            $purgeRevoked ? 'revoked items' : null,
            $expiredBefore instanceof DateTime
                ? 'items expired for more than ' . $expiredBefore->diffForHumans(null, true)
                : null,
        ]);

        $io->success('Purged ' . implode(' and ', $parts) . '.');

        return static::CODE_SUCCESS;
    }

    /**
     * Build OR conditions for revoked and/or expired rows.
     *
     * @param bool $purgeRevoked Whether to include revoked rows
     * @param \Cake\I18n\DateTime|null $expiredBefore Expire cutoff when purging expired
     * @return array<string, mixed>
     */
    protected function purgeConditions(bool $purgeRevoked, ?DateTime $expiredBefore): array
    {
        $or = [];

        if ($purgeRevoked) {
            $or[] = ['revoked' => true];
        }

        if ($expiredBefore instanceof DateTime) {
            $or[] = ['expires <' => $expiredBefore];
        }

        if ($or === []) {
            return ['1 =' => 0];
        }

        if (count($or) === 1) {
            return $or[0];
        }

        return ['OR' => $or];
    }
}
