# Webhooks y Rutas Administrativas

## Webhooks n8n

### Webhook de salida: ticket.created

Cuando se crea un ticket, el sistema envia un webhook POST a n8n (si esta habilitado).

**Trigger**: Creacion de ticket (email, web o API)
**Servicio**: `N8nService::sendTicketCreatedWebhook()`
**URL destino**: Configurada en `n8n_webhook_url` (SystemSettings)

**Payload**:

```json
{
  "ticket": {
    "id": 42,
    "ticket_number": "TKT-2026-00042",
    "subject": "Asunto del ticket",
    "description": "<p>Descripcion HTML</p>",
    "description_plain": "Descripcion texto plano",
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

**Notas**:
- `available_tags` solo se incluye si `n8n_send_tags_list` esta habilitado
- `callback_url` es la URL donde n8n debe enviar la respuesta con los tags asignados
- Autenticacion via header con `n8n_api_key`
- El envio es asincrono (no bloquea la creacion del ticket)

### Webhook de entrada: callback de tags

n8n procesa el ticket (clasificacion AI) y responde al callback URL.

**URL**: Definida por `N8nService::getCallbackUrl()`
**Metodo**: POST
**Proposito**: Asignar tags automaticamente al ticket basado en clasificacion AI

---

## Rutas Administrativas

Todas las rutas administrativas requieren autenticacion con rol `admin`. Prefijo: `/admin`.

### Panel de Configuracion

```
GET /admin/settings
```

Panel principal de configuracion del sistema. Incluye:

- Configuracion de Gmail (OAuth, email del sistema, intervalo de importacion)
- Configuracion de WhatsApp (Evolution API URL, API key, instancia, numeros por modulo)
- Configuracion de n8n (webhook URL, API key, opciones)
- Prueba de conexion para cada integracion

#### Autenticacion Gmail

```
GET /admin/settings/gmail-auth
```

Inicia el flujo OAuth2 con Google. Redirige al usuario a Google para autorizar acceso.

#### Prueba de Gmail

```
POST /admin/settings/test-gmail
```

Envia un email de prueba para verificar la configuracion de Gmail.

#### Prueba de WhatsApp

```
POST /admin/settings/test-whatsapp
```

Envia un mensaje de prueba al numero configurado para verificar conectividad con Evolution API.

#### Prueba de n8n

```
POST /admin/settings/test-n8n
```

Envia un payload de prueba al webhook de n8n para verificar conectividad.

---

### Gestion de Plantillas de Email

```
GET /admin/settings/email-templates
```

Lista todas las plantillas de notificacion por email.

```
GET /admin/settings/edit-template/{id}
POST /admin/settings/edit-template/{id}
```

Edicion de plantilla: asunto, cuerpo HTML, variables disponibles, estado activo.

```
GET /admin/settings/preview-template/{id}
```

Vista previa de la plantilla renderizada con datos de ejemplo.

---

### Gestion de Usuarios

```
GET /admin/settings/users
```

Lista de usuarios del sistema con filtros por rol y estado.

```
GET /admin/settings/add-user
POST /admin/settings/add-user
```

Crear nuevo usuario (nombre, email, rol, organizacion).

```
GET /admin/settings/edit-user/{id}
POST /admin/settings/edit-user/{id}
```

Editar usuario existente.

```
POST /admin/settings/deactivate-user/{id}
POST /admin/settings/activate-user/{id}
```

Activar/desactivar usuario (soft delete).

---

### Gestion de Etiquetas

```
GET /admin/settings/tags
```

Lista de etiquetas disponibles.

```
POST /admin/settings/add-tag
```

Crear etiqueta (nombre, color hexadecimal).

```
POST /admin/settings/edit-tag/{id}
```

Editar etiqueta.

```
POST /admin/settings/delete-tag/{id}
```

Eliminar etiqueta.

---

### Gestion de Organizaciones

```
GET /admin/settings/organizations
```

Lista de organizaciones.

```
POST /admin/settings/add-organization
POST /admin/settings/edit-organization/{id}
POST /admin/settings/delete-organization/{id}
```

CRUD de organizaciones (nombre, dominio de email).

---

### Gestion de SLA

```
GET /admin/sla-management
```

Panel de configuracion de SLA para todos los modulos.

```
POST /admin/sla-management/save
```

**Body**:

| Campo | Tipo | Descripcion |
|---|---|---|
| `sla_pqrs_peticion_first_response_days` | int | Dias para primera respuesta (peticion) |
| `sla_pqrs_peticion_resolution_days` | int | Dias para resolucion (peticion) |
| `sla_pqrs_queja_first_response_days` | int | Dias (queja) |
| `sla_pqrs_queja_resolution_days` | int | Dias (queja) |
| `sla_pqrs_reclamo_first_response_days` | int | Dias (reclamo) |
| `sla_pqrs_reclamo_resolution_days` | int | Dias (reclamo) |
| `sla_pqrs_sugerencia_first_response_days` | int | Dias (sugerencia) |
| `sla_pqrs_sugerencia_resolution_days` | int | Dias (sugerencia) |
| `sla_compras_first_response_days` | int | Dias para compras |
| `sla_compras_resolution_days` | int | Dias para compras |

```
GET /admin/sla-management/preview
```

Vista previa del estado actual de SLA con entidades proximas a vencer o ya vencidas.

---

### Gestion de Archivos de Configuracion

```
POST /admin/config-files/upload
```

Subir archivos de configuracion (ej: client_secret.json para Gmail).

```
GET /admin/config-files/download/{type}
```

Descargar archivo de configuracion existente.

```
POST /admin/config-files/delete/{type}
```

Eliminar archivo de configuracion.

---

## Health Check

```
GET /health
```

Endpoint para monitoreo de Docker. Verifica conectividad con la base de datos.

**Respuesta (JSON)**:

```json
{
  "status": "ok",
  "database": "connected",
  "timestamp": "2026-02-13T15:30:00-05:00"
}
```
