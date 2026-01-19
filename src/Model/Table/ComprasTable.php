<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Table\Traits\FilterableTrait;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class ComprasTable extends Table
{
    use FilterableTrait;
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('compras');
        $this->setDisplayField('compra_number');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        // Asociaciones
        $this->belongsTo('Requesters', [
            'className' => 'Users',
            'foreignKey' => 'requester_id',
            'joinType' => 'INNER',
        ]);

        $this->belongsTo('Assignees', [
            'className' => 'Users',
            'foreignKey' => 'assignee_id',
            'joinType' => 'LEFT',
        ]);

        $this->hasMany('ComprasComments', [
            'foreignKey' => 'compra_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
            'sort' => ['ComprasComments.created' => 'ASC'],
        ]);

        $this->hasMany('ComprasAttachments', [
            'foreignKey' => 'compra_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);

        $this->hasMany('ComprasHistory', [
            'foreignKey' => 'compra_id',
            'dependent' => true,
            'sort' => ['ComprasHistory.created' => 'DESC'],
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('compra_number')
            ->maxLength('compra_number', 20)
            ->requirePresence('compra_number', 'create')
            ->notEmptyString('compra_number')
            ->add('compra_number', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('original_ticket_number')
            ->maxLength('original_ticket_number', 20)
            ->allowEmptyString('original_ticket_number');

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
            ->inList('status', ['nuevo', 'en_revision', 'aprobado', 'en_proceso', 'completado', 'rechazado'])
            ->notEmptyString('status');

        $validator
            ->scalar('priority')
            ->inList('priority', ['baja', 'media', 'alta', 'urgente'])
            ->notEmptyString('priority');

        $validator
            ->integer('requester_id')
            ->notEmptyString('requester_id');

        $validator
            ->integer('assignee_id')
            ->allowEmptyString('assignee_id');

        $validator
            ->scalar('channel')
            ->maxLength('channel', 20)
            ->notEmptyString('channel')
            ->inList('channel', ['email', 'whatsapp']);

        $validator
            ->dateTime('sla_due_date')
            ->allowEmptyDateTime('sla_due_date');

        $validator
            ->dateTime('resolved_at')
            ->allowEmptyDateTime('resolved_at');

        $validator
            ->dateTime('first_response_at')
            ->allowEmptyDateTime('first_response_at');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['compra_number']), ['errorField' => 'compra_number']);
        $rules->add($rules->existsIn(['requester_id'], 'Requesters'), ['errorField' => 'requester_id']);

        // Allow null assignee_id (unassigned compras)
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
     * Genera número único de compra
     * Formato: CPR-{YEAR}-{SEQUENCE}
     * Ejemplo: CPR-2025-00001
     */
    public function generateCompraNumber(): string
    {
        $year = date('Y');
        $prefix = "CPR-{$year}-";

        $lastCompra = $this->find()
            ->select(['compra_number'])
            ->where(['compra_number LIKE' => $prefix . '%'])
            ->order(['compra_number' => 'DESC'])
            ->first();

        if ($lastCompra) {
            $lastNumber = (int)substr($lastCompra->compra_number, -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad((string)$newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get filter configuration for Compras
     *
     * Required by FilterableTrait
     * Resolves: MODEL-001 (findWithFilters duplication)
     *
     * @return array Filter configuration
     */
    protected function getFilterConfig(): array
    {
        return [
            'tableAlias' => 'Compras',
            'numberField' => 'compra_number',
            'resolvedStatuses' => ['completado', 'rechazado', 'convertido'],
            'searchFields' => [
                'Compras.compra_number',
                'Compras.subject',
                'Compras.description',
                'Compras.original_ticket_number',
                'Requesters.name',
                'Requesters.email',
            ],
            'viewConfig' => [], // Use default views from trait
        ];
    }

    /**
     * Custom finder con filtros
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
