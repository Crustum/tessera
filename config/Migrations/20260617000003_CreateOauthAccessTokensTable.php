<?php
declare(strict_types=1);

use Crustum\Tessera\Support\UserId;
use Migrations\BaseMigration;

/**
 * Create oauth_access_tokens table.
 */
class CreateOauthAccessTokensTable extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $userId = UserId::migrationColumn(true);

        $table = $this->table('oauth_access_tokens', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('user_id', $userId['type'], $userId['options'])
            ->addColumn('client_id', 'uuid', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('scopes', 'json', ['null' => true, 'default' => null])
            ->addColumn('revoked', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('expires', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['user_id'])
            ->create();
    }
}
