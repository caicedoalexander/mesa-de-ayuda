# Diseño: "Mantener sesión iniciada" con corte diario a medianoche

**Fecha:** 2026-07-21
**Estado:** Aprobado y ajustado tras revisión de seguridad (pendiente plan de implementación)

> **Revisión de seguridad (2026-07-21):** un agente revisor de autenticación validó
> este diseño contra el código fuente real de `cakephp/authentication` ^4.2 y
> detectó tres defectos CRITICAL (API inexistente, clave de cifrado nula,
> colisión de namespace de sesión). Todos están corregidos en el cuerpo de este
> documento; ver el changelog al final ("Correcciones aplicadas tras revisión").

## Contexto y motivación

El formulario de login (`templates/Users/login.php`, líneas 133-137) ya muestra
un checkbox **"Mantener sesión iniciada"** (`remember_me`), pero hoy es
**puramente decorativo**: no está conectado a ningún autenticador. La app usa el
plugin `cakephp/authentication` (^4.2) con dos autenticadores —
`Authentication.Session` y `Authentication.Form`— y la sesión es una cookie de
navegador estándar de PHP (`Session.defaults => php`, sin `lifetime` explícito),
por lo que **hoy la sesión ya muere al cerrar el navegador** y no existe
persistencia de ningún tipo.

Se requiere que el checkbox **funcione de verdad** (persistir la sesión más allá
del cierre del navegador) pero con una restricción de higiene: **todos deben
volver a iniciar sesión cada día**. El límite temporal es un corte a las **00:00
hora `America/Bogota`** (la zona horaria por defecto de la app, sin horario de
verano).

Aclaración de terminología: lo que el usuario describió como "reiniciar la caché
a medianoche" no es una caché — es la **expiración de la credencial persistente**
(cookie de "recuérdame") y de la sesión.

## Decisiones tomadas

| Decisión | Valor |
|---|---|
| Semántica de expiración | **Medianoche fija (00:00 `America/Bogota`)**. Todos re-loguean a las 00:00, sin importar la hora de entrada. |
| Alcance del corte | **Todas las sesiones** (marquen o no el checkbox). El checkbox solo decide si se sobrevive al cierre del navegador. |
| Enfoque de persistencia | **`CookieAuthenticator` nativo** del plugin Authentication (reutilizar código battle-tested). |
| Cifrado de la cookie | `EncryptedCookieMiddleware` (la cookie no debe exponer el hash del token). |
| Formulario | **Sin cambios** — el checkbox `remember_me` ya emite el campo esperado. |
| Config de sesión | **Sin cambios** en `config/app.php`. |

### Trade-off explícitamente aceptado

Con el `CookieAuthenticator` nativo, la expiración **de la cookie persistente**
es client-side (el navegador la borra a medianoche). El corte de **sesión** sí es
server-side. Hueco residual conocido: una cookie *robada* cuyo cliente ignore el
atributo `expires` podría renovarse día a día. El corte a medianoche es una
**política de higiene diaria, no una defensa contra secuestro de sesión** dentro
del mismo día. Este trade-off se aceptó a cambio de menos código custom.

## Arquitectura

El diseño tiene dos piezas independientes:

- **Pieza A — Corte a medianoche (server-side, para TODAS las sesiones).**
- **Pieza B — Sobrevivir al cierre del navegador (solo si `remember_me`).**

```
                        ┌─────────────────────────────────────────────┐
  Request  ──────────►  │ EncryptedCookieMiddleware  (descifra cookie) │
                        │ AuthenticationMiddleware   (Session→Cookie→  │
                        │                             Form)            │
                        └───────────────┬─────────────────────────────┘
                                        ▼
                        AppController::beforeFilter()
                        └─ Pieza A: valida SessionExpiry.expiresAt (server-side)
                              · null      → fija próxima medianoche
                              · expirado  → logout + redirect a login
```

### Pieza B — `Application::getAuthenticationService()`

Se reestructura el servicio para soportar el `CookieAuthenticator`. El plugin
^4.2 **no** expone un método `loadIdentifier()` a nivel de servicio (solo
`loadAuthenticator()`); cada autenticador declara su **propio** bloque
`identifier`, exactamente como el `Form` ya lo hace hoy. Cookie y Form usan un
config `Password`/`fields` idéntico —funcionalmente equivalente—, sin necesidad
de compartir instancia. El orden de los autenticadores es significativo:

```php
$fields = ['username' => 'email', 'password' => 'password'];
$identifier = ['className' => 'Authentication.Password', 'fields' => $fields];

$service->loadAuthenticator('Authentication.Session');   // 1º: sesión existente
$service->loadAuthenticator('Authentication.Cookie', [   // 2º: re-auth por cookie
    'rememberMeField' => 'remember_me',
    'loginUrl' => '/users/login',        // defensa: solo escribe la cookie en /users/login
    'fields' => $fields,
    'identifier' => $identifier,
    'cookie' => [
        'name' => 'MdaRemember',
        'expires' => $nextMidnight,      // Cake\I18n\DateTime, ver abajo
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (bool)env('TRUST_PROXY', false),
    ],
]);
$service->loadAuthenticator('Authentication.Form', [     // 3º: procesa el POST
    'fields' => $fields,
    'loginUrl' => '/users/login',
    'identifier' => $identifier,
]);
```

**`loginUrl` en Cookie (defensa en profundidad):** sin `loginUrl`, el
`CookieAuthenticator` escribiría la cookie persistente ante **cualquier** POST que
incluyera un campo `remember_me` truthy. Fijarlo a `/users/login` restringe la
escritura al formulario de login.

El `expires` se calcula **por request** con `SessionExpiryPolicy::nextMidnight()`
(próxima medianoche `America/Bogota`). Como el servicio se reconstruye en cada
request, el valor es correcto al momento de escribir la cookie tras el login.

**Orden de autenticadores:** `Session` primero (si ya hay sesión válida, se usa y
no se toca la cookie); `Cookie` segundo (re-crea la sesión desde la cookie
persistente cuando no hay sesión); `Form` último (procesa las credenciales del
POST de login y, si `remember_me` viene marcado, dispara la escritura de la
cookie persistente).

### Pieza B — `EncryptedCookieMiddleware`

Se añade a la cola de middleware **antes** de `AuthenticationMiddleware` (para
descifrar en el request antes de que el autenticador lea, y cifrar en la
respuesta después de que escriba):

```php
use Cake\Utility\Security;
// ...
->add(new EncryptedCookieMiddleware(['MdaRemember'], Security::getSalt()))
->add(new AuthenticationMiddleware($this));
```

La cookie del `CookieAuthenticator` contiene `email` + un token derivado del hash
de la contraseña; el cifrado evita exponer ese material.

**Clave de cifrado — usar `Security::getSalt()`, NO `Configure::read('Security.salt')`:**
`config/bootstrap.php:189` ejecuta `Security::setSalt(Configure::consume('Security.salt'))`.
`Configure::consume()` **lee y elimina** la clave de `Configure`, por lo que tras
el bootstrap `Configure::read('Security.salt')` devuelve `null` — y el constructor
de `EncryptedCookieMiddleware` exige `string $key`, produciendo un `TypeError`
fatal en cada request. El getter runtime `Security::getSalt()` sí devuelve el valor
(mismo que usa el `CookieAuthenticator` internamente). Requiere
`use Cake\Utility\Security;` en `Application.php`.

### Pieza A — Corte a medianoche server-side

Lógica **pura y testeable** en una clase nueva, siguiendo el estilo
fat-service/thin-controller del proyecto:

**`src/Service/Auth/SessionExpiryPolicy.php`**
```php
final class SessionExpiryPolicy
{
    // 00:00 del día siguiente en la zona horaria por defecto (America/Bogota)
    public static function nextMidnight(DateTimeInterface $now): DateTimeInterface;

    // now >= expiresAt
    public static function isExpired(int $expiresAt, int $now): bool;
}
```

`nextMidnight()` se implementa con `Cake\I18n\DateTime` (clase inmutable de
CakePHP 5.x; `FrozenTime` quedó deprecada), por ejemplo
`DateTime::now()->addDays(1)->startOfDay()`, que respeta `App.defaultTimezone`.

**Invocación en `AppController::beforeFilter()`** (tras `parent::beforeFilter()`,
una vez que la identidad ya está resuelta):

```
$identity = $this->Authentication->getIdentity();
if ($identity !== null) {
    $session   = $this->request->getSession();
    $expiresAt = $session->read('SessionExpiry.expiresAt');

    if ($expiresAt === null) {
        // Primer request tras login por Form O re-auth por Cookie
        $session->write('SessionExpiry.expiresAt',
            SessionExpiryPolicy::nextMidnight(DateTime::now())->getTimestamp());
    } elseif (SessionExpiryPolicy::isExpired((int)$expiresAt, time())) {
        $this->Authentication->logout();   // borra sesión + cookie MdaRemember
        $this->Flash->error('Tu sesión expiró. Vuelve a iniciar sesión.');

        return $this->redirect(['controller' => 'Users', 'action' => 'login']);
    }
}
```

Puntos clave:
- **La clave DEBE ser `SessionExpiry.*`, NUNCA `Auth.*`.** `SessionAuthenticator`
  usa `sessionKey => 'Auth'` y solo persiste la identidad `if (!$session->check('Auth'))`.
  Escribir `Auth.expiresAt` en `beforeFilter` crea `$_SESSION['Auth'] = ['expiresAt' => …]`
  **antes** de que el middleware persista el identity real (lo hace en la fase de
  salida, tras el controller). Ese `check('Auth')` daría `true`, la identidad real
  nunca se guardaría, y en el siguiente request `SessionAuthenticator` leería el
  array `['expiresAt' => …]` como "identidad válida". Eso corrompe la sesión de
  todos los usuarios y —vía `redirectByRole()`, que trata "no es `User`" como
  "permitir"— abre una escalada hacia `/admin`. Un namespace de primer nivel
  distinto de `Auth` (y de `AuthImpersonate`) evita la colisión por completo.
- El mismo bloque cubre login por Form **y** re-autenticación por Cookie, porque
  ambos llegan a `beforeFilter` con `SessionExpiry.expiresAt` aún sin escribir.
- `Authentication->logout()` invoca `clearIdentity` en todos los autenticadores;
  el `CookieAuthenticator` implementa la limpieza y **borra la cookie
  `MdaRemember`**. `SessionAuthenticator::clearIdentity()` además hace
  `$session->renew()`, así que el corte de medianoche **rota el ID de sesión**, no
  solo lo expira.
- Los controllers sin identidad no entran al bloque. `WebhooksController extends
  Controller` (no `AppController`) → nunca ejecuta este `beforeFilter`.
  `HealthController extends AppController`, pero un healthcheck de Docker no lleva
  cookie de sesión → `getIdentity()` devuelve `null` y el corte es inofensivo.
- No hay riesgo de bucle de redirección: tras `logout()` la identidad queda nula,
  y la acción `login` no vuelve a entrar al bloque en el siguiente request.

## Flujos resultantes

| Escenario | Comportamiento |
|---|---|
| Marca "Mantener sesión" → cierra navegador → vuelve antes de 00:00 | La cookie `MdaRemember` re-autentica automáticamente; nueva sesión con `expiresAt` = próxima medianoche. |
| Marca "Mantener sesión" → llega la medianoche | Corte server-side (`beforeFilter`) **y** cookie expirada en el navegador → re-login. |
| No marca → cierra navegador | Sesión de navegador muerta; debe iniciar sesión. |
| Cualquiera → llega la medianoche con el navegador abierto | `beforeFilter` fuerza `logout()` + redirect a login en el primer request posterior a las 00:00. |
| Cambia su contraseña | La cookie `MdaRemember` queda inválida (el token deriva del hash anterior). |

## Configuración de sesión — sin cambios

No se toca `config/app.php`. La persistencia entre cierres de navegador la aporta
la cookie `MdaRemember`, no la sesión PHP. La sesión sigue siendo cookie de
navegador (muere al cerrar). El único límite temporal explícito es el corte a
medianoche. El garbage-collector de sesiones de PHP puede seguir limpiando
sesiones inactivas como hoy; si el usuario marcó `remember_me`, el siguiente
request re-autentica por cookie de forma transparente.

## Seguridad

- Cookie `MdaRemember` **cifrada** (`EncryptedCookieMiddleware`), `httponly`,
  `samesite=Lax`, `secure` en producción (`TRUST_PROXY`).
- Cambiar la contraseña **invalida** la cookie (token derivado del hash).
- Corte diario server-side reduce la ventana de sesiones persistentes.
- Hueco residual aceptado (ver "Trade-off"): cookie robada renovable día a día.
  El corte a medianoche es higiene, no anti-hijack intradía.
- `America/Bogota` no observa DST → sin ambigüedad de reloj en el cálculo de
  medianoche.

## Testing

> **Nota (decisión tomada en la fase de plan, 2026-07-21):** el proyecto mantiene
> una política de tests **pure-unit** (`tests/bootstrap.php` sin conexión a BD ni
> fixtures). Por decisión explícita del usuario, los ítems de **integración**
> listados abajo (round-trip de identidad, smoke de middleware, `Set-Cookie`,
> invalidación por cambio de contraseña, cookie corrupta) se ejecutan como
> **smoke test manual** — la Task 4 del plan de implementación
> (`docs/superpowers/plans/2026-07-21-mantener-sesion-iniciada.md`) — **no** como
> tests automatizados. Solo `SessionExpiryPolicy` se cubre con unit tests.

- **Unit — `tests/TestCase/Service/Auth/SessionExpiryPolicyTest.php`:**
  - `nextMidnight()` devuelve 00:00 del día siguiente para varias horas de
    entrada (incluidas 23:59, 00:01 y el límite exacto `00:00:00`).
  - `isExpired()` en los límites: `now == expiresAt` (expira, por el `>=`),
    `now < expiresAt` (vigente).
- **Integración (con fixture `Users`):**
  - Un request con `SessionExpiry.expiresAt` en el pasado → 302 a `/users/login`;
    con `expiresAt` futuro → acceso permitido.
  - **Round-trip de identidad (regresión de CRITICAL-3):** login → request
    subsiguiente → `Authentication->getIdentity()->getOriginalData()` sigue siendo
    la instancia `User` correcta (id, email, role intactos). Este test es el que
    habría atrapado la colisión de namespace de sesión.
  - Se añade el wiring de fixtures según indica `CLAUDE.md` (sección Testing).
- **Smoke test de arranque del middleware completo (regresión de CRITICAL-1 y
  CRITICAL-2):** un request cualquiera a través de la cola real de
  `Application::middleware()` debe responder sin `Error`/`TypeError`. Ambos
  defectos eran de construcción/arranque, no de lógica, y solo un request end-to-end
  los revela.
- **Aserciones sobre `Set-Cookie`:**
  - `MdaRemember` se emite con `Expires` = próxima medianoche cuando `remember_me=1`.
  - **No** se emite cuando el checkbox no se marca (el hidden de `FormHelper::checkbox()`
    envía `'0'`, que es `empty()`).
  - Tras `logout()` (y tras el corte de medianoche) se emite `MdaRemember` con
    expiración en el pasado (cookie borrada).
- **Invalidación por cambio de contraseña:** reclamada en "Flujos resultantes";
  un test debe confirmar que una cookie emitida con el hash antiguo deja de
  autenticar tras cambiar la contraseña.
- **Cookie corrupta/manipulada:** un valor de `MdaRemember` inválido (JSON roto o
  hash no coincidente, ahora además cifrado con AES) no debe provocar un error no
  controlado, solo un fallo de autenticación limpio.
- El `CookieAuthenticator` y el `EncryptedCookieMiddleware` son del plugin/core
  (ya testeados upstream); **no** se re-testea su lógica interna, solo nuestra
  integración (emisión, expiración, limpieza).

## Archivos afectados

| Archivo | Cambio |
|---|---|
| `src/Application.php` | `getAuthenticationService()`: `CookieAuthenticator` (con su `identifier` y `loginUrl`); `middleware()`: `EncryptedCookieMiddleware` + `use Cake\Utility\Security`. (editar) |
| `src/Service/Auth/SessionExpiryPolicy.php` | Lógica pura de expiración. (nuevo) |
| `src/Controller/AppController.php` | Check de expiración en `beforeFilter()`. (editar) |
| `tests/TestCase/Service/Auth/SessionExpiryPolicyTest.php` | Tests unitarios. (nuevo) |
| Test de integración de expiración (+ `UsersFixture`) | Cobertura del corte server-side. (nuevo) |
| `templates/Users/login.php` | **Sin cambios.** |

## Fuera de alcance

- Idle timeout por inactividad (no solicitado; YAGNI).
- Forzado server-side estricto de la cookie persistente (Enfoque 2, descartado).
- "Cerrar sesión en todos los dispositivos" / gestión de sesiones activas.
- Cambios de UI/UX en el formulario de login.

## Correcciones aplicadas tras revisión de seguridad (2026-07-21)

Un agente revisor de autenticación validó el diseño original contra el código
fuente instalado de `cakephp/authentication` ^4.2 y `cakephp/cakephp`. Hallazgos
y correcciones ya incorporadas arriba:

| # | Severidad | Defecto en el diseño original | Corrección aplicada |
|---|---|---|---|
| C1 | CRITICAL | `AuthenticationService::loadIdentifier()` **no existe** en el plugin ^4.2 → `Error` fatal por request. | Eliminado; cada autenticador (Cookie/Form) declara su propio `identifier`. |
| C2 | CRITICAL | `Configure::read('Security.salt')` devuelve `null` (bootstrap lo `consume`) → `TypeError` fatal por request. | Se usa `Security::getSalt()` + `use Cake\Utility\Security`. |
| C3 | CRITICAL | Clave `Auth.expiresAt` **colisiona** con el `sessionKey => 'Auth'` de `SessionAuthenticator` → identidad corrupta + escalada vía `redirectByRole()`. | Renombrada a `SessionExpiry.expiresAt` (namespace propio). |
| M1 | MEDIUM | `CookieAuthenticator` sin `loginUrl` → escribiría la cookie ante cualquier POST con `remember_me`. | `loginUrl => '/users/login'` en Cookie. |
| M2 | MEDIUM | Plan de tests no detectaría ninguno de los CRITICAL. | Ampliado: round-trip de identidad, smoke test de middleware, `Set-Cookie`, cambio de password, cookie corrupta. |

Verificaciones que la revisión confirmó **correctas** en el diseño original:
`cookie.expires` acepta `DateTimeInterface`; orden `EncryptedCookieMiddleware`
antes de `AuthenticationMiddleware`; invalidación de la cookie al cambiar
contraseña; `logout()` borra sesión y cookie sin doble escritura; rotación de
session ID en el corte; `America/Bogota` sin DST; webhooks fuera del alcance del
corte.
