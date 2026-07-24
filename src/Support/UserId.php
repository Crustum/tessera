<?php
declare(strict_types=1);

namespace Crustum\Tessera\Support;

use Cake\Core\Configure;
use RuntimeException;

/**
 * Resolves Tessera.userIdType for oauth_* persistence and migrations.
 */
class UserId
{
    /**
     * Configured storage type for oauth_* user_id columns.
     *
     * Supported values: `integer` (default), `uuid`, `string`.
     * Set before running Tessera migrations so column types match your users primary key.
     *
     * @return 'integer'|'uuid'|'string'
     */
    public static function type(): string
    {
        $type = Configure::read('Tessera.userIdType', 'integer');
        if (!is_string($type) || !in_array($type, ['integer', 'uuid', 'string'], true)) {
            throw new RuntimeException(
                'Tessera.userIdType must be one of: integer, uuid, string.',
            );
        }

        /** @var 'integer'|'uuid'|'string' $type */
        return $type;
    }

    /**
     * Normalize a user identifier for persistence against oauth_* user_id columns.
     *
     * @param array|string|int|null $userId Raw user identifier
     * @return string|int|null
     */
    public static function normalize(array|string|int|null $userId): int|string|null
    {
        if ($userId === null || $userId === '' || is_array($userId)) {
            return null;
        }

        return match (static::type()) {
            'integer' => (int)$userId,
            'uuid', 'string' => (string)$userId,
        };
    }

    /**
     * Phinx column type and options for oauth_* user_id fields.
     *
     * @param bool $nullable Whether the column may be null
     * @return array{type: string, options: array<string, mixed>}
     */
    public static function migrationColumn(bool $nullable = false): array
    {
        $options = ['null' => $nullable];
        if ($nullable) {
            $options['default'] = null;
        }

        return match (static::type()) {
            'integer' => [
                'type' => 'integer',
                'options' => $options,
            ],
            'uuid' => [
                'type' => 'uuid',
                'options' => $options,
            ],
            'string' => [
                'type' => 'string',
                'options' => $options + ['limit' => 255],
            ],
        };
    }
}
