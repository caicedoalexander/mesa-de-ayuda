# Diseño: eliminar "Título del sistema" e "Intervalo Gmail"

**Fecha:** 2026-07-21
**Rama:** dev
**Estado:** aprobado

## 1. Objetivo

Eliminar por completo dos ajustes del formulario de Configuración
(`/admin/settings`) y todo su rastro en el código:

- **Intervalo Gmail** (`gmail_check_interval`): campo muerto. Nada lo lee; la
  frecuencia real de ingesta la controla el schedule del webhook en n8n.
- **Título del sistema** (`system_title`): campo con dos consumidores activos
  reales. Se elimina el ajuste editable y sus consumidores se reapuntan a la
  constante de marca ya existente (`EmailBrand::TEAM_NAME`).

## 2. Alcance y no-alcance

**En alcance:** eliminar ambos ajustes de la UI, de `SettingKeys`, de los DTOs
(`GmailConfig`, `AppConfig`), del `AppController`, del layout, de `EmailService`
y `WhatsappService`; limpiar la constante `DEFAULT_SYSTEM_TITLE`; migración de
limpieza de filas huérfanas en `system_settings`; ajustar los tests que usaban
`system_title` como ejemplo.

**Fuera de alcance:** el sistema de plantillas de email (ya migrado a código en
mayo 2026), la lógica de ingesta Gmail/n8n, cualquier otro ajuste del formulario
(OAuth, WhatsApp, n8n, webhooks).

## 3. Contexto verificado

Rastreo completo realizado durante el brainstorming:

- `gmail_check_interval` se hidrata en `GmailConfig::$checkInterval` y se
  re-serializa en `toArray()`, pero **ningún consumidor lee `->checkInterval`**
  (grep en `src/` solo lo encuentra dentro de `GmailConfig.php`).
- `system_title` tiene **dos** consumidores activos:
  1. `EmailService.php:154,205` — nombre del remitente (`from` display name) de
     todos los emails salientes.
  2. `templates/element/head.php:5` — sufijo del `<title>` del navegador, vía
     `AppController.php:121` (`$this->set('systemTitle', …)`).
- El `{{system_title}}` de la migración `20260514120000_AddTicketAssignedEmailTemplate`
  es **historia**: la tabla `email_templates` fue eliminada por
  `20260515184721_DropEmailTemplatesTable`. Las plantillas viven en código
  (`src/Notification/Email/*`) y **no** usan `system_title` (grep en
  `src/Notification` = 0 resultados). No queda data viva que tocar.
- `CacheConstants::DEFAULT_SYSTEM_TITLE = 'Mesa de Ayuda'` se usa en tres sitios:
  fallback en `AppController.php:121`, fallback en `EmailService.php:154`, y como
  literal de marca en el mensaje de prueba de `WhatsappService.php:240`.
- `EmailBrand::TEAM_NAME = 'Mesa de Ayuda'` (`src/Notification/Email/EmailBrand.php:17`)
  es la constante de marca canónica, ya usada en los pies de todos los emails.
- Los DTOs se construyen **solo** vía `fromArray()` (named args internos); ningún
  test ni servicio los instancia con constructor posicional. Quitar parámetros es
  seguro.

## 4. Parte A — `gmail_check_interval` (eliminación limpia)

Sin reemplazo: no hay consumidor. Eliminar en:

- `src/Constants/SettingKeys.php`
  - constante `GMAIL_CHECK_INTERVAL` (línea 18).
  - su entrada en `USER_EDITABLE_KEYS` (línea 53).
- `src/Service/Dto/GmailConfig.php`
  - parámetro `$checkInterval` del constructor y su línea de docblock.
  - `checkInterval:` en `fromArray()`.
  - `SettingKeys::GMAIL_CHECK_INTERVAL => …` en `toArray()`.
  - El DTO queda con `refreshToken`, `clientSecretJson`, `userEmail`.
- `templates/Admin/Settings/index.php`
  - el `app-form-group` con el `<number>` `gmail_check_interval` (líneas 68-76),
    incluido su `<small>` "Frecuencia con la que se revisan nuevos correos.".

## 5. Parte B — `system_title` (eliminación con reemplazo por marca)

Los dos consumidores activos se resuelven así:

| Consumidor | Hoy | Después |
|---|---|---|
| Remitente de emails (`EmailService.php:154,205`) | `from` = `[$fromEmail => $systemTitle]` | `from` = `[$fromEmail => EmailBrand::TEAM_NAME]` |
| `<title>` del navegador (`head.php:5`) | `{página} - {systemTitle}` | `{página}` (se quita el sufijo) |

Eliminar / modificar en:

- `src/Constants/SettingKeys.php`
  - constante `SYSTEM_TITLE` (línea 14).
  - su entrada en `USER_EDITABLE_KEYS` (línea 52).
- `src/Service/Dto/AppConfig.php`
  - parámetro `$systemTitle` del constructor y su docblock.
  - `systemTitle:` en `fromArray()` y `SettingKeys::SYSTEM_TITLE => …` en
    `toArray()`.
  - El DTO queda solo con `webhookGmailImportToken` (se conserva la clase).
- `src/Controller/AppController.php`
  - eliminar la línea 121 `$this->set('systemTitle', …)`.
- `templates/element/head.php`
  - línea 5 queda: `<title><?= $this->fetch('title') ?></title>`. Cada vista ya
    asigna su propio título vía `$this->assign('title', …)`; no se reintroduce
    ninguna marca fija. Un `<title>` vacío en una vista sin título asignado es
    aceptable (no ocurre en las vistas actuales).
- `src/Service/EmailService.php`
  - eliminar `$systemTitle = $this->getSettingValue(SettingKeys::SYSTEM_TITLE, …)`
    (línea 154).
  - línea 205: `'from' => [$fromEmail => EmailBrand::TEAM_NAME]`.
  - añadir el `use App\Notification\Email\EmailBrand;` correspondiente.
- `templates/Admin/Settings/index.php`
  - eliminar el `app-form-group` con el `text` `system_title` (líneas 60-67).
  - **La tarjeta completa "Configuración general" (sección 1, líneas ~49-86)
    queda sin campos → se elimina la tarjeta entera**, incluidos su header
    ("Configuración general" / "Título y frecuencia de ingesta"), el
    `Form->create`/`Form->end` y el botón "Guardar configuración". El resto de
    tarjetas (OAuth, credenciales, WhatsApp, n8n, webhooks, accesos rápidos)
    permanece intacto.
- `src/Constants/CacheConstants.php`
  - eliminar `DEFAULT_SYSTEM_TITLE` (línea 15). Sus dos usos como fallback
    desaparecen junto con las líneas de `AppController` y `EmailService`.
- `src/Service/WhatsappService.php`
  - línea 240: reemplazar `CacheConstants::DEFAULT_SYSTEM_TITLE` por el literal
    `'Mesa de Ayuda'` inline. No se acopla a `EmailBrand` (otro dominio); es solo
    el pie de un mensaje de prueba de conexión.
  - si tras el cambio el `use App\Constants\CacheConstants;` queda sin uso en el
    archivo, eliminarlo; si `CacheConstants` se sigue usando para otra cosa,
    conservarlo.

## 6. Parte C — Migración de limpieza de datos

En producción, `system_settings` probablemente contiene filas huérfanas
`system_title` y `gmail_check_interval` (creadas al guardar el formulario). Tras
el refactor nada las lee.

Nueva migración `DeleteObsoleteSystemSettings`:

- `up()`:
  `DELETE FROM system_settings WHERE setting_key IN ('system_title', 'gmail_check_interval')`.
- `down()`: no-op (no se re-siembran ajustes obsoletos).

La migración `20260514120000` con `{{system_title}}` **no se toca**: su tabla ya
fue eliminada en `20260515184721`.

## 7. Parte D — Tests afectados

- `tests/TestCase/Service/Traits/SettingsEncryptionTraitTest.php` (líneas
  157, 161, 175, 181): `'system_title'` se usaba como ejemplo de "clave
  passthrough no cifrada". Reemplazar por un literal genérico
  (p. ej. `'some_plain_setting'`) para no reacoplar el test a una clave real que
  pudiera volverse cifrada. El aserto sigue verificando que la clave pasa sin
  cambios.
- `tests/TestCase/Service/SettingsServiceTest.php:36`:
  `keyRequiresOAuthCachePurge('system_title')` debe seguir dando `false`.
  Reemplazar `'system_title'` por otra clave no-OAuth existente
  (p. ej. `SettingKeys::WHATSAPP_API_URL`).

## 8. Verificación pre-merge

1. `composer cs-fix && composer cs-check` limpio.
2. `vendor/bin/phpstan analyse src` sin errores nuevos.
3. `composer test` verde.
4. `bin/cake migrations migrate` aplica la limpieza sin error.
5. Smoke manual: `/admin/settings` ya no muestra la tarjeta "Configuración
   general"; el resto de tarjetas intactas y funcionales.
6. Smoke manual opcional: crear un ticket en local y verificar que el correo
   saliente muestra "Mesa de Ayuda" como remitente.

## 9. Decisiones clave (referencia rápida)

1. **Intervalo Gmail:** eliminación total, sin reemplazo (campo muerto).
2. **Título del sistema:** eliminación del ajuste editable; consumidores
   reapuntados a `EmailBrand::TEAM_NAME` (emails) y sin sufijo (navegador).
3. **Remitente de emails:** `EmailBrand::TEAM_NAME` (decisión del usuario) para
   evitar regresión de branding.
4. **Tarjeta "Configuración general":** se elimina entera al quedar sin campos.
5. **`DEFAULT_SYSTEM_TITLE`:** se elimina; su uso en WhatsApp pasa a literal.
6. **Filas huérfanas en `system_settings`:** se limpian con una migración
   dedicada.
