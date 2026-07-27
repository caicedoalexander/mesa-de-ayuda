<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\UsersController;
use Authentication\Authenticator\Result;
use Authentication\Controller\Component\AuthenticationComponent;
use Authentication\Identity;
use Authentication\IdentityInterface;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;

final class UsersControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // El bootstrap de tests es mínimo (sin BD): la acción sólo necesita
        // charset para construir la Response y rutas para resolver el destino.
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
     * Un `logout()` ejecutado en `AppController::beforeFilter` (corte diario de
     * sesión) elimina el atributo `identity` del request pero NO limpia el
     * resultado guardado en `AuthenticationService::$_result`. La acción
     * `login()` corre después dentro del mismo request y recibe
     * `isValid() === true` con identidad nula.
     */
    public function testLoginWithValidResultButNullIdentityRendersFormInsteadOfCrashing(): void
    {
        $controller = $this->makeLoginController(null);

        $this->assertNull($controller->login());
    }

    public function testLoginWithIdentityRedirectsToDefaultTarget(): void
    {
        $controller = $this->makeLoginController(new Identity(['id' => 1, 'role' => 'admin']));

        $response = $controller->login();

        $this->assertNotNull($response);
        $this->assertSame('/tickets', $response->getHeaderLine('Location'));
    }

    public function testLogoutClearsTheSessionExpiryMark(): void
    {
        $session = new Session();
        $session->write('SessionExpiry.expiresAt', time() + 3600);
        $controller = $this->makeLoginController(new Identity(['id' => 1, 'role' => 'admin']), $session);

        $controller->logout();

        $this->assertNull($session->read('SessionExpiry.expiresAt'));
    }

    /**
     * Construye el controlador con el componente de autenticación sustituido
     * por un doble, para fijar el par (resultado, identidad) sin tocar la BD.
     */
    private function makeLoginController(?IdentityInterface $identity, ?Session $session = null): UsersController
    {
        $controller = new UsersController(new ServerRequest([
            'url' => '/users/login',
            'environment' => ['REQUEST_METHOD' => 'GET'],
            'session' => $session ?? new Session(),
        ]));

        $auth = $this->createStub(AuthenticationComponent::class);
        $auth->method('getResult')->willReturn(new Result(['id' => 1, 'role' => 'admin'], Result::SUCCESS));
        $auth->method('getIdentity')->willReturn($identity);
        $controller->components()->set('Authentication', $auth);

        return $controller;
    }
}
