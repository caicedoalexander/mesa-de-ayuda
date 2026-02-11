<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Table\Traits\FilterableTrait;
use App\Model\Table\Traits\NumberGeneratorTrait;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use App\Model\Enum\Channel;
use App\Model\Enum\PqrsStatus;
use App\Model\Enum\Priority;
use Cake\Validation\Validator;

/**
 * Pqrs Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Assignees
 * @property \App\Model\Table\PqrsCommentsTable&\Cake\ORM\Association\HasMany $PqrsComments
 * @property \App\Model\Table\PqrsAttachmentsTable&\Cake\ORM\Association\HasMany $PqrsAttachments
 * @property \App\Model\Table\PqrsHistoryTable&\Cake\ORM\Association\HasMany $PqrsHistory
 *
 * @method \App\Model\Entity\Pqr newEmptyEntity()
 * @method \App\Model\Entity\Pqr newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Pqr> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Pqr get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Pqr findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Pqr patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Pqr> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Pqr|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Pqr saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Pqr>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Pqr>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Pqr>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Pqr> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Pqr>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Pqr>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Pqr>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Pqr> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class PqrsTable extends Table
{
    use FilterableTrait;
    use NumberGeneratorTrait;

    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('pqrs');
        $this->setDisplayField('pqrs_number');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Assignees', [
            'className' => 'Users',
            'foreignKey' => 'assignee_id',
            'joinType' => 'LEFT',
        ]);
        $this->hasMany('PqrsComments', [
            'foreignKey' => 'pqrs_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
            'sort' => ['PqrsComments.created' => 'ASC'],
        ]);
        $this->hasMany('PqrsAttachments', [
            'foreignKey' => 'pqrs_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);
        $this->hasMany('PqrsHistory', [
            'foreignKey' => 'pqrs_id',
            'dependent' => true,
            'sort' => ['PqrsHistory.created' => 'DESC'],
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('pqrs_number')
            ->maxLength('pqrs_number', 20)
            ->requirePresence('pqrs_number', 'create')
            ->notEmptyString('pqrs_number')
            ->add('pqrs_number', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('type')
            ->maxLength('type', 20)
            ->requirePresence('type', 'create')
            ->notEmptyString('type')
            ->inList('type', ['peticion', 'queja', 'reclamo', 'sugerencia']);

        $validator
            ->scalar('subject')
            ->maxLength('subject', 255)
            ->requirePresence('subject', 'create')
            ->notEmptyString('subject');

        $validator
            ->scalar('description')
            ->requirePresence('description', 'create')
            ->notEmptyString('description');

        $validator
            ->scalar('status')
            ->maxLength('status', 20)
            ->requirePresence('status', 'create')
            ->notEmptyString('status')
            ->inList('status', PqrsStatus::values());

        $validator
            ->scalar('priority')
            ->maxLength('priority', 20)
            ->requirePresence('priority', 'create')
            ->notEmptyString('priority')
            ->inList('priority', Priority::values());

        $validator
            ->scalar('requester_name')
            ->maxLength('requester_name', 255)
            ->requirePresence('requester_name', 'create')
            ->notEmptyString('requester_name');

        $validator
            ->email('requester_email')
            ->requirePresence('requester_email', 'create')
            ->notEmptyString('requester_email');

        $validator
            ->scalar('requester_phone')
            ->maxLength('requester_phone', 50)
            ->allowEmptyString('requester_phone');

        $validator
            ->scalar('requester_id_number')
            ->maxLength('requester_id_number', 50)
            ->allowEmptyString('requester_id_number');

        $validator
            ->scalar('requester_address')
            ->allowEmptyString('requester_address');

        $validator
            ->scalar('requester_city')
            ->maxLength('requester_city', 100)
            ->allowEmptyString('requester_city');

        $validator
            ->scalar('channel')
            ->maxLength('channel', 20)
            ->notEmptyString('channel')
            ->inList('channel', [Channel::Web->value, Channel::Whatsapp->value]);

        $validator
            ->integer('assignee_id')
            ->allowEmptyString('assignee_id');

        $validator
            ->scalar('source_url')
            ->maxLength('source_url', 500)
            ->allowEmptyString('source_url');

        $validator
            ->scalar('ip_address')
            ->maxLength('ip_address', 45)
            ->allowEmptyString('ip_address');

        $validator
            ->scalar('user_agent')
            ->allowEmptyString('user_agent');

        $validator
            ->dateTime('resolved_at')
            ->allowEmptyDateTime('resolved_at');

        $validator
            ->dateTime('first_response_at')
            ->allowEmptyDateTime('first_response_at');

        $validator
            ->dateTime('closed_at')
            ->allowEmptyDateTime('closed_at');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['pqrs_number']), ['errorField' => 'pqrs_number']);

        // Allow null assignee_id (unassigned PQRS)
        $rules->add(
            $rules->existsIn(['assignee_id'], 'Assignees'),
            [
                'errorField' => 'assignee_id',
                'allowNullableNulls' => true
            ]
        );

        return $rules;
    }

    /**
     * Get filter configuration for PQRS
     *
     * Required by FilterableTrait
     * Resolves: MODEL-001 (findWithFilters duplication)
     *
     * @return array Filter configuration
     */
    protected function getFilterConfig(): array
    {
        return [
            'tableAlias' => 'Pqrs',
            'numberField' => 'pqrs_number',
            'resolvedStatuses' => PqrsStatus::resolvedValues(),
            'searchFields' => [
                'Pqrs.pqrs_number',
                'Pqrs.subject',
                'Pqrs.requester_name',
                'Pqrs.requester_email',
                'Pqrs.description',
            ],
            'viewConfig' => [], // Use default views from trait
        ];
    }

    /**
     * Find PQRS with filters
     *
     * Refactored to use FilterableTrait for DRY code.
     * Resolves: MODEL-001 (findWithFilters duplication)
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query object
     * @param array $options Filter options
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findWithFilters(SelectQuery $query, array $options): SelectQuery
    {
        $filters = $options['filters'] ?? [];
        $view = $options['view'] ?? 'todos_sin_resolver';
        $user = $options['user'] ?? null;

        return $this->applyGenericFilters($query, $filters, $view, $user);
    }

    /**
     * Get the prefix for PQRS numbers
     *
     * Required by NumberGeneratorTrait
     * Resolves: MODEL-002 (generateXXXNumber() duplication)
     *
     * @return string
     */
    protected function getNumberPrefix(): string
    {
        return 'PQRS';
    }

    /**
     * Get the field name for PQRS numbers
     *
     * Required by NumberGeneratorTrait
     * Resolves: MODEL-002 (generateXXXNumber() duplication)
     *
     * @return string
     */
    protected function getNumberField(): string
    {
        return 'pqrs_number';
    }

    /**
     * Generate next PQRS number
     *
     * Wrapper for backward compatibility.
     * Uses NumberGeneratorTrait::generateNumber() internally.
     *
     * @return string Format: PQRS-2025-00001
     */
    public function generatePqrsNumber(): string
    {
        return $this->generateNumber();
    }
}
