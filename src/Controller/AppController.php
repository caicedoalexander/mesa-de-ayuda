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

use Cake\Controller\Controller;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/5/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
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
     * @param \Cake\Event\EventInterface $event Event.
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        // Make user data available in all views
        $user = $this->Authentication->getIdentity();
        $this->set('currentUser', $user);

        // Load system settings and make them available in all views
        $systemSettingsTable = $this->fetchTable('SystemSettings');
        $settings = $systemSettingsTable->find()
            ->select(['setting_key', 'setting_value'])
            ->toArray();

        $systemConfig = [];
        foreach ($settings as $setting) {
            $systemConfig[$setting->setting_key] = $setting->setting_value;
        }

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
            } else {
                $this->viewBuilder()->setLayout('requester');
            }
        }
    }
}
