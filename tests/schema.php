<?php
declare(strict_types=1);

/**
 * Test database schema for Tessera OAuth tables.
 */
return [
    [
        'table' => 'users',
        'columns' => [
            'id' => ['type' => 'integer', 'autoIncrement' => true],
            'email' => ['type' => 'string', 'length' => 255, 'null' => false],
            'password' => ['type' => 'string', 'length' => 255, 'null' => false],
            'created' => ['type' => 'datetime', 'null' => true],
            'modified' => ['type' => 'datetime', 'null' => true],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ],
    [
        'table' => 'oauth_clients',
        'columns' => [
            'id' => ['type' => 'uuid', 'null' => false],
            'owner_id' => ['type' => 'string', 'length' => 36, 'null' => true, 'default' => null],
            'owner_type' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null],
            'name' => ['type' => 'string', 'length' => 255, 'null' => false],
            'secret' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null],
            'provider' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null],
            'redirect_uris' => ['type' => 'json', 'null' => false],
            'grant_types' => ['type' => 'json', 'null' => false],
            'revoked' => ['type' => 'boolean', 'null' => false, 'default' => false],
            'created' => ['type' => 'datetime', 'null' => true],
            'modified' => ['type' => 'datetime', 'null' => true],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
        'indexes' => [
            'oauth_clients_owner_type_owner_id_index' => [
                'type' => 'index',
                'columns' => ['owner_type', 'owner_id'],
            ],
        ],
    ],
    [
        'table' => 'oauth_auth_codes',
        'columns' => [
            'id' => ['type' => 'string', 'length' => 80, 'null' => false],
            'user_id' => ['type' => 'integer', 'null' => false],
            'client_id' => ['type' => 'uuid', 'null' => false],
            'scopes' => ['type' => 'json', 'null' => true, 'default' => null],
            'revoked' => ['type' => 'boolean', 'null' => false, 'default' => false],
            'expires' => ['type' => 'datetime', 'null' => true, 'default' => null],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
        'indexes' => [
            'oauth_auth_codes_user_id_index' => ['type' => 'index', 'columns' => ['user_id']],
        ],
    ],
    [
        'table' => 'oauth_access_tokens',
        'columns' => [
            'id' => ['type' => 'string', 'length' => 80, 'null' => false],
            'user_id' => ['type' => 'integer', 'null' => true, 'default' => null],
            'client_id' => ['type' => 'uuid', 'null' => false],
            'name' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null],
            'scopes' => ['type' => 'json', 'null' => true, 'default' => null],
            'revoked' => ['type' => 'boolean', 'null' => false, 'default' => false],
            'created' => ['type' => 'datetime', 'null' => true],
            'modified' => ['type' => 'datetime', 'null' => true],
            'expires' => ['type' => 'datetime', 'null' => true, 'default' => null],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
        'indexes' => [
            'oauth_access_tokens_user_id_index' => ['type' => 'index', 'columns' => ['user_id']],
        ],
    ],
    [
        'table' => 'oauth_refresh_tokens',
        'columns' => [
            'id' => ['type' => 'string', 'length' => 80, 'null' => false],
            'access_token_id' => ['type' => 'string', 'length' => 80, 'null' => false],
            'revoked' => ['type' => 'boolean', 'null' => false, 'default' => false],
            'expires' => ['type' => 'datetime', 'null' => true, 'default' => null],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
        'indexes' => [
            'oauth_refresh_tokens_access_token_id_index' => [
                'type' => 'index',
                'columns' => ['access_token_id'],
            ],
        ],
    ],
    [
        'table' => 'oauth_device_codes',
        'columns' => [
            'id' => ['type' => 'string', 'length' => 80, 'null' => false],
            'user_id' => ['type' => 'integer', 'null' => true, 'default' => null],
            'client_id' => ['type' => 'uuid', 'null' => false],
            'user_code' => ['type' => 'string', 'length' => 8, 'null' => false],
            'scopes' => ['type' => 'json', 'null' => false],
            'revoked' => ['type' => 'boolean', 'null' => false, 'default' => false],
            'user_approved' => ['type' => 'datetime', 'null' => true, 'default' => null],
            'last_polled' => ['type' => 'datetime', 'null' => true, 'default' => null],
            'expires' => ['type' => 'datetime', 'null' => true, 'default' => null],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'oauth_device_codes_user_code_unique' => [
                'type' => 'unique',
                'columns' => ['user_code'],
            ],
        ],
        'indexes' => [
            'oauth_device_codes_user_id_index' => ['type' => 'index', 'columns' => ['user_id']],
            'oauth_device_codes_client_id_index' => ['type' => 'index', 'columns' => ['client_id']],
        ],
    ],
];
