<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\TicketService;
use App\Service\AttachmentService;

/**
 * Tickets Controller
 *
 * @property \App\Model\Table\TicketsTable $Tickets
 */
class TicketsController extends AppController
{
    private TicketService $ticketService;
    private AttachmentService $attachmentService;

    /**
     * Initialize
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->ticketService = new TicketService();
        $this->attachmentService = new AttachmentService();
    }

    /**
     * Index method - List tickets with filters
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        // Handle Gmail OAuth callback redirect
        $code = $this->request->getQuery('code');
        if ($code) {
            return $this->redirect([
                'controller' => 'Settings',
                'action' => 'gmailAuth',
                'prefix' => 'Admin',
                '?' => ['code' => $code]
            ]);
        }

        $user = $this->Authentication->getIdentity();
        $userRole = $user ? $user->get('role') : null;
        $view = $this->request->getQuery('view', 'todos_sin_resolver');

        // Get search and filter parameters
        $search = $this->request->getQuery('search');
        $filterStatus = $this->request->getQuery('filter_status');
        $filterPriority = $this->request->getQuery('filter_priority');
        $filterAssignee = $this->request->getQuery('filter_assignee');
        $filterOrganization = $this->request->getQuery('filter_organization');
        $filterDateFrom = $this->request->getQuery('filter_date_from');
        $filterDateTo = $this->request->getQuery('filter_date_to');
        $sortField = $this->request->getQuery('sort', 'created');
        $sortDirection = $this->request->getQuery('direction', 'desc');

        // Build query based on view
        $query = $this->Tickets->find()
            ->contain(['Requesters', 'Assignees', 'Organizations']);

        // Apply sorting
        $validSortFields = ['created', 'modified', 'ticket_number', 'status', 'priority', 'subject'];
        if (in_array($sortField, $validSortFields)) {
            $query->orderBy(['Tickets.' . $sortField => strtoupper($sortDirection)]);
        } else {
            $query->orderBy(['Tickets.created' => 'DESC']);
        }

        // If user is a requester, only show their own tickets
        if ($userRole === 'requester') {
            $query->where(['Tickets.requester_id' => $user->get('id')]);
        }

        // If user is compras role, only show their assigned tickets or resolved tickets
        if ($userRole === 'compras') {
            $query->where([
                'OR' => [
                    'Tickets.assignee_id' => $user->get('id'),
                    'AND' => [
                        'Tickets.assignee_id' => $user->get('id'),
                        'Tickets.status' => 'resuelto'
                    ]
                ]
            ]);
        }

        // Apply view-based filters
        switch ($view) {
            case 'sin_asignar':
                $query->where(['Tickets.assignee_id IS' => null, 'Tickets.status !=' => 'resuelto']);
                break;
            case 'todos_sin_resolver':
                $query->where(['Tickets.status !=' => 'resuelto']);
                break;
            case 'pendientes':
                $query->where(['Tickets.status' => 'pendiente']);
                break;
            case 'nuevos':
                $query->where(['Tickets.status' => 'nuevo']);
                break;
            case 'abiertos':
                $query->where(['Tickets.status' => 'abierto']);
                break;
            case 'resueltos':
                $query->where(['Tickets.status' => 'resuelto']);
                break;
            case 'mis_tickets':
                // Filter by current user's assigned tickets
                if ($user) {
                    $query->where(['Tickets.assignee_id' => $user->get('id'), 'Tickets.status !=' => 'resuelto']);
                }
                break;
        }

        // Apply search filter
        if (!empty($search)) {
            $query->where([
                'OR' => [
                    'Tickets.ticket_number LIKE' => '%' . $search . '%',
                    'Tickets.subject LIKE' => '%' . $search . '%',
                    'Tickets.description LIKE' => '%' . $search . '%',
                    'Tickets.source_email LIKE' => '%' . $search . '%',
                    'Requesters.name LIKE' => '%' . $search . '%',
                    'Requesters.email LIKE' => '%' . $search . '%',
                ]
            ]);
        }

        // Apply status filter
        if (!empty($filterStatus)) {
            $query->where(['Tickets.status' => $filterStatus]);
        }

        // Apply priority filter
        if (!empty($filterPriority)) {
            $query->where(['Tickets.priority' => $filterPriority]);
        }

        // Apply assignee filter
        if (!empty($filterAssignee)) {
            if ($filterAssignee === 'unassigned') {
                $query->where(['Tickets.assignee_id IS' => null]);
            } else {
                $query->where(['Tickets.assignee_id' => $filterAssignee]);
            }
        }

        // Apply organization filter
        if (!empty($filterOrganization)) {
            $query->where(['Tickets.organization_id' => $filterOrganization]);
        }

        // Apply date range filter
        if (!empty($filterDateFrom)) {
            $query->where(['Tickets.created >=' => $filterDateFrom . ' 00:00:00']);
        }
        if (!empty($filterDateTo)) {
            $query->where(['Tickets.created <=' => $filterDateTo . ' 23:59:59']);
        }

        $tickets = $this->paginate($query, [
            'limit' => 25,
        ]);

        // Get all agents for assignment dropdown and filters
        $agents = $this->fetchTable('Users')->find('list')
            ->where(['role IN' => ['admin', 'agent', 'compras'], 'is_active' => true])
            ->toArray();

        // Get all organizations for filter
        $organizations = $this->fetchTable('Organizations')->find('list')
            ->toArray();

        // Store filters in variables for the view
        $filters = compact(
            'search',
            'filterStatus',
            'filterPriority',
            'filterAssignee',
            'filterOrganization',
            'filterDateFrom',
            'filterDateTo',
            'sortField',
            'sortDirection'
        );

        $this->set(compact('tickets', 'view', 'agents', 'organizations', 'filters'));
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
        $ticket = $this->Tickets->get($id, contain: [
            'Requesters',
            'Assignees',
            'Organizations',
            'TicketComments' => ['Users'],
            'Attachments',
            'Tags',
            'TicketFollowers' => ['Users'],
            'TicketHistory' => ['Users'],
        ]);

        // Check if requester is trying to view ticket that's not theirs
        $user = $this->Authentication->getIdentity();
        if ($user && $user->get('role') === 'requester') {
            if ($ticket->requester_id !== $user->get('id')) {
                $this->Flash->error('No tienes permiso para ver este ticket.');
                return $this->redirect(['action' => 'index']);
            }
        }

        // Check if compras user is trying to view ticket that's not assigned to them
        if ($user && $user->get('role') === 'compras') {
            if ($ticket->assignee_id !== $user->get('id')) {
                $this->Flash->error('No tienes permiso para ver este ticket.');
                return $this->redirect(['action' => 'index']);
            }
        }

        // Get all agents for assignment dropdown
        $agents = $this->fetchTable('Users')->find('list')
            ->where(['role IN' => ['admin', 'agent', 'compras'], 'is_active' => true])
            ->toArray();

        // Get all tags for selection
        $tags = $this->fetchTable('Tags')->find('list')->toArray();

        $this->set(compact('ticket', 'agents', 'tags'));
    }

    /**
     * View method - Show ticket detail
     *
     * @param string|null $id Ticket id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function viewCompras($id = null)
    {
        $ticket = $this->Tickets->get($id, contain: [
            'Requesters',
            'Assignees',
            'Organizations',
            'TicketComments' => ['Users'],
            'Attachments',
            'Tags',
            'TicketFollowers' => ['Users'],
        ]);

        // Check if requester is trying to view ticket that's not theirs
        $user = $this->Authentication->getIdentity();
        if ($user && $user->get('role') === 'requester') {
            if ($ticket->requester_id !== $user->get('id')) {
                $this->Flash->error('No tienes permiso para ver este ticket.');
                return $this->redirect(['action' => 'index']);
            }
        }

        // Check if compras user is trying to view ticket that's not assigned to them
        if ($user && $user->get('role') === 'compras') {
            if ($ticket->assignee_id !== $user->get('id')) {
                $this->Flash->error('No tienes permiso para ver este ticket.');
                return $this->redirect(['action' => 'index']);
            }
        }

        // Get all agents for assignment dropdown
        $agents = $this->fetchTable('Users')->find('list')
            ->where(['role IN' => ['admin', 'agent', 'compras'], 'is_active' => true])
            ->toArray();

        // Get all tags for selection
        $tags = $this->fetchTable('Tags')->find('list')->toArray();

        $this->set(compact('ticket', 'agents', 'tags'));
    }

    /**
     * Add comment to ticket
     *
     * @param string|null $id Ticket id
     * @return \Cake\Http\Response|null|void Redirects back to ticket view
     */
    public function addComment($id = null)
    {
        $this->request->allowMethod(['post']);

        $ticket = $this->Tickets->get($id);
        $user = $this->Authentication->getIdentity();
        $userId = $user->get('id');

        $commentBody = $this->request->getData('comment_body');
        $commentType = $this->request->getData('comment_type', 'public');
        $newStatus = $this->request->getData('status', $ticket->status);

        if (empty($commentBody)) {
            $this->Flash->error('El comentario no puede estar vacío.');
            return $this->redirect(['action' => 'view', $id]);
        }

        // Add comment
        $comment = $this->ticketService->addComment(
            (int)$id,
            $userId,
            $commentBody,
            $commentType,
            false
        );

        if (!$comment) {
            $this->Flash->error('Error al agregar el comentario.');
            return $this->redirect(['action' => 'view', $id]);
        }

        // Handle file uploads
        $files = $this->request->getUploadedFiles();
        if (!empty($files['attachments'])) {
            foreach ($files['attachments'] as $file) {
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $this->attachmentService->saveUploadedFile(
                        (int)$id,
                        $comment->id,
                        $file,
                        $userId
                    );
                }
            }
        }

        // Change status if different
        if ($newStatus !== $ticket->status) {
            $this->ticketService->changeStatus($ticket, $newStatus, $userId);
        }

        $this->Flash->success('Comentario agregado exitosamente.');
        return $this->redirect(['action' => 'index']);
    }

    /**
     * Assign ticket to agent
     *
     * @param string|null $id Ticket id
     * @return \Cake\Http\Response|null|void Redirects back
     */
    public function assign($id = null)
    {
        $this->request->allowMethod(['post', 'put']);

        $ticket = $this->Tickets->get($id);
        $agentId = (int)$this->request->getData('agent_id');
        $userId = $this->request->getAttribute('identity')['id'] ?? 1;

        if ($this->ticketService->assignTicket($ticket, $agentId, $userId)) {
            $this->Flash->success('Ticket asignado exitosamente.');
        } else {
            $this->Flash->error('Error al asignar el ticket.');
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Change ticket status
     *
     * @param string|null $id Ticket id
     * @return \Cake\Http\Response|null|void Redirects back
     */
    public function changeStatus($id = null)
    {
        $this->request->allowMethod(['post', 'put']);

        $ticket = $this->Tickets->get($id);
        $newStatus = $this->request->getData('status');
        $userId = $this->request->getAttribute('identity')['id'] ?? 1;

        if ($this->ticketService->changeStatus($ticket, $newStatus, $userId)) {
            $this->Flash->success('Estado del ticket actualizado.');
        } else {
            $this->Flash->error('Error al cambiar el estado.');
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Change ticket priority
     *
     * @param string|null $id Ticket id
     * @return \Cake\Http\Response|null|void Redirects back
     */
    public function changePriority($id = null)
    {
        $this->request->allowMethod(['post', 'put']);

        $ticket = $this->Tickets->get($id);
        $newPriority = $this->request->getData('priority');

        $ticket->priority = $newPriority;

        if ($this->Tickets->save($ticket)) {
            $this->Flash->success('Prioridad actualizada.');
        } else {
            $this->Flash->error('Error al cambiar la prioridad.');
        }

        return $this->redirect(['action' => 'view', $id]);
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
        $tagId = (int)$this->request->getData('tag_id');

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

        $userId = (int)$this->request->getData('user_id');

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
     * Download attachment
     *
     * @param string|null $id Attachment id
     * @return \Cake\Http\Response File download response
     */
    public function downloadAttachment($id = null)
    {
        $attachmentsTable = $this->fetchTable('Attachments');
        $attachment = $attachmentsTable->get($id);

        $filePath = $this->attachmentService->getFullPath($attachment);

        if (!file_exists($filePath)) {
            throw new \Cake\Http\Exception\NotFoundException('Archivo no encontrado.');
        }

        return $this->response
            ->withFile($filePath, ['download' => true, 'name' => $attachment->original_filename])
            ->withType($attachment->mime_type);
    }
}
