# Mantener Sesión Iniciada — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hacer funcional el checkbox "Mantener sesión iniciada" del login (persistencia entre cierres de navegador vía cookie), con un corte diario que fuerza re-login a partir de la medianoche `America/Bogota` para todas las sesiones.

**Architecture:** Dos piezas independientes. (A) Corte a medianoche server-side: `AppController::beforeFilter()` guarda un timestamp de expiración en la sesión al autenticar y fuerza `logout()` + redirect cuando se supera. (B) Persistencia: el `CookieAuthenticator` nativo del plugin Authentication emite una cookie cifrada (`EncryptedCookieMiddleware`) cuando `remember_me` viene marcado. La lógica de fechas vive en una clase pura testeable, `SessionExpiryPolicy`.

**Tech Stack:** CakePHP 5.3.6, `cakephp/authentication` ^4.2, PHP 8.5, PHPUnit (pure-unit), `Cake\I18n\DateTime`.

**Spec de referencia:** `docs/superpowers/specs/2026-07-21-mantener-sesion-iniciada-design.md`

## Global Constraints

Aplican a **todas** las tareas:

- `declare(strict_types=1);` es obligatorio en cada archivo PHP.
- La clave de sesión del corte **DEBE** ser `SessionExpiry.expiresAt`. **NUNCA** usar el namespace `Auth.*` — colisiona con el `sessionKey => 'Auth'` del `SessionAuthenticator` y corrompe la identidad (CRITICAL-3 de la revisión).
- La clave de cifrado de la cookie **DEBE** obtenerse con `Security::getSalt()`. **NUNCA** `Configure::read('Security.salt')` — devuelve `null` porque el bootstrap la `consume` (CRITICAL-2).
- El plugin ^4.2 **NO** expone `AuthenticationService::loadIdentifier()`. Cada autenticador declara su propio `identifier` (CRITICAL-1).
- "Medianoche" siempre es 00:00 en `America/Bogota` (zona por defecto de la app, sin DST).
- Tests **pure-unit**: sin conexión a BD, sin fixtures (respetar `tests/bootstrap.php`). El wiring de autenticación se valida con el smoke test manual de la Task 4.
- Antes de cada commit: `composer cs-fix && composer cs-check` en verde. Análisis: `vendor/bin/phpstan analyse src`.
- Tests base class: `PHPUnit\Framework\TestCase`; clases `final`; métodos `testXxx(): void`; namespace `App\Test\TestCase\...`.

---

### Task 1: `SessionExpiryPolicy` — lógica pura de expiración

Clase estática pura que calcula la próxima medianoche y decide si un timestamp ya expiró. Sin dependencias de framework más allá de `Cake\I18n\DateTime`. Totalmente unit-testeable.

**Files:**
- Create: `src/Service/Auth/SessionExpiryPolicy.php`
- Test: `tests/TestCase/Service/Auth/SessionExpiryPolicyTest.php`

**Interfaces:**
- Consumes: nada.
- Produces:
  - `SessionExpiryPolicy::nextMidnight(\DateTimeInterface $now): \Cake\I18n\DateTime` — devuelve las 00:00 del día siguiente a `$now`, en la zona horaria de `$now`.
  - `SessionExpiryPolicy::isExpired(int $expiresAt, int $now): bool` — `true` si `$now >= $expiresAt`.

- [ ] **Step 1: Escribir el test que falla**

Create `tests/TestCase/Service/Auth/SessionExpiryPolicyTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\Auth;

use App\Service\Auth\SessionExpiryPolicy;
use Cake\I18n\DateTime;
use PHPUnit\Framework\TestCase;

final class SessionExpiryPolicyTest extends TestCase
{
    public function testNextMidnightFromAfternoonReturnsStartOfNextDay(): void
    {
        $now = new DateTime('2026-07-21 15:30:00', 'America/Bogota');

        $result = SessionExpiryPolicy::nextMidnight($now);

        $this->assertSame('2026-07-22 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testNextMidnightJustBeforeMidnightReturnsSameUpcomingMidnight(): void
    {
        $now = new DateTime('2026-07-21 23:59:00', 'America/Bogota');

        $result = SessionExpiryPolicy::nextMidnight($now);

        $this->assertSame('2026-07-22 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testNextMidnightJustAfterMidnightReturnsNextDay(): void
    {
        $now = new DateTime('2026-07-21 00:01:00', 'America/Bogota');

        $result = SessionExpiryPolicy::nextMidnight($now);

        $this->assertSame('2026-07-22 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testNextMidnightExactlyAtMidnightReturnsNextDay(): void
    {
        $now = new DateTime('2026-07-21 00:00:00', 'America/Bogota');

        $result = SessionExpiryPolicy::nextMidnight($now);

        $this->assertSame('2026-07-22 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testIsExpiredWhenNowEqualsExpiry(): void
    {
        $this->assertTrue(SessionExpiryPolicy::isExpired(1000, 1000));
    }

    public function testIsExpiredWhenNowAfterExpiry(): void
    {
        $this->assertTrue(SessionExpiryPolicy::isExpired(1000, 1001));
    }

    public function testNotExpiredWhenNowBeforeExpiry(): void
    {
        $this->assertFalse(SessionExpiryPolicy::isExpired(1000, 999));
    }
}
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `vendor/bin/phpunit tests/TestCase/Service/Auth/SessionExpiryPolicyTest.php`
Expected: FAIL — `Error: Class "App\Service\Auth\SessionExpiryPolicy" not found`.

- [ ] **Step 3: Implementar la clase mínima**

Create `src/Service/Auth/SessionExpiryPolicy.php`:

```php
<?php
declare(strict_types=1);

namespace App\Service\Auth;

use Cake\I18n\DateTime;
use DateTimeInterface;

/**
 * Política de expiración diaria de sesión.
 *
 * Lógica pura (sin estado, sin framework más allá de las fechas) para forzar
 * el re-login a partir de la próxima medianoche `America/Bogota`.
 */
final class SessionExpiryPolicy
{
    /**
     * Devuelve las 00:00 del día siguiente a $now, en la zona horaria de $now.
     *
     * @param \DateTimeInterface $now Momento de referencia (en producción,
     *   `DateTime::now()`, ya en la zona por defecto de la app).
     * @return \Cake\I18n\DateTime
     */
    public static function nextMidnight(DateTimeInterface $now): DateTime
    {
        return DateTime::parse($now)->addDays(1)->startOfDay();
    }

    /**
     * @param int $expiresAt Timestamp de expiración.
     * @param int $now Timestamp actual.
     * @return bool `true` si $now alcanzó o superó $expiresAt.
     */
    public static function isExpired(int $expiresAt, int $now): bool
    {
        return $now >= $expiresAt;
    }
}
```

- [ ] **Step 4: Correr el test para verificar que pasa**

Run: `vendor/bin/phpunit tests/TestCase/Service/Auth/SessionExpiryPolicyTest.php`
Expected: PASS (7 tests, 7 assertions).

- [ ] **Step 5: Análisis estático y estilo**

Run: `composer cs-fix && composer cs-check && vendor/bin/phpstan analyse src`
Expected: sin errores en los archivos nuevos.

- [ ] **Step 6: Commit**

```bash
git add src/Service/Auth/SessionExpiryPolicy.php tests/TestCase/Service/Auth/SessionExpiryPolicyTest.php
git commit -m "feat: añadir SessionExpiryPolicy para corte diario de sesión"
```

---

### Task 2: Corte a medianoche en `AppController::beforeFilter()`

Aplica el corte a **todas** las sesiones (marquen o no el checkbox): fija `SessionExpiry.expiresAt` al autenticar y fuerza logout + redirect cuando expira. Sin unit test (requiere controller/sesión/BD → fuera del alcance pure-unit); se valida en la Task 4.

**Files:**
- Modify: `src/Controller/AppController.php`

**Interfaces:**
- Consumes: `SessionExpiryPolicy::nextMidnight()`, `SessionExpiryPolicy::isExpired()` (Task 1).
- Produces: escribe/lee la clave de sesión `SessionExpiry.expiresAt`.

- [ ] **Step 1: Añadir los imports**

En `src/Controller/AppController.php`, junto al resto de `use` (bloque de líneas ~19-27), añadir en orden alfabético:

```php
use App\Service\Auth\SessionExpiryPolicy;
use Cake\I18n\DateTime;
```

(Ya existen `use App\Constants\...`, `use Cake\Cache\Cache;`, `use Cake\Controller\Controller;`, `use Cake\Event\EventInterface;`, `use Cake\Http\Response;` — insertar los dos nuevos respetando el orden.)

- [ ] **Step 2: Insertar el bloque de corte en `beforeFilter()`**

En `src/Controller/AppController.php`, localizar en `beforeFilter()`:

```php
        // Make user data available in all views
        $identity = $this->Authentication->getIdentity();
        $this->set('currentUser', $identity?->getOriginalData());
```

Insertar **inmediatamente después** el bloque de corte:

```php
        // Corte diario: fuerza re-login a partir de la próxima medianoche
        // (America/Bogota). Aplica a TODAS las sesiones. La clave DEBE vivir
        // fuera del namespace 'Auth' que usa SessionAuthenticator.
        if ($identity !== null) {
            $session = $this->request->getSession();
            $expiresAt = $session->read('SessionExpiry.expiresAt');

            if ($expiresAt === null) {
                $session->write(
                    'SessionExpiry.expiresAt',
                    SessionExpiryPolicy::nextMidnight(DateTime::now())->getTimestamp(),
                );
            } elseif (SessionExpiryPolicy::isExpired((int)$expiresAt, time())) {
                $this->Authentication->logout();
                $this->Flash->error('Tu sesión expiró. Vuelve a iniciar sesión.');

                return $this->redirect(['controller' => 'Users', 'action' => 'login']);
            }
        }
```

- [ ] **Step 3: Análisis estático y estilo**

Run: `composer cs-fix && composer cs-check && vendor/bin/phpstan analyse src`
Expected: sin errores nuevos.

- [ ] **Step 4: Verificación de arranque**

Run: `bin/cake server` (en otra terminal) y abrir `http://localhost:8765/users/login`.
Expected: la página de login carga sin error 500 (confirma que los imports y el bloque no rompen el arranque). Detener el server tras verificar.

- [ ] **Step 5: Commit**

```bash
git add src/Controller/AppController.php
git commit -m "feat: forzar corte diario de sesión a medianoche en beforeFilter"
```

---

### Task 3: `CookieAuthenticator` + `EncryptedCookieMiddleware`

Habilita la persistencia entre cierres de navegador cuando `remember_me` está marcado. Sin unit test (wiring de framework); se valida en la Task 4.

**Files:**
- Modify: `src/Application.php` (`getAuthenticationService()` y `middleware()`)

**Interfaces:**
- Consumes: `SessionExpiryPolicy::nextMidnight()` (Task 1); `Security::getSalt()`.
- Produces: cookie persistente `MdaRemember` (cifrada) emitida solo en `/users/login` con `remember_me` truthy.

- [ ] **Step 1: Añadir los imports**

En `src/Application.php`, junto al resto de `use`, añadir en orden alfabético:

```php
use App\Service\Auth\SessionExpiryPolicy;
use Cake\Http\Middleware\EncryptedCookieMiddleware;
use Cake\I18n\DateTime;
use Cake\Utility\Security;
```

(Ya existen `use Cake\Http\Middleware\BodyParserMiddleware;`, `use Cake\Http\Middleware\CsrfProtectionMiddleware;`, `use Cake\Http\Middleware\SecurityHeadersMiddleware;` — insertar `EncryptedCookieMiddleware` entre ellos respetando el orden alfabético.)

- [ ] **Step 2: Reemplazar el cuerpo de `getAuthenticationService()`**

En `src/Application.php`, reemplazar el bloque actual:

```php
        $fields = [
            'username' => 'email',
            'password' => 'password',
        ];

        $authenticationService->loadAuthenticator('Authentication.Session');
        $authenticationService->loadAuthenticator('Authentication.Form', [
            'fields' => $fields,
            'loginUrl' => '/users/login',
            'identifier' => [
                'className' => 'Authentication.Password',
                'fields' => $fields,
            ],
        ]);

        return $authenticationService;
```

por:

```php
        $fields = [
            'username' => 'email',
            'password' => 'password',
        ];
        $identifier = [
            'className' => 'Authentication.Password',
            'fields' => $fields,
        ];

        // Orden significativo: Session (sesión existente) → Cookie (re-auth
        // persistente) → Form (procesa el POST del login).
        $authenticationService->loadAuthenticator('Authentication.Session');
        $authenticationService->loadAuthenticator('Authentication.Cookie', [
            'rememberMeField' => 'remember_me',
            'loginUrl' => '/users/login',
            'fields' => $fields,
            'identifier' => $identifier,
            'cookie' => [
                'name' => 'MdaRemember',
                'expires' => SessionExpiryPolicy::nextMidnight(DateTime::now()),
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => (bool)env('TRUST_PROXY', false),
            ],
        ]);
        $authenticationService->loadAuthenticator('Authentication.Form', [
            'fields' => $fields,
            'loginUrl' => '/users/login',
            'identifier' => $identifier,
        ]);

        return $authenticationService;
```

- [ ] **Step 3: Añadir `EncryptedCookieMiddleware` a la cola**

En `src/Application.php`, en `middleware()`, reemplazar:

```php
            // Add authentication middleware
            ->add(new AuthenticationMiddleware($this));
```

por:

```php
            // Descifra la cookie de "recuérdame" antes de que el autenticador
            // la lea (y la cifra en la respuesta). Clave: Security::getSalt()
            // — Configure::read('Security.salt') sería null (bootstrap la consume).
            ->add(new EncryptedCookieMiddleware(['MdaRemember'], Security::getSalt()))

            // Add authentication middleware
            ->add(new AuthenticationMiddleware($this));
```

- [ ] **Step 4: Análisis estático y estilo**

Run: `composer cs-fix && composer cs-check && vendor/bin/phpstan analyse src`
Expected: sin errores nuevos.

- [ ] **Step 5: Verificación de arranque**

Run: `bin/cake server` y abrir `http://localhost:8765/users/login`.
Expected: login carga sin error 500 (confirma que el nuevo autenticador y el middleware construyen bien — detecta CRITICAL-1 y CRITICAL-2 si algo se desvió). Detener el server tras verificar.

- [ ] **Step 6: Commit**

```bash
git add src/Application.php
git commit -m "feat: habilitar cookie persistente MdaRemember cifrada para remember_me"
```

---

### Task 4: Verificación end-to-end (smoke test manual + suite completa)

Valida los flujos completos que la estrategia pure-unit no cubre con tests automatizados. No produce código nuevo; es el gate final.

**Files:** ninguno (verificación).

- [ ] **Step 1: Suite y análisis completos en verde**

Run:
```bash
composer test
composer cs-check
vendor/bin/phpstan analyse src
```
Expected: toda la suite pasa; sin violaciones de estilo; sin errores de PHPStan.

- [ ] **Step 2: Smoke test — arranque e identidad (regresión de CRITICAL-1/2/3)**

Con `bin/cake server` corriendo:
1. Abrir `/users/login` → carga sin 500 (C1, C2).
2. Iniciar sesión **sin** marcar el checkbox, con un usuario real.
3. Tras el redirect, confirmar que la barra/menú muestra el nombre del usuario correcto y que se navega entre vistas sin perder la sesión (C3: la identidad no se corrompió).
4. Si el usuario es admin, entrar a `/admin`; si no lo es, confirmar que `/admin` lo rechaza según su rol (C3: `redirectByRole()` sigue aplicando).

Expected: identidad estable y guard de rol intacto en varios requests.

- [ ] **Step 3: Smoke test — persistencia con "Mantener sesión iniciada"**

1. Cerrar sesión. Iniciar sesión **marcando** el checkbox.
2. En DevTools → Application → Cookies, confirmar que existe `MdaRemember`, con `HttpOnly`, `SameSite=Lax` y `Expires` = próxima medianoche (hora local Bogotá). Su valor debe verse cifrado (no legible).
3. Borrar únicamente la cookie de sesión de PHP (dejar `MdaRemember`) y recargar → debe **re-autenticar automáticamente** sin pedir credenciales.

Expected: la cookie persistente re-crea la sesión.

- [ ] **Step 4: Smoke test — sin checkbox no persiste**

1. Cerrar sesión. Iniciar sesión **sin** marcar el checkbox.
2. Confirmar que **no** se emite la cookie `MdaRemember`.
3. Borrar la cookie de sesión de PHP y recargar → debe **redirigir al login**.

Expected: sin persistencia cuando el checkbox no se marca.

- [ ] **Step 5: Smoke test — corte diario (prueba forzada)**

Como esperar a la medianoche real no es práctico, forzar temporalmente el corte:
1. En `src/Service/Auth/SessionExpiryPolicy.php`, cambiar **temporalmente** `->addDays(1)->startOfDay()` por `->addMinutes(1)` (expira en 1 minuto).
2. Reiniciar el server, iniciar sesión, esperar > 1 minuto y hacer cualquier request.
3. Confirmar: redirect a `/users/login` con el flash "Tu sesión expiró. Vuelve a iniciar sesión.", y que la cookie `MdaRemember` fue borrada (logout la limpia).
4. **Revertir** el cambio temporal en `SessionExpiryPolicy.php` (`->addDays(1)->startOfDay()`) y confirmar con `git diff` que no queda residuo.

Expected: el corte fuerza logout + redirect + limpieza de cookie; el archivo queda revertido.

- [ ] **Step 6: Smoke test — cambio de contraseña invalida la cookie**

1. Iniciar sesión marcando el checkbox (se emite `MdaRemember`).
2. Cambiar la contraseña de ese usuario (vía el flujo de administración o directamente en BD con un nuevo hash).
3. Borrar la cookie de sesión de PHP (dejar `MdaRemember`) y recargar → debe **pedir login** (la cookie ya no autentica).

Expected: la cookie persistente queda inválida tras el cambio de contraseña.

- [ ] **Step 7: Confirmar árbol limpio**

Run: `git status`
Expected: sin cambios sin commitear (el ajuste temporal del Step 5 fue revertido). El plan queda implementado.

---

## Notas de implementación

- **No tocar** `templates/Users/login.php` (el checkbox `remember_me` ya emite el campo correcto) ni `config/app.php` (la sesión sigue siendo cookie de navegador; la persistencia la aporta `MdaRemember`).
- El desfase de microsegundos entre el `nextMidnight()` del `getAuthenticationService()` y el de `beforeFilter()` es irrelevante salvo que un request cruce exactamente la medianoche entre ambas llamadas (riesgo despreciable).
- `env('TRUST_PROXY', false)` en `getAuthenticationService()`: `env()` es una función global de CakePHP, disponible sin import.
