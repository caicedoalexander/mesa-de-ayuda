<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use App\Service\GmailService;
use Cake\I18n\DateTime;
use Cake\Log\Log;

/**
 * Settings Controller
 *
 * Handles system configuration including:
 * - General settings
 * - SMTP configuration
 * - Gmail OAuth setup
 */
class SettingsController extends AppController
{
    /**
     * Index method - Show and update settings
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $settingsTable = $this->fetchTable('SystemSettings');

        if ($this->request->is(['post', 'put'])) {
            $data = $this->request->getData();

            foreach ($data as $key => $value) {
                $setting = $settingsTable->find()
                    ->where(['setting_key' => $key])
                    ->first();

                if ($setting) {
                    $setting->setting_value = $value;
                    $setting->modified = new DateTime();
                    $settingsTable->save($setting);
                } else {
                    // Create new setting
                    $newSetting = $settingsTable->newEntity([
                        'setting_key' => $key,
                        'setting_value' => $value,
                        'setting_type' => 'string',
                    ]);
                    $settingsTable->save($newSetting);
                }
            }

            $this->Flash->success('Configuración guardada exitosamente.');
            return $this->redirect(['action' => 'index']);
        }

        // Load all settings
        $settings = $settingsTable->find()->all();
        $settingsArray = [];

        foreach ($settings as $setting) {
            $settingsArray[$setting->setting_key] = $setting->setting_value;
        }

        $this->set('settings', $settingsArray);
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
            $config['client_secret_path'] = $clientSecretSetting->setting_value;
        }

        $gmailService = new GmailService($config);

        // Check if we have a code from Google
        $code = $this->request->getQuery('code');

        if ($code) {
            try {
                // Exchange code for tokens
                $tokens = $gmailService->authenticate($code);

                if (isset($tokens['refresh_token'])) {
                    // Save refresh token to settings
                    $refreshTokenSetting = $settingsTable->find()
                        ->where(['setting_key' => 'gmail_refresh_token'])
                        ->first();

                    if ($refreshTokenSetting) {
                        $refreshTokenSetting->setting_value = $tokens['refresh_token'];
                        $refreshTokenSetting->modified = new DateTime();
                    } else {
                        $refreshTokenSetting = $settingsTable->newEntity([
                            'setting_key' => 'gmail_refresh_token',
                            'setting_value' => $tokens['refresh_token'],
                            'setting_type' => 'string',
                        ]);
                    }

                    if ($settingsTable->save($refreshTokenSetting)) {
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
            $config[$key] = $setting->setting_value;
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
            ->where(['Users.role IN' => ['admin', 'agent', 'compras']])
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

        $tags = $tagsTable->find()->all();
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
}
