<?php
declare(strict_types=1);

namespace Crustum\Tessera\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Crustum\Tessera\Model\Entity\AuthCode;

/**
 * OAuth authorization codes table.
 *
 * @method \Crustum\Tessera\Model\Entity\AuthCode newEmptyEntity()
 * @method \Crustum\Tessera\Model\Entity\AuthCode newEntity(array $data, array $options = [])
 * @method \Crustum\Tessera\Model\Entity\AuthCode|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class AuthCodesTable extends Table
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

        $this->setTable('oauth_auth_codes');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');
        $this->setEntityClass(AuthCode::class);

        $this->getSchema()->setColumnType('scopes', 'json');

        $this->belongsTo('Clients', [
            'className' => ClientsTable::class,
            'foreignKey' => 'client_id',
        ]);
    }

    /**
     * Default validation rules for authorization codes.
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
