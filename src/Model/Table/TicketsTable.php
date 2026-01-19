<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Table\Traits\FilterableTrait;
use App\Model\Table\Traits\NumberGeneratorTrait;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Tickets Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Requesters
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Assignees
 * @property \App\Model\Table\AttachmentsTable&\Cake\ORM\Association\HasMany $Attachments
 * @property \App\Model\Table\TicketCommentsTable&\Cake\ORM\Association\HasMany $TicketComments
 * @property \App\Model\Table\TicketFollowersTable&\Cake\ORM\Association\HasMany $TicketFollowers
 * @property \App\Model\Table\TicketTagsTable&\Cake\ORM\Association\HasMany $TicketTags
 *
 * @method \App\Model\Entity\Ticket newEmptyEntity()
 * @method \App\Model\Entity\Ticket newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Ticket> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Ticket get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Ticket findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Ticket patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Ticket> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Ticket|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Ticket saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Ticket>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Ticket>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Ticket>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Ticket> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Ticket>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Ticket>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Ticket>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Ticket> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class TicketsTable extends Table
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

        $this->setTable('tickets');
        $this->setDisplayField('ticket_number');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Requesters', [
            'foreignKey' => 'requester_id',
            'className' => 'Users',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Assignees', [
            'foreignKey' => 'assignee_id',
            'className' => 'Users',
        ]);
        $this->hasMany('Attachments', [
            'foreignKey' => 'ticket_id',
        ]);
        $this->hasMany('TicketComments', [
            'foreignKey' => 'ticket_id',
        ]);
        $this->hasMany('TicketFollowers', [
            'foreignKey' => 'ticket_id',
        ]);
        $this->hasMany('TicketTags', [
            'foreignKey' => 'ticket_id',
        ]);
        $this->hasMany('TicketHistory', [
            'foreignKey' => 'ticket_id',
            'sort' => ['TicketHistory.created' => 'DESC'],
        ]);
        $this->belongsToMany('Tags', [
            'foreignKey' => 'ticket_id',
            'targetForeignKey' => 'tag_id',
            'joinTable' => 'ticket_tags',
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
            ->scalar('ticket_number')
            ->maxLength('ticket_number', 20)
            ->requirePresence('ticket_number', 'create')
            ->notEmptyString('ticket_number')
            ->add('ticket_number', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('gmail_message_id')
            ->maxLength('gmail_message_id', 255)
            ->allowEmptyString('gmail_message_id')
            ->add('gmail_message_id', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        // Note: email_to and email_cc accept arrays (converted to JSON by entity setters)
        // No validation rules needed - let the entity handle it

        $validator
            ->scalar('gmail_thread_id')
            ->maxLength('gmail_thread_id', 255)
            ->allowEmptyString('gmail_thread_id');

        $validator
            ->scalar('subject')
            ->maxLength('subject', 255)
            ->requirePresence('subject', 'create')
            ->notEmptyString('subject');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->scalar('status')
            ->maxLength('status', 20)
            ->notEmptyString('status');

        $validator
            ->scalar('priority')
            ->maxLength('priority', 20)
            ->notEmptyString('priority');

        $validator
            ->integer('requester_id')
            ->notEmptyString('requester_id');

        $validator
            ->integer('assignee_id')
            ->allowEmptyString('assignee_id');

        $validator
            ->scalar('channel')
            ->maxLength('channel', 50)
            ->notEmptyString('channel');

        $validator
            ->scalar('source_email')
            ->maxLength('source_email', 255)
            ->allowEmptyString('source_email');

        $validator
            ->dateTime('resolved_at')
            ->allowEmptyDateTime('resolved_at');

        $validator
            ->dateTime('first_response_at')
            ->allowEmptyDateTime('first_response_at');

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
        $rules->add($rules->isUnique(['ticket_number']), ['errorField' => 'ticket_number']);
        $rules->add($rules->isUnique(['gmail_message_id'], ['allowMultipleNulls' => true]), ['errorField' => 'gmail_message_id']);
        $rules->add($rules->existsIn(['requester_id'], 'Requesters'), ['errorField' => 'requester_id']);

        // Allow null assignee_id (unassigned tickets)
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
     * Get the prefix for ticket numbers
     *
     * Required by NumberGeneratorTrait
     * Resolves: MODEL-002 (generateXXXNumber() duplication)
     *
     * @return string
     */
    protected function getNumberPrefix(): string
    {
        return 'TKT';
    }

    /**
     * Get the field name for ticket numbers
     *
     * Required by NumberGeneratorTrait
     * Resolves: MODEL-002 (generateXXXNumber() duplication)
     *
     * @return string
     */
    protected function getNumberField(): string
    {
        return 'ticket_number';
    }

    /**
     * Generate unique ticket number in format TKT-YYYY-NNNNN
     *
     * Wrapper for backward compatibility.
     * Uses NumberGeneratorTrait::generateNumber() internally.
     *
     * @return string
     */
    public function generateTicketNumber(): string
    {
        return $this->generateNumber();
    }

    /**
     * Get filter configuration for Tickets
     *
     * Required by FilterableTrait
     * Resolves: MODEL-001 (findWithFilters duplication)
     *
     * @return array Filter configuration
     */
    protected function getFilterConfig(): array
    {
        return [
            'tableAlias' => 'Tickets',
            'numberField' => 'ticket_number',
            'resolvedStatuses' => ['resuelto', 'convertido'],
            'searchFields' => [
                'Tickets.ticket_number',
                'Tickets.subject',
                'Tickets.description',
                'Tickets.source_email',
                'Requesters.name',
                'Requesters.email',
            ],
            'viewConfig' => [], // Use default views from trait
        ];
    }

    /**
     * Find tickets with filters
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
}
