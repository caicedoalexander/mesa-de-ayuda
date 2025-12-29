<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use App\Utility\SettingsEncryptionTrait;
use Cake\Controller\Controller;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/5/en/controllers.html#the-app-controller
 * @property \Authentication\Controller\Component\AuthenticationComponent $Authentication
 */
class AppController extends Controller
{
    use SettingsEncryptionTrait;
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
        $this->loadComponent('Authentication.Authentication');

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/5/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');
    }

    /**
     * Before filter callback
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event.
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        // Make user data available in all views
        $user = $this->Authentication->getIdentity();
        $this->set('currentUser', $user);

        // Load system settings with cache (1 hour TTL)
        $systemConfig = \Cake\Cache\Cache::remember('system_settings', function () {
            $systemSettingsTable = $this->fetchTable('SystemSettings');
            $settings = $systemSettingsTable->find()
                ->select(['setting_key', 'setting_value'])
                ->toArray();

            $config = [];
            foreach ($settings as $setting) {
                $config[$setting->setting_key] = $setting->setting_value;
            }

            // Decrypt sensitive values automatically
            return $this->processSettings($config);
        }, '_cake_core_');

        // Make system settings available in views
        $this->set('systemConfig', $systemConfig);
        $this->set('systemTitle', $systemConfig['system_title'] ?? 'Sistema de Soporte');

        // Set layout based on user role
        if ($user) {
            $role = $user->get('role');
            if ($role === 'admin') {
                $this->viewBuilder()->setLayout('admin');
            } elseif ($role === 'agent') {
                $this->viewBuilder()->setLayout('agent');
            } elseif ($role === 'compras') {
                $this->viewBuilder()->setLayout('compras');
            } elseif ($role === 'servicio_cliente') {
                $this->viewBuilder()->setLayout('servicio_cliente');
            } else {
                $this->viewBuilder()->setLayout('requester');
            }
        }
    }

    /**
     * Redirect user by role if not allowed for current module
     *
     * Eliminates ~45 lines of duplicated code across 3 controllers
     *
     * @param array $allowedRoles Roles allowed to access current module
     * @param string $moduleName Module name for error message (e.g., 'tickets', 'PQRS', 'compras')
     * @return \Cake\Http\Response|null Redirect response if not allowed, null if access granted
     */
    protected function redirectByRole(array $allowedRoles, string $moduleName): ?\Cake\Http\Response
    {
        $user = $this->Authentication->getIdentity();

        if (!$user) {
            return null; // Allow unauthenticated access (will be handled by Authentication plugin)
        }

        $role = $user->get('role');

        // Check if user role is allowed
        if (in_array($role, $allowedRoles, true)) {
            return null; // Access granted
        }

        // User not allowed - determine redirect based on their role
        $redirectMap = [
            'compras' => ['controller' => 'Compras', 'action' => 'index'],
            'servicio_cliente' => ['controller' => 'Pqrs', 'action' => 'index'],
            'agent' => ['controller' => 'Tickets', 'action' => 'index'],
            'requester' => ['controller' => 'Tickets', 'action' => 'index'],
            'admin' => ['controller' => 'Tickets', 'action' => 'index'], // Fallback for admin
        ];

        $this->Flash->error(__('No tienes permiso para acceder al módulo de {0}.', $moduleName));

        return $this->redirect($redirectMap[$role] ?? ['controller' => 'Tickets', 'action' => 'index']);
    }
}
