<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use App\Service\GmailService;
use App\Service\WhatsappService;
use App\Utility\SettingsEncryptionTrait;
use Cake\I18n\DateTime;
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
    use SettingsEncryptionTrait;
    /**
     * Helper method to save or update a setting (with automatic encryption)
     *
     * @param string $key Setting key
     * @param string $value Setting value
     * @return bool Success status
     */
    private function _saveSetting(string $key, string $value): bool
    {
        $settingsTable = $this->fetchTable('SystemSettings');
        $setting = $settingsTable->find()->where(['setting_key' => $key])->first();

        // Encrypt sensitive values automatically
        $valueToStore = $this->shouldEncrypt($key)
            ? $this->encryptSetting($value, $key)
            : $value;

        if ($setting) {
            $setting->setting_value = $valueToStore;
            $setting->modified = new DateTime();
        } else {
            $setting = $settingsTable->newEntity([
                'setting_key' => $key,
                'setting_value' => $valueToStore,
                'setting_type' => 'string',
            ]);
        }

        $result = (bool) $settingsTable->save($setting);

        // Clear all settings caches when a setting is updated
        if ($result) {
            \Cake\Cache\Cache::delete('system_settings', '_cake_core_');
            \Cake\Cache\Cache::delete('system_title', '_cake_core_');
            \Cake\Cache\Cache::delete('whatsapp_settings', '_cake_core_');
            \Cake\Cache\Cache::delete('n8n_settings', '_cake_core_');
        }

        return $result;
    }

    /**
     * Helper method to load all settings as associative array (with automatic decryption)
     *
     * @return array Settings array with key => value pairs (decrypted)
     */
    private function _loadSettings(): array
    {
        // Try to use cached settings from AppController first
        $settings = \Cake\Cache\Cache::read('system_settings', '_cake_core_');

        if ($settings === null) {
            // Cache miss - load from database
            $settingsTable = $this->fetchTable('SystemSettings');
            $settings = $settingsTable->find()
                ->select(['setting_key', 'setting_value'])
                ->all()
                ->combine('setting_key', 'setting_value')
                ->toArray();

            // Decrypt and cache for 1 hour
            $settings = $this->processSettings($settings);
            \Cake\Cache\Cache::write('system_settings', $settings, '_cake_core_');
        }

        return $settings;
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

            foreach ($data as $key => $value) {
                $this->_saveSetting($key, $value);
            }

            $this->Flash->success('Configuración guardada exitosamente.');
            return $this->redirect(['action' => 'index']);
        }

        $this->set('settings', $this->_loadSettings());
    }

    /**
     * Gmail OAuth authorization
     *
     * @return \Cake\Http\Response|null|void
     */
    public function gmailAuth()
    {
        $settingsTable = $this->fetchTable('SystemSettings');

        // Get client_secret_path from settings
        $clientSecretSetting = $settingsTable->find()
            ->where(['setting_key' => 'gmail_client_secret_path'])
            ->first();

        $config = [];
        if ($clientSecretSetting) {
            // No decryption needed for client_secret_path (it's a file path)
            $config['client_secret_path'] = $clientSecretSetting->setting_value;
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
                    // Save refresh token to settings using helper method
                    if ($this->_saveSetting('gmail_refresh_token', $tokens['refresh_token'])) {
                        $this->Flash->success('Gmail autorizado exitosamente.');
                        Log::info('Gmail OAuth completed successfully');
                    } else {
                        $this->Flash->error('Error al guardar el token de Gmail.');
                        Log::error('Failed to save Gmail refresh token');
                    }
                } else {
                    $this->Flash->warning('No se recibió refresh token. Intenta nuevamente.');
                    Log::warning('No refresh token in OAuth response', ['tokens' => $tokens]);
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
        $settingsTable = $this->fetchTable('SystemSettings');

        // Get Gmail config
        $settings = $settingsTable->find()
            ->where(['setting_key IN' => ['gmail_refresh_token', 'gmail_client_secret_path']])
            ->all();

        $config = [];
        foreach ($settings as $setting) {
            $key = str_replace('gmail_', '', $setting->setting_key);
            // Decrypt sensitive values
            $config[$key] = $this->shouldEncrypt($setting->setting_key)
                ? $this->decryptSetting($setting->setting_value, $setting->setting_key)
                : $setting->setting_value;
        }

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
