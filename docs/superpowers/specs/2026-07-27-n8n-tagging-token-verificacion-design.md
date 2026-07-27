# Diseño: token de verificación para el webhook de tageo en n8n

**Fecha:** 2026-07-27
**Rama:** dev
**Estado:** aprobado

## 1. Objetivo

Cerrar el único acceso sin autenticar entre la mesa de ayuda y n8n: el webhook
**saliente** de tageo automático (`POST https://n8n.alexandercaicedo.dev/webhook/tagging`).
Hoy ese nodo acepta cualquier POST anónimo. El backend pasa a enviar un token de
verificación en el header `X-Webhook-Token` y el nodo de n8n pasa a exigirlo,
igual que los tres accesos entrantes ya existentes.

## 2. Alcance y no-alcance

**En alcance:** convertir el setting `n8n_api_key` en un token generado por el
sistema (readonly en la UI, botón *Generar/Regenerar*, cifrado en reposo);
cambiar el header saliente a `X-Webhook-Token`; comportamiento fail-closed en
`N8nService`; activar Header Auth en el nodo `Asignacion de Tags` del workflow
`Mesa de Ayuda - Auto Tagging`; tests unitarios; runbook de operación y
re-exportación del JSON versionado del workflow.

**Fuera de alcance:** los tres webhooks entrantes (`/webhooks/gmail/import`,
`/webhooks/whatsapp/import`, `/webhooks/tickets/{id}/tags`), que ya validan
token; el resto del workflow de tageo (prompt, modelo, validación de `tag_ids`);
el workflow del bot de WhatsApp; renombrar la key `n8n_api_key` en base de datos.

## 3. Contexto verificado

Rastreo realizado durante el brainstorming:

- Los tres endpoints entrantes validan `X-Webhook-Token` contra un setting
  cifrado, con `hash_equals` y ventana de gracia de 300 s tras rotación
  (`src/Controller/WebhooksController.php:296-333`).
- El workflow **en producción** `Mesa de Ayuda - Auto Tagging`
  (`ljN6TsKdgEHXAWwm`, `active: true`) tiene el nodo `Asignacion de Tags`
  (`n8n-nodes-base.webhook` typeVersion 2.1, `path: tagging`) **sin** clave
  `authentication`. La API de n8n lo reporta literalmente como *"No credentials
  required for this webhook"*.
- El backend ya envía `X-API-Key: <n8n_api_key>` **solo si el valor no está
  vacío** (`src/Service/N8nService.php:232-234`). Nadie lo valida del otro lado,
  así que hoy el header es decorativo.
- `n8n_api_key` ya está en `ENCRYPTED_SETTING_KEYS`
  (`src/Service/Traits/SettingsEncryptionTrait.php:37`): cifrado en reposo, sin
  cambios necesarios.
- `n8n_api_key` está en `SettingKeys::USER_EDITABLE_KEYS` (`SettingKeys.php:56`),
  es decir, el POST del formulario de `/admin/settings` lo escribe. Los tokens de
  webhook **no** están en esa lista: se gestionan solo vía
  `SettingsController::regenerateWebhookToken()`.
- `regenerateWebhookToken()` genera `bin2hex(random_bytes(32))` y guarda el token
  anterior en caché durante `CacheConstants::WEBHOOK_TOKEN_OVERLAP_SECONDS`
  (300 s) para no cortar tráfico en vuelo (`SettingsController.php:662-716`).
  `rotatableWebhookTokens()` (`:136-143`) es la lista blanca de keys rotables.
- El JSON versionado `docs/operations/n8n/auto-tagging-workflow.json` está
  desfasado del workflow real: declara Groq como modelo y
  `{{ $env.MESADEAYUDA_URL }}` como base del callback; producción usa OpenRouter
  y la URL `http://mesadeayuda.copcsa.com` hardcodeada.
- No existe `tests/TestCase/Service/N8nServiceTest.php`.

## 4. Riesgo que se cierra

Cualquiera que conozca la URL del webhook puede POSTear un payload de ticket
falso. El flujo invoca al LLM (coste) y, en el último nodo, llama a
`/webhooks/tickets/{id}/tags` **con la credencial válida de n8n**, de modo que un
tercero puede hacer que se apliquen tags arbitrarios a tickets arbitrarios. El
`ticket_id` viaja en el cuerpo controlado por quien llama, así que el objetivo lo
elige el atacante. Severidad: media (contaminación de datos + gasto de LLM), sin
exfiltración — la respuesta del webhook es fija (`"Workflow got started."`).

## 5. Decisiones de diseño

| Decisión | Elección | Razón |
|---|---|---|
| Setting | Reusar `n8n_api_key` | Ya existe y ya está cifrado; crear uno nuevo dejaría un campo huérfano y exigiría migración |
| Origen del secreto | Generado por el sistema (`random_bytes(32)`) | Mismo mecanismo y misma UX que los otros tres accesos |
| Header | `X-Webhook-Token` | Unifica los cuatro accesos; nadie valida `X-API-Key` hoy, así que cambiarlo no rompe producción |
| Token vacío + n8n activo | Fail-closed, sin migración que autogenere | El operador genera el token explícitamente; entretanto el tageo se pausa con un warning en log en vez de salir sin autenticar |
| Ventana de gracia al rotar | No aplica | En esta dirección el validador es n8n; el backend no puede aceptar dos tokens |

## 6. Cambios en el backend

### 6.1 `src/Constants/SettingKeys.php`

Sacar `self::N8N_API_KEY` de `USER_EDITABLE_KEYS` y añadirlo a la lista de
exclusiones documentada en el docblock, junto a `WEBHOOK_GMAIL_IMPORT_TOKEN`:

> `- N8N_API_KEY (token de verificación saliente, regenerateWebhookToken)`

Efecto: el POST del formulario ya no puede escribir ni vaciar el token.

### 6.2 `src/Controller/Admin/SettingsController.php`

- `rotatableWebhookTokens()` cambia su tipo a `array<string, ?string>` y añade
  `SettingKeys::N8N_API_KEY => null`. `null` significa *token saliente*: no hay
  ventana de gracia porque quien valida es n8n.
- En `regenerateWebhookToken()`, el bloque que escribe el token previo en caché
  se ejecuta solo si `$previousCacheKey !== null`. Cuando es `null`, el mensaje
  de éxito es: *"Token regenerado. Actualiza la credencial Header Auth en n8n; el
  tageo automático fallará con 403 hasta que lo hagas."*
- En `index()`, exponer `'n8nVerificationToken' => (string)($allSettings[SettingKeys::N8N_API_KEY] ?? '')`.

### 6.3 `templates/Admin/Settings/index.php`

En la tarjeta *Integración con n8n*, reemplazar el input editable
`API Key (opcional)` por el mismo bloque que usa la tarjeta *Webhook · Tickets
tags* (`:387-413`):

- Label: `Token de verificación (X-Webhook-Token)`.
- Si el token está vacío: aviso `— sin generar — El tageo automático está pausado.`
  en `var(--danger-600)`.
- Si existe: input `type="password"` readonly + botón ojo + botón copiar.
- En el footer de la tarjeta, un `postLink` a `regenerateWebhookToken` con
  `data.setting_key = 'n8n_api_key'`, etiqueta *Generar token* / *Regenerar
  token* según haya valor, y `confirm`: *"¿Seguro? Deberás actualizar la
  credencial Header Auth en n8n; hasta entonces el tageo fallará con 403."*

El campo readonly va **fuera** del `Form->create()` de la tarjeta o sin atributo
`name`, para que el submit del formulario no lo POSTee. (Aunque el allowlist de
`USER_EDITABLE_KEYS` ya lo rechazaría, no conviene mandar el secreto en cada
guardado.)

Sin CSS nuevo: se reutilizan las clases ya cargadas por esta vista.

### 6.4 `src/Service/N8nService.php`

- `sendWebhook()`: el header pasa a `'X-Webhook-Token: ' . $config[SettingKeys::N8N_API_KEY]`.
- `sendWebhook()` pasa de `private` a `protected` (necesario para el espía de
  tests; ver §8).
- `sendTicketCreatedWebhook()`: tras comprobar la URL, si el token está vacío
  → `Log::warning('n8n verification token not configured; skipping tagging webhook', ['ticket_id' => $ticket->id])`
  y `return false`. No se construye payload ni se abre conexión.
- `testConnection()`: si el token está vacío →
  `['success' => false, 'message' => 'Genera el token de verificación antes de probar la conexión.']`.
  Sin esto, el botón *Probar conexión* daría un OK engañoso.
- Docblock de la clase: una línea explicando que `n8n_api_key` es el token de
  verificación del webhook saliente, no una API key de la API de n8n.

### 6.5 `src/Service/Dto/N8nConfig.php`

Solo el docblock de `$apiKey`: *"Token de verificación enviado en X-Webhook-Token
(descifrado)"*. El nombre de la propiedad no cambia.

## 7. Cambios en n8n

Workflow `Mesa de Ayuda - Auto Tagging` (`ljN6TsKdgEHXAWwm`), nodo
`Asignacion de Tags` (`08ad1428-aa4e-4d16-ac4d-c227d967057e`).

**Paso manual (el operador, en la UI de n8n):** crear credencial *Header Auth*
`Mesa de Ayuda - Tagging Inbound` con Header Name `X-Webhook-Token` y Value = el
token generado en `/admin/settings`. El MCP de n8n solo lista credenciales, no
las crea.

**Paso automatizable (vía MCP):** añadir al nodo
`"authentication": "headerAuth"` y la referencia `credentials.httpHeaderAuth`
con el id de esa credencial; después **publicar** la versión — el workflow usa
versionado (`activeVersionId`), así que sin publicar producción seguiría con la
versión sin auth.

Tras el cambio, n8n responde `403` a todo POST sin el header correcto.

## 8. Tests

Nuevo `tests/TestCase/Service/N8nServiceTest.php`, suite Unit, sin fixtures.

Aislamiento: se construye `new N8nService(SystemConfig::fromSettingsArray([...]))`.
Como el array trae la presence key `n8n_enabled`, `resolveSettingsBatch()`
devuelve el DTO en su primera rama y no toca caché ni base de datos. Se fija
`n8n_send_tags_list = '0'` para que `buildTicketPayload()` no consulte la tabla
`Tags`. Una subclase anónima sobrescribe `sendWebhook()` para capturar los
argumentos sin abrir conexión.

Casos:

1. `sendTicketCreatedWebhook()` con `n8n_api_key = ''` y n8n habilitado → retorna
   `false` y `sendWebhook()` **no** se invoca.
2. `sendTicketCreatedWebhook()` con token presente → `sendWebhook()` recibe los
   headers, que contienen `X-Webhook-Token: <token>` y **no** contienen
   `X-API-Key`.
3. `testConnection()` con token vacío → `success === false` y mensaje que
   menciona el token.
4. Regresión de seguridad: `assertNotContains(SettingKeys::N8N_API_KEY, SettingKeys::USER_EDITABLE_KEYS)`.

Comandos de verificación: `vendor/bin/phpunit tests/TestCase/Service/N8nServiceTest.php`,
más `composer cs-fix && composer cs-check` antes de commitear.

## 9. Orden de despliegue

Invertir los pasos 2 y 4 corta el tageo automático.

| # | Paso | Estado del tageo |
|---|---|---|
| 1 | Deploy del backend | Vivo si `n8n_api_key` ya tenía valor; pausado (con warning en log) si estaba vacío |
| 2 | `/admin/settings` → *Generar token* → copiar | Vivo: n8n aún acepta anónimo e ignora el header extra |
| 3 | Crear la credencial Header Auth en n8n con ese valor | Vivo |
| 4 | Activar `headerAuth` en el nodo y publicar la versión | Vivo y ya autenticado |
| 5 | Verificación (§10) | — |

## 10. Criterios de éxito

- Un ticket nuevo dispara una ejecución exitosa en n8n y sus tags quedan
  aplicados.
- `curl -X POST` a la URL del webhook **sin** el header → `403`.
- El mismo `curl` **con** `X-Webhook-Token: <token>` → `200`.
- *Probar conexión* en `/admin/settings` → OK con token; mensaje explícito
  (sin llamada HTTP) cuando el token está vacío.
- El formulario de `/admin/settings` guardado repetidamente no borra ni altera el
  token.
- `composer cs-check` y la suite Unit en verde.

## 11. Documentación

- **Nuevo** `docs/operations/n8n-tagging-auth.md`: runbook corto — generar el
  token, crear/actualizar la credencial en n8n, rotar, y el `curl` de
  verificación con la advertencia de que rotar deja el tageo en 403 hasta
  actualizar la credencial.
- `docs/operations/n8n/auto-tagging-workflow.json`: **re-exportar** desde el
  workflow real tras el cambio. Actualizar solo el nodo webhook dejaría un
  artefacto igualmente engañoso (hoy declara Groq y `$env.MESADEAYUDA_URL`,
  producción usa OpenRouter y una URL hardcodeada). Decisión aprobada en el
  brainstorming; es la única parte que excede el cambio mínimo.
- `CLAUDE.md`, sección de notificaciones e integraciones: una línea indicando que
  el webhook saliente de tageo exige `X-Webhook-Token` y que el secreto vive en
  `n8n_api_key`.

## 12. Deuda conocida que este spec no toca

- El nombre de la key en base de datos sigue siendo `n8n_api_key` aunque ya no
  designe una API key. Renombrarla exigiría migración y no aporta comportamiento.
- `N8nService::getCallbackUrl()` (`:244-249`) devuelve una URL placeholder
  (`/api/webhooks/n8n/tags`) que no corresponde a ninguna ruta real; el workflow
  no la usa. Es código muerto preexistente, ajeno a este cambio.
- La URL del backend está hardcodeada en el nodo `Aplicar Tags` en vez de leerse
  de una variable de entorno de n8n.
