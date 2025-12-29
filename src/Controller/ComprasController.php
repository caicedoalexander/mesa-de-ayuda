<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\StatisticsControllerTrait;
use App\Controller\Traits\TicketSystemControllerTrait;
use App\Service\ComprasService;
use App\Service\ResponseService;
use App\Service\StatisticsService;
use Cake\Event\EventInterface;

/**
 * Compras Controller
 *
 * Sistema de gestión de compras convertidas desde tickets
 * Solo accesible para roles: admin y compras
 *
 * @property \App\Model\Table\ComprasTable $Compras
 */
class ComprasController extends AppController
{
    use StatisticsControllerTrait;
    use TicketSystemControllerTrait;

    private ComprasService $comprasService;
    private ResponseService $responseService;
    private StatisticsService $statisticsService;

    /**
     * beforeFilter callback - Restrict access to admin and compras roles only
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);

        $user = $this->Authentication->getIdentity();
        $allowedRoles = ['admin', 'compras'];

        if ($user && !in_array($user->get('role'), $allowedRoles)) {
            $role = $user->get('role');
            $this->Flash->error(__('No tienes permiso para acceder al módulo de compras.'));

            // Redirect to appropriate module based on role
            if ($role === 'servicio_cliente') {
                return $this->redirect(['controller' => 'Pqrs', 'action' => 'index']);
            } else {
                // agent, requester, and others go to Tickets
                return $this->redirect(['controller' => 'Tickets', 'action' => 'index']);
            }
        }
    }

    /**
     * Initialize
     */
    public function initialize(): void
    {
        parent::initialize();

        $systemConfig = $this->viewBuilder()->getVar('systemConfig');
        $this->comprasService = new ComprasService($systemConfig);
        $this->responseService = new ResponseService($systemConfig);
        $this->statisticsService = new StatisticsService();
    }

    /**
     * Index method - List compras with filters
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->indexEntity('compra', [
            'paginationLimit' => 25,
        ]);
    }

    /**
     * View method - Display individual compra
     *
     * @param int|null $id Compra id
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function view($id = null)
    {
        return $this->viewEntity('compra', (int)$id, [
            'lazyLoadHistory' => true, // PERFORMANCE FIX: Load history via AJAX
            'beforeSet' => function($compra, $viewVars) {
                // Check SLA status
                $isSLABreached = $this->comprasService->isSLABreached($compra);

                // Get users for assignment with formatted names
                $comprasUsers = $this->fetchTable('Users')->find()
                    ->where(['role' => 'compras', 'is_active' => true])
                    ->orderBy(['first_name' => 'ASC', 'last_name' => 'ASC'])
                    ->all()
                    ->combine('id', function ($user) {
                        return $user->first_name . ' ' . $user->last_name;
                    })
                    ->toArray();

                $priorities = [
                    'baja' => 'Baja',
                    'media' => 'Media',
                    'alta' => 'Alta',
                    'urgente' => 'Urgente',
                ];

                // Status configuration for reply editor (with icon, color, label)
                $statuses = [
                    'nuevo' => ['icon' => 'bi-circle-fill', 'color' => 'info', 'label' => 'Nuevo'],
                    'en_revision' => ['icon' => 'bi-circle-fill', 'color' => 'warning', 'label' => 'En Revisión'],
                    'aprobado' => ['icon' => 'bi-circle-fill', 'color' => 'success', 'label' => 'Aprobado'],
                    'en_proceso' => ['icon' => 'bi-circle-fill', 'color' => 'primary', 'label' => 'En Proceso'],
                    'completado' => ['icon' => 'bi-circle-fill', 'color' => 'success', 'label' => 'Completado'],
                    'rechazado' => ['icon' => 'bi-circle-fill', 'color' => 'danger', 'label' => 'Rechazado'],
                ];

                // Merge with existing view vars
                return array_merge($viewVars, compact('comprasUsers', 'priorities', 'statuses', 'isSLABreached'));
            },
        ]);
    }

    /**
     * Assign compra to user (uses trait method)
     *
     * @param int|null $id Compra id
     * @return \Cake\Http\Response|null
     */
    public function assign($id = null)
    {
        return $this->assignEntity('compra', (int)$id, $this->request->getData('agent_id'));
    }

    /**
     * Change compra status (uses trait method)
     *
     * @param int|null $id Compra id
     * @return \Cake\Http\Response|null
     */
    public function changeStatus($id = null)
    {
        return $this->changeEntityStatus('compra', (int)$id, $this->request->getData('status'));
    }

    /**
     * Change compra priority (uses trait method)
     *
     * @param int|null $id Compra id
     * @return \Cake\Http\Response|null
     */
    public function changePriority($id = null)
    {
        return $this->changeEntityPriority('compra', (int)$id, $this->request->getData('priority'));
    }

    /**
     * Add comment to compra (uses trait method)
     *
     * @param int|null $id Compra id
     * @return \Cake\Http\Response|null
     */
    public function addComment($id = null)
    {
        return $this->addEntityComment('compra', (int)$id);
    }

    /**
     * Download attachment (uses trait method)
     *
     * @param int|null $id Attachment id
     * @return \Cake\Http\Response
     */
    public function download($id = null)
    {
        return $this->downloadEntityAttachment('compra', (int)$id);
    }

    /**
     * AJAX endpoint for lazy loading compra history
     * PERFORMANCE FIX: Only loads when history tab is opened
     *
     * @param string|null $id Compra id
     * @return void JSON response
     */
    public function history($id = null)
    {
        $this->historyEntity('compra', (int)$id);
    }

    /**
     * Bulk assign compras to agents
     *
     * @return \Cake\Http\Response|null|void Redirects on success
     */
    public function bulkAssign()
    {
        return $this->bulkAssignEntity('compra');
    }

    /**
     * Bulk change priority of compras
     *
     * @return \Cake\Http\Response|null|void Redirects on success
     */
    public function bulkChangePriority()
    {
        return $this->bulkChangeEntityPriority('compra');
    }

    /**
     * Bulk delete compras
     *
     * @return \Cake\Http\Response|null|void Redirects on success
     */
    public function bulkDelete()
    {
        return $this->bulkDeleteEntity('compra');
    }

    /**
     * Convert compra to ticket
     *
     * @param int|null $id Compra id
     * @return \Cake\Http\Response|null Redirects to ticket view
     */
    public function convertToTicket($id = null)
    {
        $this->request->allowMethod(['post']);

        $user = $this->Authentication->getIdentity();
        // Allow admin and compras users to convert
        $allowedRoles = ['admin', 'compras'];
        if (!$user || !in_array($user->role, $allowedRoles)) {
            $this->Flash->error(__('No tienes permiso para esta acción.'));
            return $this->redirect(['action' => 'view', $id]);
        }

        try {
            $compra = $this->Compras->get($id, [
                'contain' => ['Requesters']
            ]);

            $systemConfig = $this->viewBuilder()->getVar('systemConfig');
            $ticketService = new \App\Service\TicketService($systemConfig);

            // Create ticket without assignee (will be assigned in Tickets module)
            $ticket = $ticketService->createFromCompra($compra);

            if ($ticket) {

                // Mark compra as converted
                $compra->status = 'convertido';
                $compra->resolved_at = new \Cake\I18n\DateTime();

                // Add system comment to compra
                $comprasCommentsTable = $this->fetchTable('ComprasComments');
                $comprasCommentsTable->save($comprasCommentsTable->newEntity([
                    'compra_id' => $compra->id,
                    'user_id' => $user->id,
                    'comment_type' => 'internal',
                    'body' => "Compra convertida a Ticket",
                    'is_system_comment' => true,
                    'sent_as_email' => false,
                ]));


                // Log conversion in compra history
                $comprasHistoryTable = $this->fetchTable('ComprasHistory');
                $comprasHistoryTable->save($comprasHistoryTable->newEntity([
                    'compra_id' => $compra->id,
                    'changed_by' => $user->id,
                    'field_name' => 'converted_to_ticket',
                    'old_value' => null,
                    'new_value' => $ticket->ticket_number,
                    'description' => "Convertido a Ticket #{$ticket->ticket_number}",
                ]));

                $this->Compras->save($compra);
                $ticketService->copyCompraData($compra, $ticket);

                $this->Flash->success(__(
                    'Compra convertida exitosamente a Ticket'
                ));

                return $this->redirect([
                    'controller' => 'Compras',
                    'action' => 'index',
                ]);
            }

            $this->Flash->error(__('Error al convertir compra a ticket.'));
            return $this->redirect(['action' => 'view', $id]);

        } catch (\Exception $e) {
            \Cake\Log\Log::error('Error en convertToTicket: ' . $e->getMessage());
            $this->Flash->error(__('Error al procesar la conversión: {0}', $e->getMessage()));
            return $this->redirect(['action' => 'view', $id]);
        }
    }

    /**
     * Statistics - Display compras statistics and analytics
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function statistics()
    {
        $this->renderStatistics('compra', ['defaultRange' => '30days']);
    }

    /**
     * Recalculate SLA for a compra
     *
     * @param string|null $id Compra id
     * @return \Cake\Http\Response|null Redirects on success
     */
    public function recalculateSla($id = null)
    {
        $this->request->allowMethod(['post', 'get']);

        try {
            $this->comprasService->recalculateSLA((int)$id);
            $this->Flash->success(__('SLA recalculado exitosamente.'));
        } catch (\Exception $e) {
            $this->Flash->error(__('Error al recalcular SLA: {0}', $e->getMessage()));
        }

        return $this->redirect(['action' => 'view', $id]);
    }

}
