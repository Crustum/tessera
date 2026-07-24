<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Create oauth_clients table.
 */
class CreateOauthClientsTable extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('oauth_clients', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('owner_id', 'string', ['limit' => 36, 'null' => true, 'default' => null])
            ->addColumn('owner_type', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('secret', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('provider', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('redirect_uris', 'json', ['null' => false])
            ->addColumn('grant_types', 'json', ['null' => false])
            ->addColumn('revoked', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['owner_type', 'owner_id'])
            ->create();
    }
}
