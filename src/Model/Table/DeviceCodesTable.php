<?php
declare(strict_types=1);

namespace Crustum\Tessera\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Crustum\Tessera\Model\Entity\DeviceCode;

/**
 * OAuth device codes table.
 *
 * @method \Crustum\Tessera\Model\Entity\DeviceCode newEmptyEntity()
 * @method \Crustum\Tessera\Model\Entity\DeviceCode newEntity(array $data, array $options = [])
 * @method \Crustum\Tessera\Model\Entity\DeviceCode|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class DeviceCodesTable extends Table
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

        $this->setTable('oauth_device_codes');
        $this->setDisplayField('user_code');
        $this->setPrimaryKey('id');
        $this->setEntityClass(DeviceCode::class);

        $this->getSchema()->setColumnType('scopes', 'json');

        $this->belongsTo('Clients', [
            'className' => ClientsTable::class,
            'foreignKey' => 'client_id',
        ]);
    }

    /**
     * Default validation rules for device codes.
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
            ->scalar('user_code')
            ->maxLength('user_code', 8)
            ->requirePresence('user_code', 'create')
            ->notEmptyString('user_code');

        return $validator;
    }
}
