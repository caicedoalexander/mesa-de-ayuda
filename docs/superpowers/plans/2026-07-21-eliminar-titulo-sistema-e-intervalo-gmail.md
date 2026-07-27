# Eliminar "Título del sistema" e "Intervalo Gmail" — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar por completo los ajustes `system_title` y `gmail_check_interval` del sistema, reapuntando los consumidores activos del título a la constante de marca `EmailBrand::TEAM_NAME`.

**Architecture:** Refactor de eliminación en un helpdesk CakePHP 5.x. `gmail_check_interval` es un campo muerto (nada lo lee) → eliminación limpia. `system_title` tiene dos consumidores activos (remitente de emails y `<title>` del navegador) → se reapuntan/eliminan antes de borrar la constante. Orden de tareas elegido para que cada commit deje el build verde (phpstan detecta constantes colgantes).

**Tech Stack:** PHP 8.5+, CakePHP 5.x, PHPUnit, Phinx migrations, PHP_CodeSniffer (CakePHP ruleset), PHPStan.

## Global Constraints

- `declare(strict_types=1);` obligatorio en todo archivo PHP (ya presente en los archivos tocados; no eliminarlo).
- El valor de marca es `Mesa de Ayuda`, expuesto por `App\Notification\Email\EmailBrand::TEAM_NAME`. El remitente de emails DEBE usar esa constante, no un literal ni un ajuste.
- No reintroducir magic strings de claves de settings: usar constantes de `App\Constants\SettingKeys`. Única excepción aprobada: el literal `'_Mesa de Ayuda - Tickets_'` en el mensaje de prueba de WhatsApp.
- **Gate estándar** (correr al final de cada tarea, en este orden): `composer cs-fix` → `composer cs-check` → `vendor/bin/phpstan analyse src` → `composer test`. Todos deben quedar limpios/verdes antes del commit.
- Commits en formato conventional commits. No añadir atribución (deshabilitada globalmente en la config del usuario).
- Rama: `dev` (ya activa).

---

### Task 1: Eliminar el campo muerto `gmail_check_interval` (backend)

Campo sin consumidores: nada lee `GmailConfig::$checkInterval` (verificado por grep en `src/`). Eliminación limpia; sin test nuevo — la verificación es que phpstan y la suite siguen verdes.

**Files:**
- Modify: `src/Constants/SettingKeys.php` (línea 18 y línea 53)
- Modify: `src/Service/Dto/GmailConfig.php` (docblock, constructor, `fromArray`, `toArray`)

**Interfaces:**
- Consumes: nada.
- Produces: `GmailConfig` queda con el constructor `(string $refreshToken, string $clientSecretJson, string $userEmail)`. `GmailConfig::fromArray()` y `toArray()` dejan de incluir la clave `gmail_check_interval`.

- [ ] **Step 1: Eliminar la constante `GMAIL_CHECK_INTERVAL` de `SettingKeys`**

En `src/Constants/SettingKeys.php`, eliminar la línea:

```php
    public const GMAIL_CHECK_INTERVAL = 'gmail_check_interval';
```

- [ ] **Step 2: Eliminar la entrada de `GMAIL_CHECK_INTERVAL` en `USER_EDITABLE_KEYS`**

En el array `USER_EDITABLE_KEYS` del mismo archivo, eliminar la línea:

```php
        self::GMAIL_CHECK_INTERVAL,
```

- [ ] **Step 3: Eliminar `checkInterval` de `GmailConfig`**

En `src/Service/Dto/GmailConfig.php` eliminar las cuatro apariciones:

1. Del docblock del constructor, la línea:
```php
     * @param string $checkInterval Check interval (minutes)
```
2. Del constructor promovido, la línea:
```php
        public string $checkInterval,
```
3. De `fromArray()`, la línea:
```php
            checkInterval: (string)($raw[SettingKeys::GMAIL_CHECK_INTERVAL] ?? ''),
```
4. De `toArray()`, la línea:
```php
            SettingKeys::GMAIL_CHECK_INTERVAL => $this->checkInterval,
```

El constructor resultante debe quedar así:

```php
    public function __construct(
        public string $refreshToken,
        public string $clientSecretJson,
        public string $userEmail,
    ) {
    }
```

- [ ] **Step 4: Correr el gate estándar**

Run: `composer cs-fix && composer cs-check && vendor/bin/phpstan analyse src && composer test`
Expected: cs-check limpio, phpstan sin errores nuevos (ninguna referencia colgante a `GMAIL_CHECK_INTERVAL`), suite verde.

- [ ] **Step 5: Commit**

```bash
git add src/Constants/SettingKeys.php src/Service/Dto/GmailConfig.php
git commit -m "refactor: eliminar ajuste muerto gmail_check_interval"
```

---

### Task 2: Reapuntar el remitente de emails a `EmailBrand::TEAM_NAME`

Único cambio de comportamiento con test. El remitente (`from` display name) deja de leer el ajuste `system_title` y usa la constante de marca. Se añade un test de caracterización que ancla el contrato.

> **Nota honesta sobre el test:** NO es un ciclo RED→GREEN clásico. Con `SystemConfig::empty()`, el código viejo produce `CacheConstants::DEFAULT_SYSTEM_TITLE` (`'Mesa de Ayuda'`) y el nuevo produce `EmailBrand::TEAM_NAME` (también `'Mesa de Ayuda'`): el valor visible coincide, así que el test pasa antes y después. Su propósito es fijar el contrato "el remitente es la constante de marca" para prevenir que se re-acople a un ajuste. Por eso el test se añade JUNTO con el cambio (Steps 2-3) y se espera que pase inmediatamente.

**Files:**
- Modify: `src/Service/EmailService.php` (línea 6, imports; línea 154; línea 205)
- Test: `tests/TestCase/Service/EmailServiceTest.php` (nuevo test)

**Interfaces:**
- Consumes: `App\Notification\Email\EmailBrand::TEAM_NAME` (constante `string` existente = `'Mesa de Ayuda'`); `EmailService::dispatch(NotificationMessage $msg): bool` (ya existente); el seam `buildService()` de `EmailServiceTest`.
- Produces: los emails salientes fijan `$options['from'] = [$fromEmail => EmailBrand::TEAM_NAME]`. `EmailService` deja de referenciar `SettingKeys::SYSTEM_TITLE` y `CacheConstants`.

- [ ] **Step 1: Añadir el test de caracterización del remitente**

En `tests/TestCase/Service/EmailServiceTest.php`, añadir al bloque de `use` (tras `use App\Service\Dto\SystemConfig;`):

```php
use App\Notification\Email\EmailBrand;
```

Y añadir este método de test junto a los otros `testDispatch*` (antes del comentario `// -------------------- helpers --------------------`):

```php
    /**
     * El remitente (from display name) debe ser la constante de marca
     * EmailBrand::TEAM_NAME, desacoplado de cualquier ajuste de settings.
     * Captura $options via willReturnCallback y afirma el par email => nombre.
     */
    public function testDispatchSetsFromDisplayNameToBrandTeamName(): void
    {
        $capturedOptions = null;
        $gmail = $this->getMockBuilder(GmailService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['sendEmail'])
            ->getMock();
        $gmail->method('sendEmail')->willReturnCallback(
            static function ($to, $subject, $body, $attachments, $options) use (&$capturedOptions): ?string {
                $capturedOptions = $options;

                return 'sent@mail.gmail.com';
            },
        );

        $service = $this->buildService($this->createMock(TicketCommentService::class), $gmail);

        $msg = NotificationMessage::email(
            recipient: 'user@example.com',
            subject: 'New ticket',
            bodyHtml: '<p>b</p>',
        );

        $service->dispatch($msg);

        $this->assertIsArray($capturedOptions);
        // With SystemConfig::empty() the from-email resolves to '' (present-but-empty
        // GMAIL_USER_EMAIL). Task 2's contract is the display NAME, so assert only that.
        $this->assertSame([EmailBrand::TEAM_NAME], array_values($capturedOptions['from']));
    }
```

> **Corrección aplicada durante la implementación:** el snippet original de este paso asumía
> que `$fromEmail` caía al default `'noreply@localhost'` con `SystemConfig::empty()`. En
> realidad `resolveSettingValue` devuelve la cadena vacía presente-pero-vacía, así que
> `$fromEmail` es `''`. El contrato real de la Task 2 es el **display name**, por lo que el
> assert verifica solo `array_values($capturedOptions['from']) === [EmailBrand::TEAM_NAME]`.

- [ ] **Step 2: Cambiar el remitente en `EmailService` para usar la marca**

En `src/Service/EmailService.php`:

1. Eliminar la línea 154 completa:
```php
            $systemTitle = $this->getSettingValue(SettingKeys::SYSTEM_TITLE, CacheConstants::DEFAULT_SYSTEM_TITLE);
```
2. En la construcción de `$options` (línea 205), cambiar:
```php
                'from' => [$fromEmail => $systemTitle],
```
por:
```php
                'from' => [$fromEmail => EmailBrand::TEAM_NAME],
```

- [ ] **Step 3: Arreglar los imports de `EmailService`**

En el bloque de `use` de `src/Service/EmailService.php`:

1. Eliminar (línea 6) — ya no se usa `CacheConstants` en el archivo:
```php
use App\Constants\CacheConstants;
```
2. Añadir:
```php
use App\Notification\Email\EmailBrand;
```

> `use App\Constants\SettingKeys;` se mantiene: sigue usándose para `SettingKeys::GMAIL_USER_EMAIL`. `composer cs-fix` reordenará el bloque de `use` alfabéticamente.

- [ ] **Step 4: Correr el test nuevo**

Run: `vendor/bin/phpunit --filter testDispatchSetsFromDisplayNameToBrandTeamName`
Expected: PASS (1 test verde).

- [ ] **Step 5: Correr el gate estándar**

Run: `composer cs-fix && composer cs-check && vendor/bin/phpstan analyse src && composer test`
Expected: cs-check limpio (imports ordenados, `CacheConstants` sin uso huérfano), phpstan sin errores, suite verde.

- [ ] **Step 6: Commit**

```bash
git add src/Service/EmailService.php tests/TestCase/Service/EmailServiceTest.php
git commit -m "refactor: remitente de emails usa EmailBrand::TEAM_NAME en vez de system_title"
```

---

### Task 3: Eliminar los consumidores restantes de `system_title` y la constante `DEFAULT_SYSTEM_TITLE`

Los otros dos usos activos (título del navegador vía controller/layout) más el uso de la constante en WhatsApp. Tras esta tarea, `DEFAULT_SYSTEM_TITLE` queda eliminada y `SYSTEM_TITLE` solo la referencia `AppConfig` + `USER_EDITABLE_KEYS`.

**Files:**
- Modify: `src/Controller/AppController.php:121`
- Modify: `templates/element/head.php:5`
- Modify: `src/Service/WhatsappService.php` (línea 6, import; línea 240)
- Modify: `src/Constants/CacheConstants.php:15`

**Interfaces:**
- Consumes: nada nuevo.
- Produces: la variable de vista `systemTitle` deja de existir; el `<title>` del navegador es solo el título de la vista. `CacheConstants::DEFAULT_SYSTEM_TITLE` eliminada.

- [ ] **Step 1: Quitar el `set('systemTitle', …)` de `AppController`**

En `src/Controller/AppController.php`, eliminar la línea 121 completa:

```php
        $this->set('systemTitle', $systemConfig[SettingKeys::SYSTEM_TITLE] ?? CacheConstants::DEFAULT_SYSTEM_TITLE);
```

> NO tocar el `use App\Constants\CacheConstants;`: sigue usándose en las líneas 98 y 111 (`CACHE_SETTINGS`, `CACHE_CONFIG`). `use App\Constants\SettingKeys;` también se mantiene (usado en el array `$sensitiveKeys`).

- [ ] **Step 2: Quitar el sufijo del `<title>` en el layout**

En `templates/element/head.php`, la línea 5 dentro de `<title>`:

```php
        <?= $this->fetch('title') ?> - <?= h($systemTitle ?? 'Sistema de Soporte') ?>
```

reemplazar por:

```php
        <?= $this->fetch('title') ?>
```

> Cada vista ya asigna su título con `$this->assign('title', …)`; no se reintroduce marca fija.

- [ ] **Step 3: Reemplazar el uso de la constante en `WhatsappService` por un literal**

En `src/Service/WhatsappService.php`, línea 240:

```php
            '_' . CacheConstants::DEFAULT_SYSTEM_TITLE . ' - Tickets_';
```

reemplazar por:

```php
            '_Mesa de Ayuda - Tickets_';
```

Luego eliminar el import huérfano (línea 6), ya que `CacheConstants` no se usa en ninguna otra parte del archivo:

```php
use App\Constants\CacheConstants;
```

- [ ] **Step 4: Eliminar la constante `DEFAULT_SYSTEM_TITLE`**

En `src/Constants/CacheConstants.php`, eliminar la línea 15:

```php
    public const DEFAULT_SYSTEM_TITLE = 'Mesa de Ayuda';
```

- [ ] **Step 5: Correr el gate estándar**

Run: `composer cs-fix && composer cs-check && vendor/bin/phpstan analyse src && composer test`
Expected: phpstan verde (ninguna referencia colgante a `DEFAULT_SYSTEM_TITLE`; el import huérfano en `WhatsappService` fue eliminado), suite verde.

- [ ] **Step 6: Commit**

```bash
git add src/Controller/AppController.php templates/element/head.php src/Service/WhatsappService.php src/Constants/CacheConstants.php
git commit -m "refactor: eliminar consumidores de system_title y la constante DEFAULT_SYSTEM_TITLE"
```

---

### Task 4: Eliminar la constante `SYSTEM_TITLE` y el campo del DTO `AppConfig`

Con todos los consumidores ya reapuntados (Tasks 2-3), se elimina la definición del ajuste.

**Files:**
- Modify: `src/Constants/SettingKeys.php` (línea 14 y línea 52)
- Modify: `src/Service/Dto/AppConfig.php` (docblock, constructor, `fromArray`, `toArray`)

**Interfaces:**
- Consumes: nada.
- Produces: `AppConfig` queda con el constructor `(string $webhookGmailImportToken)`. `AppConfig::fromArray()`/`toArray()` dejan de incluir `system_title`. `SettingKeys::SYSTEM_TITLE` deja de existir.

- [ ] **Step 1: Eliminar la constante `SYSTEM_TITLE` de `SettingKeys`**

En `src/Constants/SettingKeys.php`, eliminar la línea:

```php
    public const SYSTEM_TITLE = 'system_title';
```

- [ ] **Step 2: Eliminar la entrada de `SYSTEM_TITLE` en `USER_EDITABLE_KEYS`**

En el array `USER_EDITABLE_KEYS`, eliminar la línea:

```php
        self::SYSTEM_TITLE,
```

- [ ] **Step 3: Eliminar `systemTitle` de `AppConfig`**

En `src/Service/Dto/AppConfig.php` eliminar las cuatro apariciones:

1. Del docblock, la línea:
```php
     * @param string $systemTitle Display title shown in UI
```
2. Del constructor promovido, la línea:
```php
        public string $systemTitle,
```
3. De `fromArray()`, la línea:
```php
            systemTitle: (string)($raw[SettingKeys::SYSTEM_TITLE] ?? ''),
```
4. De `toArray()`, la línea:
```php
            SettingKeys::SYSTEM_TITLE => $this->systemTitle,
```

El constructor resultante debe quedar así:

```php
    public function __construct(
        public string $webhookGmailImportToken,
    ) {
    }
```

Y `fromArray()`:

```php
    public static function fromArray(array $raw): self
    {
        return new self(
            webhookGmailImportToken: (string)($raw[SettingKeys::WEBHOOK_GMAIL_IMPORT_TOKEN] ?? ''),
        );
    }
```

- [ ] **Step 4: Correr el gate estándar**

Run: `composer cs-fix && composer cs-check && vendor/bin/phpstan analyse src && composer test`
Expected: phpstan verde (`SYSTEM_TITLE` sin referencias colgantes en `src/`; `SystemConfig::empty()` sigue construyendo sin ese campo), suite verde.

- [ ] **Step 5: Commit**

```bash
git add src/Constants/SettingKeys.php src/Service/Dto/AppConfig.php
git commit -m "refactor: eliminar constante SYSTEM_TITLE y campo systemTitle del DTO"
```

---

### Task 5: Eliminar la tarjeta "Configuración general" del formulario de settings

Al quitar sus dos únicos campos, la tarjeta (sección 1) queda vacía → se elimina entera. Único cambio user-facing. El template usa literales `'system_title'`/`'gmail_check_interval'`, así que no afecta el build; la verificación es cs-check + smoke visual.

**Files:**
- Modify: `templates/Admin/Settings/index.php` (bloque de la sección 1)

**Interfaces:**
- Consumes: nada.
- Produces: `/admin/settings` ya no muestra la tarjeta "Configuración general". Las tarjetas restantes (OAuth, credenciales, WhatsApp, n8n, webhooks, accesos rápidos) quedan intactas.

- [ ] **Step 1: Eliminar el bloque completo de la tarjeta general**

En `templates/Admin/Settings/index.php`, eliminar todo el bloque desde el comentario `<!-- 1. General -->` hasta el `</div>` de cierre de esa `app-card` (la que precede a `<!-- 2. Google OAuth -->`). Es exactamente este bloque:

```php
<!-- 1. General -->
<div class="app-card">
    <div class="app-card-header">
        <div class="app-card-header-icon"><i class="bi bi-sliders"></i></div>
        <div class="app-card-header-text">
            <h3 class="app-card-header-title">Configuración general</h3>
            <div class="app-card-header-subtitle">Título y frecuencia de ingesta</div>
        </div>
    </div>
    <?= $this->Form->create(null, ['type' => 'post']) ?>
    <div class="app-card-body">
        <div class="app-form-row">
            <div class="app-form-group">
                <?= $this->Form->label('system_title', 'Título del sistema') ?>
                <?= $this->Form->text('system_title', [
                    'value' => $settings['system_title'] ?? 'Sistema de Soporte',
                    'placeholder' => 'Sistema de Soporte',
                ]) ?>
            </div>
            <div class="app-form-group">
                <?= $this->Form->label('gmail_check_interval', 'Intervalo Gmail (min)') ?>
                <?= $this->Form->number('gmail_check_interval', [
                    'value' => $settings['gmail_check_interval'] ?? '5',
                    'placeholder' => '5',
                    'min' => 1,
                ]) ?>
                <small>Frecuencia con la que se revisan nuevos correos.</small>
            </div>
        </div>
    </div>
    <div class="app-card-footer">
        <?= $this->Form->button(
            '<i class="bi bi-check-lg"></i> Guardar configuración',
            ['class' => 'btn-brand-primary', 'escapeTitle' => false]
        ) ?>
    </div>
    <?= $this->Form->end() ?>
</div>
```

La siguiente tarjeta (`<!-- 2. Google OAuth -->`) pasa a ser la primera del formulario.

- [ ] **Step 2: Verificar estilo y render**

Run: `composer cs-check`
Expected: sin errores.

Smoke manual (si hay entorno local con `bin/cake server`): abrir `/admin/settings`, confirmar que la tarjeta "Configuración general" ya no aparece y que las demás tarjetas se renderizan bien. (Si no hay entorno, anotarlo y continuar.)

- [ ] **Step 3: Commit**

```bash
git add templates/Admin/Settings/index.php
git commit -m "refactor: eliminar tarjeta Configuración general del formulario de settings"
```

---

### Task 6: Actualizar tests que usaban `system_title` como ejemplo

Dos tests usan el literal `'system_title'` como ejemplo de clave. Como el ajuste ya no existe, se reemplaza por claves/strings que no aludan a un ajuste inexistente. (Estos tests usan literales, no la constante, así que ya pasan; este es un cleanup de higiene.)

**Files:**
- Modify: `tests/TestCase/Service/Traits/SettingsEncryptionTraitTest.php` (líneas 157, 161, 175, 181)
- Modify: `tests/TestCase/Service/SettingsServiceTest.php:36`

**Interfaces:**
- Consumes: `SettingKeys::N8N_WEBHOOK_URL` (clave no-OAuth existente) para `SettingsServiceTest` (ya importa `SettingKeys`).
- Produces: nada.

- [ ] **Step 1: Reemplazar `'system_title'` en `SettingsEncryptionTraitTest`**

En `tests/TestCase/Service/Traits/SettingsEncryptionTraitTest.php`, reemplazar las cuatro apariciones del literal `'system_title'` por `'some_plain_setting'`:

1. Línea 157: `'system_title' => 'Mesa de Ayuda',` → `'some_plain_setting' => 'Mesa de Ayuda',`
2. Línea 161: `$this->assertSame('Mesa de Ayuda', $processed['system_title']);` → `$this->assertSame('Mesa de Ayuda', $processed['some_plain_setting']);`
3. Línea 175: `'system_title' => 'Helpdesk',` → `'some_plain_setting' => 'Helpdesk',`
4. Línea 181: `$this->assertSame('Helpdesk', $processed['system_title']);` → `$this->assertSame('Helpdesk', $processed['some_plain_setting']);`

> El test verifica que una clave desconocida/no-cifrada pasa sin cambios; un literal genérico es más robusto que acoplarlo a una clave real.

- [ ] **Step 2: Reemplazar `'system_title'` en `SettingsServiceTest`**

En `tests/TestCase/Service/SettingsServiceTest.php`, línea 36:

```php
        $this->assertFalse(SettingsService::keyRequiresOAuthCachePurge('system_title'));
```

reemplazar por:

```php
        $this->assertFalse(SettingsService::keyRequiresOAuthCachePurge(SettingKeys::N8N_WEBHOOK_URL));
```

> Se elige `N8N_WEBHOOK_URL` (clave no-OAuth) para no duplicar la línea 37 que ya usa `'whatsapp_api_url'`. El test sigue verificando que una clave no relacionada no dispara purga de caché OAuth.

- [ ] **Step 3: Correr los tests afectados**

Run: `vendor/bin/phpunit tests/TestCase/Service/Traits/SettingsEncryptionTraitTest.php tests/TestCase/Service/SettingsServiceTest.php`
Expected: PASS (ambas clases verdes).

- [ ] **Step 4: Gate estándar + commit**

Run: `composer cs-fix && composer cs-check && composer test`
Expected: suite verde.

```bash
git add tests/TestCase/Service/Traits/SettingsEncryptionTraitTest.php tests/TestCase/Service/SettingsServiceTest.php
git commit -m "test: quitar referencias al ajuste eliminado system_title"
```

---

### Task 7: Migración de limpieza de filas huérfanas en `system_settings`

Borra las filas de datos obsoletas que quedaron en producción al haberse guardado el formulario. Sin impacto en código.

**Files:**
- Create: `config/Migrations/<timestamp>_DeleteObsoleteSystemSettings.php` (timestamp generado por `bake`)

**Interfaces:**
- Consumes: nada.
- Produces: migración con `up()` que borra las filas `system_title` y `gmail_check_interval`, y `down()` no-op.

- [ ] **Step 1: Generar el esqueleto de la migración**

Run: `bin/cake bake migration DeleteObsoleteSystemSettings`
Expected: crea `config/Migrations/<timestamp>_DeleteObsoleteSystemSettings.php`.

- [ ] **Step 2: Reemplazar el cuerpo generado**

Reemplazar todo el contenido del archivo generado por (imitando el estilo de `20260515184721_DropEmailTemplatesTable.php`):

```php
<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class DeleteObsoleteSystemSettings extends BaseMigration
{
    /**
     * Removes orphan rows left in system_settings after the removal of the
     * `system_title` and `gmail_check_interval` settings. Nothing reads these
     * values anymore (títulos y remitente usan EmailBrand::TEAM_NAME; la
     * frecuencia de ingesta la controla n8n).
     */
    public function up(): void
    {
        $this->execute(
            "DELETE FROM system_settings WHERE setting_key IN ('system_title', 'gmail_check_interval')",
        );
    }

    /**
     * Irreversible on purpose: no re-sembramos ajustes obsoletos.
     */
    public function down(): void
    {
    }
}
```

- [ ] **Step 3: Aplicar la migración (si hay DB disponible)**

Run: `bin/cake migrations migrate`
Expected: la migración aplica sin error. Si no hay DB local, verificar al menos que `composer cs-check` acepta el archivo y anotar que la migración se aplicará en despliegue.

- [ ] **Step 4: Gate de estilo + commit**

Run: `composer cs-fix && composer cs-check`
Expected: sin errores.

```bash
git add config/Migrations/
git commit -m "chore: migración para limpiar ajustes obsoletos system_title y gmail_check_interval"
```

---

## Self-Review

**1. Spec coverage:**
- Parte A (`gmail_check_interval`): SettingKeys + GmailConfig → Task 1; campo del template → Task 5. ✓
- Parte B (`system_title`): remitente email → Task 2; AppController + head.php + WhatsappService + `DEFAULT_SYSTEM_TITLE` → Task 3; SettingKeys + AppConfig → Task 4; tarjeta del template → Task 5. ✓
- Parte C (migración de datos) → Task 7. ✓
- Parte D (tests) → Task 6. ✓
- Verificación §8 (cs, phpstan, test, migrate, smoke) → gate estándar en cada tarea + smoke en Task 5. ✓

**2. Placeholder scan:** Sin TBD/TODO. Todo paso con cambio de código muestra el código exacto. El único valor no predecible (timestamp de la migración) lo genera `bake` en Task 7 Step 1, con el resto del archivo dado literal. ✓

**3. Type consistency:** `EmailBrand::TEAM_NAME` (string) usado idéntico en el test (Task 2 Step 1) y en `EmailService` (Task 2 Step 2). Constructores resultantes de `GmailConfig` (Task 1) y `AppConfig` (Task 4) mostrados completos. `SettingsService::keyRequiresOAuthCachePurge(string): bool` usado consistente con los asserts vecinos existentes. ✓

**4. Orden / build-verde:** consumidores de `system_title` (Tasks 2-3) preceden a la eliminación de la constante (Task 4); consumidores de `DEFAULT_SYSTEM_TITLE` (Tasks 2-3) preceden a su eliminación (Task 3, mismo commit). phpstan es el gate que atrapa cualquier referencia colgante. ✓
