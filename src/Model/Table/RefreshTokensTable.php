<?php
declare(strict_types=1);

namespace Crustum\Tessera\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Crustum\Tessera\Model\Entity\RefreshToken;

/**
 * OAuth refresh tokens table.
 *
 * @method \Crustum\Tessera\Model\Entity\RefreshToken newEmptyEntity()
 * @method \Crustum\Tessera\Model\Entity\RefreshToken newEntity(array $data, array $options = [])
 * @method \Crustum\Tessera\Model\Entity\RefreshToken|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class RefreshTokensTable extends Table
{
    /**
     * Initialize table schema and associations.
     *
     * @param array<string, mixed> $config Config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('oauth_refresh_tokens');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');
        $this->setEntityClass(RefreshToken::class);

        $this->belongsTo('Tokens', [
            'className' => TokensTable::class,
            'foreignKey' => 'access_token_id',
        ]);
    }

    /**
     * Persist a revoked flag for the refresh token entity.
     *
     * @param \Crustum\Tessera\Model\Entity\RefreshToken $token Refresh token
     * @return bool
     */
    public function revoke(RefreshToken $token): bool
    {
        $token->markRevoked();

        return (bool)$this->save($token);
    }

    /**
     * Default validation rules for refresh tokens.
     *
     * @param \Cake\Validation\Validator $validator Validator
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('id')
            ->maxLength('id', 80)
            ->requirePresence('id', 'create')
            ->notEmptyString('id');

        return $validator;
    }
}
