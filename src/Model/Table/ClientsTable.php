<?php
declare(strict_types=1);

namespace Crustum\Tessera\Model\Table;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Table;
use Cake\Utility\Text;
use Cake\Validation\Validator;
use Crustum\Tessera\Model\Entity\Client;

/**
 * OAuth clients table.
 *
 * @method \Crustum\Tessera\Model\Entity\Client newEmptyEntity()
 * @method \Crustum\Tessera\Model\Entity\Client newEntity(array $data, array $options = [])
 * @method \Crustum\Tessera\Model\Entity\Client|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class ClientsTable extends Table
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

        $this->setTable('oauth_clients');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->setEntityClass(Client::class);

        $this->addBehavior('Timestamp');

        $this->getSchema()->setColumnType('redirect_uris', 'json');
        $this->getSchema()->setColumnType('grant_types', 'json');

        $this->hasMany('Tokens', [
            'className' => TokensTable::class,
            'foreignKey' => 'client_id',
        ]);
        $this->hasMany('AuthCodes', [
            'className' => AuthCodesTable::class,
            'foreignKey' => 'client_id',
        ]);
    }

    /**
     * Assign a UUID primary key when a new client is saved without one.
     *
     * @param \Cake\Event\EventInterface<\Cake\ORM\Table> $event Event
     * @param \Cake\Datasource\EntityInterface $entity Entity
     * @param \ArrayObject<string, mixed> $options Options
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if ($entity->isNew() && !$entity->get('id')) {
            $entity->set('id', Text::uuid());
        }
    }

    /**
     * Default validation rules for OAuth clients.
     *
     * @param \Cake\Validation\Validator $validator Validator
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        return $validator;
    }
}
