<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\StatisticsControllerTrait;
use App\Controller\Traits\TicketSystemControllerTrait;
use App\Service\TicketService;
use App\Service\EmailService;
use App\Service\WhatsappService;
use App\Service\StatisticsService;
use App\Service\ResponseService;
use Cake\Cache\Cache;

/**
 * Tickets Controller
 *
 * @property \App\Model\Table\TicketsTable $Tickets
 */
class TicketsController extends AppController
{
    use StatisticsControllerTrait;
    use TicketSystemControllerTrait;
    private TicketService $ticketService;
    private EmailService $emailService;
    private WhatsappService $whatsappService;
    private ResponseService $responseService;
    private StatisticsService $statisticsService;

    /**
     * beforeFilter callback - Redirect users based on their role
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        $user = $this->Authentication->getIdentity();

        if ($user) {
            $role = $user->get('role');

            // Redirect compras users to their module
            if ($role === 'compras') {
                $this->Flash->error(__('No tienes permiso para acceder al módulo de tickets.'));
                return $this->redirect(['controller' => 'Compras', 'action' => 'index']);
            }

            // Redirect servicio_cliente users to PQRS module
            if ($role === 'servicio_cliente') {
                $this->Flash->error(__('No tienes permiso para acceder al módulo de tickets.'));
                return $this->redirect(['controller' => 'Pqrs', 'action' => 'index']);
            }
        }
    }

    /**
     * Initialize
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // Get cached system config from parent (set in AppController::beforeFilter)
        $systemConfig = $this->viewBuilder()->getVar('systemConfig');

        // Initialize services with cached config to avoid redundant DB queries
        $this->ticketService = new TicketService($systemConfig);
        $this->emailService = new EmailService($systemConfig);
        $this->whatsappService = new WhatsappService($systemConfig);
        $this->statisticsService = new StatisticsService();
        $this->responseService = new ResponseService($systemConfig);
    }
    /**
     * Index method - List tickets with filters
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->indexEntity('ticket', [
            'filterParams' => [
                'organization_id' => 'filter_organization',
            ],
            'specialRedirects' => function($request, $user, $userRole) {
                // Handle Gmail OAuth callback redirect
                $code = $request->getQuery('code');
                if ($code) {
                    $this->redirect([
                        'controller' => 'Settings',
                        'action' => 'gmailAuth',
                        'prefix' => 'Admin',
                        '?' => ['code' => $code]
                    ]);
                    return true; // Indicate redirect happened
                }

                return null; // No redirect
            },
        ]);
    }

    /**
     * View method - Show ticket detail
     *
     * @param string|null $id Ticket id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        return $this->viewEntity('ticket', (int)$id, [
            'lazyLoadHistory' => true, // PERFORMANCE FIX: Load history via AJAX
            'permissionCheck' => function($ticket) {
                return $this->_checkTicketViewPermission($ticket);
            },
            'beforeSet' => function($ticket, $viewVars) {
                // Get all tags for selection
                $tags = $this->fetchTable('Tags')->find('list')->toArray();

                return array_merge($viewVars, compact('tags'));
            },
        ]);
    }

    /**
     * Check if current user has permission to view ticket
     *
     * @param \App\Model\Entity\Ticket $ticket Ticket entity
     * @return \Cake\Http\Response|null Redirect response if no permission, null if allowed
     */
    private function _checkTicketViewPermission($ticket)
    {
        $user = $this->Authentication->getIdentity();
        if (!$user) {
            return null;
        }

        $userRole = $user->get('role');
        $userId = $user->get('id');

        // Requester can only view their own tickets
        if ($userRole === 'requester' && $ticket->requester_id !== $userId) {
            $this->Flash->error('No tienes permiso para ver este ticket.');
            return $this->redirect(['action' => 'index']);
        }

        return null;
    }

    /**
     * Add comment to ticket
     *
     * @param string|null $id Ticket id
     * @return \Cake\Http\Response|null|void Redirects back to ticket view
     */
    public function addComment($id = null)
    {
        return $this->addEntityComment('ticket', (int) $id);
    }

    /**
     * Assign ticket to agent
     *
     * @param string|null $id Ticket id
     * @return \Cake\Http\Response|null|void Redirects back
     */
    public function assign($id = null)
    {
        return $this->assignEntity('ticket', (int) $id, $this->request->getData('agent_id'));
    }

    /**
     * Change ticket status
     *
     * @param string|null $id Ticket id
     * @return \Cake\Http\Response|null|void Redirects back
     */
    public function changeStatus($id = null)
    {
        return $this->changeEntityStatus('ticket', (int) $id, $this->request->getData('status'));
    }

    /**
     * Change ticket priority
     *
     * @param string|null $id Ticket id
     * @return \Cake\Http\Response|null|void Redirects back
     */
    public function changePriority($id = null)
    {
        return $this->changeEntityPriority('ticket', (int) $id, $this->request->getData('priority'));
    }

    /**
     * Add tag to ticket
     *
     * @param string|null $id Ticket id
     * @return \Cake\Http\Response|null|void Redirects back
     */
    public function addTag($id = null)
    {
        $this->request->allowMethod(['post']);

        // Verify ticket exists
        $this->Tickets->get($id);
        $tagId = (int) $this->request->getData('tag_id');

        $ticketTagsTable = $this->fetchTable('TicketTags');

        // Check if already exists
        $exists = $ticketTagsTable->find()
            ->where(['ticket_id' => $id, 'tag_id' => $tagId])
            ->count();

        if ($exists) {
            $this->Flash->warning('Esta etiqueta ya está agregada.');
            return $this->redirect(['action' => 'view', $id]);
        }

        $ticketTag = $ticketTagsTable->newEntity([
            'ticket_id' => $id,
            'tag_id' => $tagId,
        ]);

        if ($ticketTagsTable->save($ticketTag)) {
            $this->Flash->success('Etiqueta agregada.');
        } else {
            $this->Flash->error('Error al agregar la etiqueta.');
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Remove tag from ticket
     *
     * @param string|null $id Ticket id
     * @param string|null $tagId Tag id
     * @return \Cake\Http\Response|null|void Redirects back
     */
    public function removeTag($id = null, $tagId = null)
    {
        $this->request->allowMethod(['post', 'delete']);

        $ticketTagsTable = $this->fetchTable('TicketTags');

        $ticketTag = $ticketTagsTable->find()
            ->where(['ticket_id' => $id, 'tag_id' => $tagId])
            ->first();

        if ($ticketTag && $ticketTagsTable->delete($ticketTag)) {
            $this->Flash->success('Etiqueta eliminada.');
        } else {
            $this->Flash->error('Error al eliminar la etiqueta.');
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Add follower to ticket
     *
     * @param string|null $id Ticket id
     * @return \Cake\Http\Response|null|void Redirects back
     */
    public function addFollower($id = null)
    {
        $this->request->allowMethod(['post']);

        $userId = (int) $this->request->getData('user_id');

        $followersTable = $this->fetchTable('TicketFollowers');

        // Check if already following
        $exists = $followersTable->find()
            ->where(['ticket_id' => $id, 'user_id' => $userId])
            ->count();

        if ($exists) {
            $this->Flash->warning('Este usuario ya está siguiendo el ticket.');
            return $this->redirect(['action' => 'view', $id]);
        }

        $follower = $followersTable->newEntity([
            'ticket_id' => $id,
            'user_id' => $userId,
        ]);

        if ($followersTable->save($follower)) {
            $this->Flash->success('Seguidor agregado.');
        } else {
            $this->Flash->error('Error al agregar seguidor.');
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Statistics - Dashboard with metrics and analytics
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function statistics()
    {
        $this->renderStatistics('ticket', ['defaultRange' => 'all']);
    }

    /**
     * Bulk assign tickets to an agent
     *
     * @return \Cake\Http\Response|null|void Redirects on success
     */
    public function bulkAssign()
    {
        return $this->bulkAssignEntity('ticket');
    }

    /**
     * Bulk change priority of tickets
     *
     * @return \Cake\Http\Response|null|void Redirects on success
     */
    public function bulkChangePriority()
    {
        return $this->bulkChangeEntityPriority('ticket');
    }

    /**
     * Bulk add tag to tickets
     *
     * @return \Cake\Http\Response|null|void Redirects on success
     */
    public function bulkAddTag()
    {
        return $this->bulkAddTagEntity('ticket');
    }

    /**
     * Bulk delete tickets
     *
     * @return \Cake\Http\Response|null|void Redirects on success
     */
    public function bulkDelete()
    {
        return $this->bulkDeleteEntity('ticket');
    }

    /**
     * Download ticket attachment
     *
     * @param string|null $id Attachment id
     * @return \Cake\Http\Response File download response
     */
    public function downloadAttachment($id = null)
    {
        return $this->downloadEntityAttachment('ticket', (int) $id);
    }

    /**
     * AJAX endpoint for lazy loading ticket history
     * PERFORMANCE FIX: Only loads when history tab is opened
     *
     * @param string|null $id Ticket id
     * @return void JSON response
     */
    public function history($id = null)
    {
        $this->historyEntity('ticket', (int)$id);
    }

    /**
     * Convert ticket to compra
     *
     * @param int|null $id Ticket id
     * @return \Cake\Http\Response|null Redirects to compra view
     */
    public function convertToCompra($id = null)
    {
        $this->request->allowMethod(['post']);

        $user = $this->Authentication->getIdentity();
        // Allow admin and agent to convert tickets
        $allowedRoles = ['admin', 'agent'];
        if (!$user || !in_array($user->role, $allowedRoles)) {
            $this->Flash->error(__('No tienes permiso para esta acción.'));
            return $this->redirect(['action' => 'view', $id]);
        }

        try {
            $ticket = $this->Tickets->get($id, [
                'contain' => ['TicketComments', 'Attachments']
            ]);

            $systemConfig = $this->viewBuilder()->getVar('systemConfig');
            $comprasService = new \App\Service\ComprasService($systemConfig);

            // Create compra without assignee (will be assigned in Compras module)
            $compra = $comprasService->createFromTicket($ticket);

            if ($compra) {

                $ticket->status = 'convertido';
                $ticket->resolved_at = new \Cake\I18n\DateTime();

                $ticketCommentsTable = $this->fetchTable('TicketComments');
                $ticketCommentsTable->save($ticketCommentsTable->newEntity([
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'comment_type' => 'internal',
                    'body' => "Ticket convertido a Compra",
                    'is_system_comment' => true,
                    'sent_as_email' => false,
                ]));

                $ticketHistoryTable = $this->fetchTable('TicketHistory');
                $ticketHistoryTable->save($ticketHistoryTable->newEntity([
                    'ticket_id' => $ticket->id,
                    'changed_by' => $user->id,
                    'field_name' => 'converted_to_compra',
                    'old_value' => null,
                    'new_value' => $compra->compra_number,
                    'description' => "Convertido a Compra #{$compra->compra_number}",
                ]));
                
                $this->Tickets->save($ticket);
                $comprasService->copyTicketData($ticket, $compra);

                $this->Flash->success(__(
                    'Ticket convertido exitosamente a Compra'
                ));

                return $this->redirect([
                    'controller' => 'Tickets',
                    'action' => 'index',
                ]);
            }

            $this->Flash->error(__('Error al convertir ticket a compra.'));
            return $this->redirect(['action' => 'view', $id]);

        } catch (\Exception $e) {
            \Cake\Log\Log::error('Error en convertToCompra: ' . $e->getMessage());
            $this->Flash->error(__('Error al procesar la conversión: {0}', $e->getMessage()));
            return $this->redirect(['action' => 'view', $id]);
        }
    }
}
