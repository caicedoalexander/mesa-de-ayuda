# Integraciones Externas

## Gmail API

### Descripcion

Integracion bidireccional con Gmail para importacion automatica de emails como tickets y envio de notificaciones por email.

### Flujo OAuth2

```
1. Admin accede a /admin/settings → seccion Gmail
2. Sube client_secret.json (credenciales Google Cloud Console)
3. Sistema genera URL de autorizacion → GmailService::getAuthUrl()
4. Admin autoriza acceso a la cuenta de Gmail
5. Google redirige con codigo de autorizacion
6. GmailService::authenticate(code) intercambia por tokens
7. Refresh token se almacena cifrado en system_settings
8. El sistema renueva tokens automaticamente
```

### Importacion de emails

El `GmailWorkerCommand` ejecuta un loop continuo:

1. Consulta mensajes no leidos: `GmailService::getMessages('is:unread')`
2. Por cada mensaje, `parseMessage()` extrae:
   - Headers: From, To, Cc, Subject, Message-ID, In-Reply-To, References
   - Cuerpo: HTML y texto plano (extraccion recursiva de partes MIME)
   - Adjuntos: archivos y imagenes inline (con Content-ID)
3. Deteccion de bucles:
   - `isAutoReply()`: Detecta auto-respuestas via headers `Auto-Submitted`, `X-Auto-Response-Suppress`
   - `isSystemNotification()`: Detecta respuestas a emails enviados por el sistema
4. Threading: busca ticket existente por `gmail_thread_id`
   - Existe → crea comentario en el ticket
   - No existe → crea nuevo ticket
5. Descarga adjuntos via `downloadAttachment()` → S3 o almacenamiento local
6. Marca como leido: `markAsRead()`

### Envio de emails

`EmailService` usa `GmailService::sendEmail()` para enviar notificaciones:
- Construye mensaje MIME completo (headers, HTML body, adjuntos)
- Soporta multiples destinatarios (To, Cc)
- Incluye adjuntos del ticket/compra/PQRS
- Usa plantillas HTML de la tabla `email_templates`

### Configuracion

| Clave (SystemSettings) | Descripcion | Cifrado |
|---|---|---|
| `gmail_client_secret_path` | Ruta al archivo client_secret.json | No |
| `gmail_refresh_token` | Token de refresco OAuth2 | Si |
| `gmail_user_email` | Email del sistema (para deteccion de bucles) | No |
| `gmail_import_interval_minutes` | Intervalo de polling (default: 5) | No |

---

## n8n (Automatizacion)

### Descripcion

Integracion con n8n via webhooks para clasificacion automatica de tickets usando IA.

### Flujo outbound: ticket.created

Cuando se crea un ticket, `N8nService::sendTicketCreatedWebhook()` envia:

```json
{
  "ticket": {
    "id": 42,
    "ticket_number": "TKT-2026-00042",
    "subject": "Problema con impresora",
    "description": "<p>HTML del ticket</p>",
    "description_plain": "Texto plano del ticket",
    "status": "nuevo",
    "priority": "media",
    "created": "2026-02-13T10:30:00-05:00"
  },
  "requester": {
    "id": 15,
    "name": "Juan Perez",
    "email": "juan@ejemplo.com",
    "organization": "Departamento IT"
  },
  "attachments": [
    {
      "filename": "foto.jpg",
      "size": 204800,
      "mime_type": "image/jpeg"
    }
  ],
  "available_tags": [
    {"id": 1, "name": "Hardware"},
    {"id": 2, "name": "Software"}
  ],
  "callback_url": "https://sistema.com/api/webhooks/n8n/tags"
}
```

### Flujo callback: asignacion de tags

n8n procesa el ticket con IA y responde al `callback_url` con los tags sugeridos. El sistema los aplica automaticamente al ticket.

### Configuracion

| Clave (SystemSettings) | Descripcion | Cifrado |
|---|---|---|
| `n8n_enabled` | Habilitar/deshabilitar ('0'/'1') | No |
| `n8n_webhook_url` | URL del webhook en n8n | No |
| `n8n_api_key` | Clave de autenticacion | Si |
| `n8n_send_tags_list` | Incluir lista de tags en payload ('0'/'1') | No |
| `n8n_timeout` | Timeout de request en segundos | No |

### Prueba de conexion

Desde `/admin/settings` se puede ejecutar `N8nService::testConnection()` para verificar conectividad.

---

## WhatsApp (Evolution API)

### Descripcion

Notificaciones por WhatsApp a equipos internos cuando se crean nuevas entidades. Usa Evolution API como intermediario.

### Funcionamiento

```
Creacion de entidad
  └── NotificationDispatcherTrait::dispatchCreationNotifications()
      └── WhatsappService::sendNewEntityNotification()
          └── WhatsappService::sendMessage(number, text)
              └── POST {api_url}/message/sendText/{instance_name}
                  Headers: apikey: {api_key}
                  Body: { "number": "...", "text": "..." }
```

### Numeros por modulo

Cada modulo tiene un numero/grupo de WhatsApp configurable:

| Modulo | Clave | Descripcion |
|---|---|---|
| Tickets | `whatsapp_tickets_number` | Grupo del equipo de soporte |
| PQRS | `whatsapp_pqrs_number` | Grupo de servicio al cliente |
| Compras | `whatsapp_compras_number` | Grupo del area de compras |

### Alcance de notificaciones

- WhatsApp solo se envia al **crear** entidades (nuevo ticket, nueva compra, nuevo PQRS)
- Las actualizaciones (cambios de estado, comentarios) solo generan notificaciones por email

### Configuracion

| Clave (SystemSettings) | Descripcion | Cifrado |
|---|---|---|
| `whatsapp_enabled` | Habilitar/deshabilitar ('0'/'1') | No |
| `whatsapp_api_url` | URL base de Evolution API | No |
| `whatsapp_api_key` | Clave de autenticacion | Si |
| `whatsapp_instance_name` | Nombre de instancia en Evolution | No |
| `whatsapp_tickets_number` | Numero/grupo para tickets | No |
| `whatsapp_pqrs_number` | Numero/grupo para PQRS | No |
| `whatsapp_compras_number` | Numero/grupo para compras | No |

### Prueba de conexion

Desde `/admin/settings` se puede probar cada modulo via `WhatsappService::testConnection($module)`.

---

## AWS S3

### Descripcion

Almacenamiento opcional de archivos en AWS S3. Cuando no esta habilitado, los archivos se almacenan localmente en `webroot/uploads/`.

### Modo dual

```
GenericAttachmentTrait::saveGenericUploadedFile()
  │
  ├── S3Service::isEnabled() == true
  │   └── Sube a S3 → guarda ruta S3 en file_path
  │
  └── S3Service::isEnabled() == false
      └── Guarda en webroot/uploads/ → guarda ruta local en file_path
```

### URLs presignadas

Para archivos en S3, `S3Service::getPresignedUrl()` genera URLs temporales con expiracion configurable. Esto permite acceso seguro sin exponer las credenciales de S3.

### Imagenes de perfil

Las imagenes de perfil de usuario tambien soportan almacenamiento dual (S3 o local).

### Configuracion

Variables de entorno (en `app.php` / `app_local.php`):

| Variable | Descripcion | Default |
|---|---|---|
| `AWS_S3_ENABLED` | Habilitar S3 | `false` |
| `AWS_S3_BUCKET` | Nombre del bucket | - |
| `AWS_S3_REGION` | Region AWS | `us-east-1` |
| `AWS_S3_KEY` | Access Key ID | - |
| `AWS_S3_SECRET` | Secret Access Key | - |

---

## Cifrado de Credenciales

Todas las credenciales de integraciones externas se almacenan cifradas en la tabla `system_settings` usando `SettingsEncryptionTrait`:

- Metodo: `Security::encrypt()` de CakePHP con `SECURITY_SALT`
- Formato: prefijo `{encrypted}` + base64
- Claves cifradas: `gmail_refresh_token`, `whatsapp_api_key`, `n8n_api_key`
- Descifrado automatico al cargar configuraciones via `SettingsService::loadAll()`
