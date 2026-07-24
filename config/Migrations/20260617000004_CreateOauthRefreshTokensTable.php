<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Create oauth_refresh_tokens table.
 */
class CreateOauthRefreshTokensTable extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('oauth_refresh_tokens', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('access_token_id', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('revoked', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('expires', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['access_token_id'])
            ->create();
    }
}
