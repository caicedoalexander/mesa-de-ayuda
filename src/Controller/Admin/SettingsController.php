<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use App\Service\GmailService;
use App\Service\SettingsService;
use App\Service\WhatsappService;
use Cake\Log\Log;

/**
 * Settings Controller
 *
 * Handles system configuration including:
 * - General settings
 * - Gmail OAuth setup
 * - Automatic encryption of sensitive values
 */
class SettingsController extends AppController
{
    private SettingsService $settingsService;

    public function initialize(): void
    {
        parent::initialize();
        $this->settingsService = new SettingsService();
    }

    /**
     * Before filter - require admin role
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event.
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        $user = $this->Authentication->getIdentity();
        if (!$user || $user->get('role') !== 'admin') {
            $this->Flash->error('Solo los administradores pueden acceder a esta sección.');
            return $this->redirect(['controller' => 'Tickets', 'action' => 'index', 'prefix' => false]);
        }
    }

    /**
     * Index method - Show and update settings
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        if ($this->request->is(['post', 'put'])) {
            $data = $this->request->getData();

            // Handle checkboxes (if not present, they're unchecked = '0')
            if (!isset($data['whatsapp_enabled'])) {
                $data['whatsapp_enabled'] = '0';
            }
            if (!isset($data['n8n_enabled'])) {
                $data['n8n_enabled'] = '0';
            }
            if (!isset($data['n8n_send_tags_list'])) {
                $data['n8n_send_tags_list'] = '0';
            }

            // Allowlist of valid setting keys to prevent arbitrary setting injection
            $allowedKeys = [
                'system_title', 'gmail_check_interval',
                'whatsapp_enabled', 'whatsapp_api_url', 'whatsapp_api_key',
                'whatsapp_instance_name', 'whatsapp_tickets_number',
                'whatsapp_compras_number', 'whatsapp_pqrs_number',
                'n8n_enabled', 'n8n_webhook_url', 'n8n_api_key',
                'n8n_send_tags_list', 'n8n_timeout',
            ];

            foreach ($data as $key => $value) {
                if (in_array($key, $allowedKeys, true)) {
                    $this->settingsService->saveSetting($key, $value);
                }
            }

            $this->Flash->success('Configuración guardada exitosamente.');
            return $this->redirect(['action' => 'index']);
        }

        $this->set('settings', $this->settingsService->loadAll());
    }

    /**
     * Gmail OAuth authorization
     *
     * @return \Cake\Http\Response|null|void
     */
    public function gmailAuth()
    {
        // Load all settings (already decrypted by SettingsService)
        $allSettings = $this->settingsService->loadAll();

        $config = [];
        if (!empty($allSettings['gmail_client_secret_path'])) {
            $config['client_secret_path'] = $allSettings['gmail_client_secret_path'];
        }

        // Set redirect URI for OAuth2 flow (callback URL)
        $config['redirect_uri'] = \Cake\Routing\Router::url([
            'controller' => 'Settings',
            'action' => 'gmailAuth',
            'prefix' => 'Admin',
        ], true); // true = full URL with domain

        $gmailService = new GmailService($config);

        // Check if we have a code from Google
        $code = $this->request->getQuery('code');

        if ($code) {
            try {
                // Exchange code for tokens
                $tokens = $gmailService->authenticate($code);

                if (isset($tokens['refresh_token'])) {
                    // Save refresh token to settings using service
                    if ($this->settingsService->saveSetting('gmail_refresh_token', $tokens['refresh_token'])) {
                        $this->Flash->success('Gmail autorizado exitosamente.');
                        Log::info('Gmail OAuth completed successfully');
                    } else {
                        $this->Flash->error('Error al guardar el token de Gmail.');
                        Log::error('Failed to save Gmail refresh token');
                    }
                } else {
                    $this->Flash->warning('No se recibió refresh token. Intenta nuevamente.');
                    Log::warning('No refresh token in OAuth response', ['token_keys' => array_keys($tokens ?? [])]);
                }

                return $this->redirect(['action' => 'index']);
            } catch (\Exception $e) {
                $this->Flash->error('Error en la autorización: ' . $e->getMessage());
                Log::error('Gmail OAuth error: ' . $e->getMessage());
                return $this->redirect(['action' => 'index']);
            }
        }

        // No code, redirect to Google authorization URL
        $authUrl = $gmailService->getAuthUrl();
        return $this->redirect($authUrl);
    }

    /**
     * Test Gmail connection
     *
     * @return \Cake\Http\Response|null|void
     */
    public function testGmail()
    {
        // Load all settings (already decrypted by SettingsService)
        $allSettings = $this->settingsService->loadAll();

        $config = [
            'refresh_token' => $allSettings['gmail_refresh_token'] ?? '',
            'client_secret_path' => $allSettings['gmail_client_secret_path'] ?? '',
        ];

        try {
            $gmailService = new GmailService($config);
            $messages = $gmailService->getMessages('is:unread', 5);

            $this->Flash->success('Conexión exitosa. Se encontraron ' . count($messages) . ' mensajes no leídos.');
            Log::info('Gmail connection test successful', ['message_count' => count($messages)]);
        } catch (\Exception $e) {
            $this->Flash->error('Error al conectar con Gmail: ' . $e->getMessage());
            Log::error('Gmail connection test failed: ' . $e->getMessage());
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Email templates management
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function emailTemplates()
    {
        $templatesTable = $this->fetchTable('EmailTemplates');

        if ($this->request->is('post')) {
            $template = $templatesTable->newEntity($this->request->getData());

            if ($templatesTable->save($template)) {
                $this->Flash->success('Plantilla creada exitosamente.');
                return $this->redirect(['action' => 'emailTemplates']);
            } else {
                $this->Flash->error('Error al crear la plantilla.');
            }
        }

        $templates = $templatesTable->find()->all();
        $this->set(compact('templates'));
    }

    /**
     * Edit email template
     *
     * @param string|null $id Template id
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function editTemplate($id = null)
    {
        $templatesTable = $this->fetchTable('EmailTemplates');
        $template = $templatesTable->get($id);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $template = $templatesTable->patchEntity($template, $this->request->getData());

            if ($templatesTable->save($template)) {
                $this->Flash->success('Plantilla actualizada exitosamente.');
                return $this->redirect(['action' => 'emailTemplates']);
            } else {
                $this->Flash->error('Error al actualizar la plantilla.');
            }
        }

        $this->set(compact('template'));
    }

    /**
     * Preview email template
     *
     * @param string|null $id Template id
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function previewTemplate($id = null)
    {
        $templatesTable = $this->fetchTable('EmailTemplates');
        $template = $templatesTable->get($id);

        // Sample data for preview
        $sampleData = [
            'ticket_number' => 'TKT-2025-00001',
            'subject' => 'Ejemplo de asunto del ticket',
            'requester_name' => 'Juan Pérez',
            'assignee_name' => 'María González',
            'created_date' => date('d/m/Y H:i'),
            'updated_date' => date('d/m/Y H:i'),
            'ticket_url' => 'http://localhost:8080/tickets/view/1',
            'system_title' => 'Sistema de Soporte',
        ];

        // Replace variables in body
        $previewBody = $template->body_html;
        foreach ($sampleData as $key => $value) {
            $previewBody = str_replace('{{' . $key . '}}', $value, $previewBody);
        }

        // Use a minimal layout for preview
        $this->viewBuilder()->setLayout(null);
        $this->set(compact('previewBody', 'template'));
    }

    /**
     * Users management
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function users()
    {
        $usersTable = $this->fetchTable('Users');

        $users = $this->paginate($usersTable->find()
            ->contain(['Organizations'])
            ->where(['Users.role IN' => ['admin', 'agent', 'servicio_cliente', 'compras']])
            ->orderBy(['Users.created' => 'DESC']));

        $this->set(compact('users'));
    }

    /**
     * Edit user
     *
     * @param string|null $id User id
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function editUser($id = null)
    {
        $usersTable = $this->fetchTable('Users');
        $user = $usersTable->get($id, contain: ['Organizations']);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->request->getData();

            // Handle profile image upload
            $profileImageFile = $this->request->getUploadedFile('profile_image_upload');
            if ($profileImageFile && $profileImageFile->getError() === UPLOAD_ERR_OK) {
                $result = $usersTable->saveProfileImage((int) $user->id, $profileImageFile);

                if ($result['success']) {
                    $data['profile_image'] = $result['filename'];
                } else {
                    $this->Flash->error($result['message']);
                    $organizations = $this->fetchTable('Organizations')->find('list')->toArray();
                    $this->set(compact('user', 'organizations'));
                    return;
                }
            }

            // Handle password change
            if (!empty($data['new_password'])) {
                if ($data['new_password'] !== $data['confirm_password']) {
                    $this->Flash->error('Las contraseñas no coinciden.');
                    $organizations = $this->fetchTable('Organizations')->find('list')->toArray();
                    $this->set(compact('user', 'organizations'));
                    return;
                }
                // Set password field to new_password value
                $data['password'] = $data['new_password'];
            } else {
                // Explicitly unset password if not changing it
                unset($data['password']);
            }

            // Remove password-related fields that shouldn't be patched
            unset($data['new_password']);
            unset($data['confirm_password']);
            unset($data['profile_image_upload']);

            $user = $usersTable->patchEntity($user, $data);

            if ($usersTable->save($user)) {
                $this->Flash->success('Usuario actualizado exitosamente.');
                return $this->redirect(['action' => 'users']);
            } else {
                $this->Flash->error('Error al actualizar el usuario.');
            }
        }

        $organizations = $this->fetchTable('Organizations')->find('list')->toArray();
        $this->set(compact('user', 'organizations'));
    }

    /**
     * Tags management
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function tags()
    {
        $tagsTable = $this->fetchTable('Tags');

        if ($this->request->is('post')) {
            $tag = $tagsTable->newEntity($this->request->getData());

            if ($tagsTable->save($tag)) {
                $this->Flash->success('Etiqueta creada exitosamente.');
                return $this->redirect(['action' => 'tags']);
            } else {
                $this->Flash->error('Error al crear la etiqueta.');
            }
        }

        // Load tags with ticket count
        $tags = $tagsTable->find()
            ->select([
                'Tags.id',
                'Tags.name',
                'Tags.color',
                'Tags.is_active',
                'Tags.created',
                'ticket_count' => $tagsTable->find()->func()->count('TicketTags.ticket_id')
            ])
            ->leftJoinWith('TicketTags')
            ->group(['Tags.id'])
            ->orderBy(['Tags.name' => 'ASC'])
            ->all();

        $this->set(compact('tags'));
    }

    /**
     * Add tag
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function addTag()
    {
        $tagsTable = $this->fetchTable('Tags');
        $tag = $tagsTable->newEmptyEntity();

        if ($this->request->is('post')) {
            $tag = $tagsTable->patchEntity($tag, $this->request->getData());

            if ($tagsTable->save($tag)) {
                $this->Flash->success('Etiqueta creada exitosamente.');
                return $this->redirect(['action' => 'tags']);
            } else {
                $this->Flash->error('Error al crear la etiqueta.');
            }
        }

        $this->set(compact('tag'));
    }

    /**
     * Edit tag
     *
     * @param string|null $id Tag id
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function editTag($id = null)
    {
        $tagsTable = $this->fetchTable('Tags');
        $tag = $tagsTable->get($id);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $tag = $tagsTable->patchEntity($tag, $this->request->getData());

            if ($tagsTable->save($tag)) {
                $this->Flash->success('Etiqueta actualizada exitosamente.');
                return $this->redirect(['action' => 'tags']);
            } else {
                $this->Flash->error('Error al actualizar la etiqueta.');
            }
        }

        $this->set(compact('tag'));
    }

    /**
     * Delete tag
     *
     * @param string|null $id Tag id
     * @return \Cake\Http\Response|null|void Redirects back
     */
    public function deleteTag($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);

        $tagsTable = $this->fetchTable('Tags');
        $tag = $tagsTable->get($id);

        if ($tagsTable->delete($tag)) {
            $this->Flash->success('Etiqueta eliminada.');
        } else {
            $this->Flash->error('Error al eliminar la etiqueta.');
        }

        return $this->redirect(['action' => 'tags']);
    }

    /**
     * Add user
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function addUser()
    {
        $usersTable = $this->fetchTable('Users');
        $user = $usersTable->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();

            // Validate password confirmation
            if (!empty($data['password']) && $data['password'] !== $data['confirm_password']) {
                $this->Flash->error('Las contraseñas no coinciden.');
            } else {
                // Remove confirm_password from data
                unset($data['confirm_password']);

                $user = $usersTable->patchEntity($user, $data);

                if ($usersTable->save($user)) {
                    $this->Flash->success('Usuario creado exitosamente.');
                    return $this->redirect(['action' => 'users']);
                } else {
                    $this->Flash->error('Error al crear el usuario.');
                }
            }
        }

        $organizations = $this->fetchTable('Organizations')->find('list')->toArray();
        $this->set(compact('user', 'organizations'));
    }

    /**
     * Deactivate user
     *
     * @param string|null $id User id
     * @return \Cake\Http\Response|null|void Redirects back
     */
    public function deactivateUser($id = null)
    {
        $this->request->allowMethod(['post']);

        $usersTable = $this->fetchTable('Users');
        $user = $usersTable->get($id);

        $user->is_active = false;

        if ($usersTable->save($user)) {
            $this->Flash->success('Usuario desactivado exitosamente.');
        } else {
            $this->Flash->error('Error al desactivar el usuario.');
        }

        return $this->redirect(['action' => 'users']);
    }

    /**
     * Activate user
     *
     * @param string|null $id User id
     * @return \Cake\Http\Response|null|void Redirects back
     */
    public function activateUser($id = null)
    {
        $this->request->allowMethod(['post']);

        $usersTable = $this->fetchTable('Users');
        $user = $usersTable->get($id);

        $user->is_active = true;

        if ($usersTable->save($user)) {
            $this->Flash->success('Usuario activado exitosamente.');
        } else {
            $this->Flash->error('Error al activar el usuario.');
        }

        return $this->redirect(['action' => 'users']);
    }

    /**
     * Test WhatsApp connection
     *
     * @return \Cake\Http\Response|null
     */
    public function testWhatsapp()
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Json');

        // Get cached system config to avoid redundant DB query
        $systemConfig = $this->viewBuilder()->getVar('systemConfig');
        $whatsappService = new WhatsappService($systemConfig);
        $result = $whatsappService->testConnection();

        $this->set([
            'success' => $result['success'],
            'message' => $result['message'],
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'message']);

        return null;
    }

    /**
     * Test n8n connection
     *
     * @return \Cake\Http\Response|null
     */
    public function testN8n()
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Json');

        $n8nService = new \App\Service\N8nService();
        $result = $n8nService->testConnection();

        $this->set([
            'success' => $result['success'],
            'message' => $result['message'],
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'message']);

        return null;
    }
    /**
     * Organizations management
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function organizations()
    {
        $organizationsTable = $this->fetchTable('Organizations');

        $organizations = $this->paginate($organizationsTable->find()
            ->select([
                'Organizations.id',
                'Organizations.name',
                'Organizations.created',
                'Organizations.modified',
                'user_count' => $organizationsTable->find()->func()->count('Users.id')
            ])
            ->leftJoinWith('Users')
            ->group(['Organizations.id'])
            ->orderBy(['Organizations.name' => 'ASC']));

        $this->set(compact('organizations'));
    }

    /**
     * Add organization
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function addOrganization()
    {
        $organizationsTable = $this->fetchTable('Organizations');
        $organization = $organizationsTable->newEmptyEntity();

        if ($this->request->is('post')) {
            $organization = $organizationsTable->patchEntity($organization, $this->request->getData());

            if ($organizationsTable->save($organization)) {
                $this->Flash->success('Organización creada exitosamente.');
                return $this->redirect(['action' => 'organizations']);
            } else {
                $this->Flash->error('Error al crear la organización.');
            }
        }

        $this->set(compact('organization'));
    }

    /**
     * Edit organization
     *
     * @param string|null $id Organization id
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function editOrganization($id = null)
    {
        $organizationsTable = $this->fetchTable('Organizations');
        $organization = $organizationsTable->get($id);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $organization = $organizationsTable->patchEntity($organization, $this->request->getData());

            if ($organizationsTable->save($organization)) {
                $this->Flash->success('Organización actualizada exitosamente.');
                return $this->redirect(['action' => 'organizations']);
            } else {
                $this->Flash->error('Error al actualizar la organización.');
            }
        }

        $this->set(compact('organization'));
    }

    /**
     * Delete organization
     *
     * @param string|null $id Organization id
     * @return \Cake\Http\Response|null|void Redirects back
     */
    public function deleteOrganization($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);

        $organizationsTable = $this->fetchTable('Organizations');
        $organization = $organizationsTable->get($id);

        // Check if organization has users
        $userCount = $this->fetchTable('Users')->find()->where(['organization_id' => $id])->count();
        if ($userCount > 0) {
            $this->Flash->error('No se puede eliminar la organización porque tiene usuarios asociados.');
            return $this->redirect(['action' => 'organizations']);
        }

        if ($organizationsTable->delete($organization)) {
            $this->Flash->success('Organización eliminada.');
        } else {
            $this->Flash->error('Error al eliminar la organización.');
        }

        return $this->redirect(['action' => 'organizations']);
    }
}
