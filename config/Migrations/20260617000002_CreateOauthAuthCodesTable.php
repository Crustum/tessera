<?php
declare(strict_types=1);

use Crustum\Tessera\Support\UserId;
use Migrations\BaseMigration;

/**
 * Create oauth_auth_codes table.
 */
class CreateOauthAuthCodesTable extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $userId = UserId::migrationColumn(false);

        $table = $this->table('oauth_auth_codes', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('user_id', $userId['type'], $userId['options'])
            ->addColumn('client_id', 'uuid', ['null' => false])
            ->addColumn('scopes', 'json', ['null' => true, 'default' => null])
            ->addColumn('revoked', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('expires', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['user_id'])
            ->create();
    }
}
