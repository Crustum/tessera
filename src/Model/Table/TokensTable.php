<?php
declare(strict_types=1);

namespace Crustum\Tessera\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Crustum\Tessera\Model\Entity\Token;

/**
 * OAuth access tokens table.
 *
 * @method \Crustum\Tessera\Model\Entity\Token newEmptyEntity()
 * @method \Crustum\Tessera\Model\Entity\Token newEntity(array $data, array $options = [])
 * @method \Crustum\Tessera\Model\Entity\Token|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class TokensTable extends Table
{
    /**
     * Initialize table schema, behaviors, and associations.
     *
     * @param array<string, mixed> $config Config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('oauth_access_tokens');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');
        $this->setEntityClass(Token::class);

        $this->addBehavior('Timestamp');

        $this->getSchema()->setColumnType('scopes', 'json');

        $this->belongsTo('Clients', [
            'className' => ClientsTable::class,
            'foreignKey' => 'client_id',
        ]);
        $this->hasOne('RefreshTokens', [
            'className' => RefreshTokensTable::class,
            'foreignKey' => 'access_token_id',
        ]);
    }

    /**
     * Persist a revoked flag for the token entity.
     *
     * @param \Crustum\Tessera\Model\Entity\Token $token Token
     * @return bool
     */
    public function revoke(Token $token): bool
    {
        $token->markRevoked();

        return (bool)$this->save($token);
    }

    /**
     * Default validation rules for access tokens.
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

        $validator
            ->scalar('client_id')
            ->maxLength('client_id', 36)
            ->requirePresence('client_id', 'create')
            ->notEmptyString('client_id');

        return $validator;
    }
}
