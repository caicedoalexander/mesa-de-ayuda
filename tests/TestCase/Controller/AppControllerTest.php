<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\AppController;
use Authentication\Controller\Component\AuthenticationComponent;
use Authentication\Identity;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;

final class AppControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Configure::write('App.encoding', 'UTF-8');
        Router::reload();
        Router::createRouteBuilder('/')->scope('/', function ($routes): void {
            $routes->fallbacks(DashedRoute::class);
        });
    }

    protected function tearDown(): void
    {
        Router::reload();

        parent::tearDown();
    }

    /**
     * La marca de expiración vive fuera del namespace 'Auth', así que el
     * logout no la borra y `$session->renew()` conserva los datos. Si
     * sobrevive, el siguiente login vuelve a expulsar al usuario en su propio
     * request de autenticación y queda bloqueado hasta borrar cookies.
     */
    public function testExpiredSessionClearsTheExpiryMarkOnLogout(): void
    {
        $session = new Session();
        $session->write('SessionExpiry.expiresAt', time() - 1);
        $controller = $this->makeController($session);

        $controller->beforeFilter(new Event('Controller.initialize', $controller));

        $this->assertNull($session->read('SessionExpiry.expiresAt'));
    }

    /**
     * Ningún controlador hijo propaga el retorno de parent::beforeFilter(),
     * de modo que la redirección debe viajar en el resultado del evento para
     * que Controller::startupProcess() detenga la acción.
     */
    public function testExpiredSessionRedirectsThroughTheEventResult(): void
    {
        $session = new Session();
        $session->write('SessionExpiry.expiresAt', time() - 1);
        $controller = $this->makeController($session);
        $event = new Event('Controller.initialize', $controller);

        $controller->beforeFilter($event);

        $response = $event->getResult();
        $this->assertNotNull($response);
        $this->assertSame('/users/login', $response->getHeaderLine('Location'));
    }

    private function makeController(Session $session): AppController
    {
        $controller = new AppController(new ServerRequest([
            'url' => '/tickets',
            'environment' => ['REQUEST_METHOD' => 'GET'],
            'session' => $session,
        ]));

        $auth = $this->createStub(AuthenticationComponent::class);
        $auth->method('getIdentity')->willReturn(new Identity(['id' => 1, 'role' => 'admin']));
        $controller->components()->set('Authentication', $auth);

        return $controller;
    }
}
