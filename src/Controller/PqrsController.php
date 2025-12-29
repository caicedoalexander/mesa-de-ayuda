<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\StatisticsControllerTrait;
use App\Controller\Traits\TicketSystemControllerTrait;
use App\Service\PqrsService;
use App\Service\StatisticsService;
use App\Service\ResponseService;
use Cake\Event\EventInterface;

/**
 * PQRS Controller
 *
 * Handles PQRS (Peticiones, Quejas, Reclamos, Sugerencias) management:
 * - Public form submission (no auth required)
 * - Internal PQRS management (auth required)
 *
 * @property \App\Model\Table\PqrsTable $Pqrs
 */
class PqrsController extends AppController
{
    use StatisticsControllerTrait;
    use TicketSystemControllerTrait;
    private PqrsService $pqrsService;
    private ResponseService $responseService;
    private StatisticsService $statisticsService;

    /**
     * beforeFilter callback
     *
     * Allow public access to create action and restrict access by role
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);

        // Allow public access to create action (form submission)
        $this->Authentication->addUnauthenticatedActions(['create', 'success']);

        $user = $this->Authentication->getIdentity();

        if ($user) {
            $role = $user->get('role');

            // Redirect compras users to their module
            if ($role === 'compras') {
                $this->Flash->error(__('No tienes permiso para acceder al módulo de PQRS.'));
                return $this->redirect(['controller' => 'Compras', 'action' => 'index']);
            }

            // Redirect agent and requester users to Tickets module
            // (servicio_cliente and admin should have access to PQRS)
            if (in_array($role, ['agent', 'requester'])) {
                $this->Flash->error(__('No tienes permiso para acceder al módulo de PQRS.'));
                return $this->redirect(['controller' => 'Tickets', 'action' => 'index']);
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
        $this->pqrsService = new PqrsService($systemConfig);
        $this->statisticsService = new StatisticsService();
        $this->responseService = new ResponseService($systemConfig);
    }

    /**
     * Public form - Create new PQRS
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function create()
    {
        $this->viewBuilder()->setLayout('default'); // Public layout

        $pqrs = $this->Pqrs->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();

            // Add request metadata
            $data['ip_address'] = $this->request->clientIp();
            $data['user_agent'] = $this->request->getHeaderLine('User-Agent');
            $data['source_url'] = $this->request->referer();
            $data['channel'] = 'web';

            // Get uploaded files
            $files = [];
            $uploadedFiles = $this->request->getUploadedFiles();
            if (!empty($uploadedFiles['attachments'])) {
                $files = $uploadedFiles['attachments'];
            }

            // Create PQRS via service
            $pqrs = $this->pqrsService->createFromForm($data, $files);

            if ($pqrs) {
                $this->Flash->success('Su PQRS ha sido recibida correctamente. Número: ' . $pqrs->pqrs_number);
                return $this->redirect(['action' => 'success', $pqrs->pqrs_number]);
            } else {
                $this->Flash->error('No se pudo crear la PQRS. Por favor, intente nuevamente.');
            }
        }

        // Get PQRS types and priorities for form
        $types = [
            'peticion' => 'Petición',
            'queja' => 'Queja',
            'reclamo' => 'Reclamo',
            'sugerencia' => 'Sugerencia',
        ];

        $priorities = [
            'baja' => 'Baja',
            'media' => 'Media',
            'alta' => 'Alta',
            'urgente' => 'Urgente',
        ];

        $this->set(compact('pqrs', 'types', 'priorities'));
    }

    /**
     * Success page after PQRS submission
     *
     * @param string|null $pqrsNumber PQRS number
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function success($pqrsNumber = null)
    {
        $this->viewBuilder()->setLayout('default');

        $pqrs = null;
        if ($pqrsNumber) {
            $pqrs = $this->Pqrs->find()
                ->where(['pqrs_number' => $pqrsNumber])
                ->first();
        }

        $this->set(compact('pqrs'));
    }

    /**
     * Index method - List PQRS with filters (authenticated users only)
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->indexEntity('pqrs', [
            'filterParams' => [
                'type' => 'filter_type',
            ],
            'additionalViewVars' => [
                'search' => $this->request->getQuery('search'),
            ],
        ]);
    }

    /**
     * View method - View single PQRS with comments
     *
     * @param int|null $id PQRS id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        return $this->viewEntity('pqrs', (int)$id, [
            'lazyLoadHistory' => true, // PERFORMANCE FIX: Load history via AJAX
        ]);
    }

    /**
     * Add comment to PQRS
     *
     * @param int|null $id PQRS id
     * @return \Cake\Http\Response|null|void Redirects on successful comment
     */
    public function addComment($id = null)
    {
        return $this->addEntityComment('pqrs', (int) $id);
    }

    /**
     * Assign PQRS to user
     *
     * @param int|null $id PQRS id
     * @return \Cake\Http\Response|null Redirects
     */
    public function assign($id = null)
    {
        return $this->assignEntity('pqrs', (int) $id, $this->request->getData('assignee_id'));
    }

    /**
     * Change PQRS status
     *
     * @param int|null $id PQRS id
     * @return \Cake\Http\Response|null Redirects
     */
    public function changeStatus($id = null)
    {
        return $this->changeEntityStatus('pqrs', (int) $id, $this->request->getData('status'), 'view');
    }

    /**
     * Change PQRS priority
     *
     * @param int|null $id PQRS id
     * @return \Cake\Http\Response|null Redirects
     */
    public function changePriority($id = null)
    {
        return $this->changeEntityPriority('pqrs', (int) $id, $this->request->getData('priority'));
    }

    /**
     * Statistics - Display PQRS statistics and analytics
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function statistics()
    {
        $this->renderStatistics('pqrs', ['defaultRange' => '30days']);
    }

    /**
     * Download attachment
     *
     * @param int|null $id Attachment id
     * @return \Cake\Http\Response
     */
    public function download($id = null)
    {
        return $this->downloadEntityAttachment('pqrs', (int) $id);
    }

    /**
     * AJAX endpoint for lazy loading PQRS history
     * PERFORMANCE FIX: Only loads when history tab is opened
     *
     * @param string|null $id PQRS id
     * @return void JSON response
     */
    public function history($id = null)
    {
        $this->historyEntity('pqrs', (int)$id);
    }

    /**
     * Bulk assign PQRS to agents
     *
     * @return \Cake\Http\Response|null|void Redirects on success
     */
    public function bulkAssign()
    {
        return $this->bulkAssignEntity('pqrs');
    }

    /**
     * Bulk change priority of PQRS
     *
     * @return \Cake\Http\Response|null|void Redirects on success
     */
    public function bulkChangePriority()
    {
        return $this->bulkChangeEntityPriority('pqrs');
    }

    /**
     * Bulk delete PQRS
     *
     * @return \Cake\Http\Response|null|void Redirects on success
     */
    public function bulkDelete()
    {
        return $this->bulkDeleteEntity('pqrs');
    }

    /**
     * Recalculate SLA for a PQRS
     *
     * @param string|null $id PQRS id
     * @return \Cake\Http\Response|null Redirects on success
     */
    public function recalculateSla($id = null)
    {
        $this->request->allowMethod(['post', 'get']);

        try {
            $this->pqrsService->recalculateSLA((int)$id);
            $this->Flash->success(__('SLA recalculado exitosamente.'));
        } catch (\Exception $e) {
            $this->Flash->error(__('Error al recalcular SLA: {0}', $e->getMessage()));
        }

        return $this->redirect(['action' => 'view', $id]);
    }
}
