<?php
declare(strict_types=1);

use Crustum\Tessera\Support\UserId;
use Migrations\BaseMigration;

/**
 * Create oauth_device_codes table.
 */
class CreateOauthDeviceCodesTable extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $userId = UserId::migrationColumn(true);

        $table = $this->table('oauth_device_codes', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('user_id', $userId['type'], $userId['options'])
            ->addColumn('client_id', 'uuid', ['null' => false])
            ->addColumn('user_code', 'string', ['limit' => 8, 'null' => false])
            ->addColumn('scopes', 'json', ['null' => false])
            ->addColumn('revoked', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('user_approved', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('last_polled', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('expires', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['user_id'])
            ->addIndex(['client_id'])
            ->addIndex(['user_code'], ['unique' => true])
            ->create();
    }
}
