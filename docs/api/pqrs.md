# API de PQRS

Modulo de Peticiones, Quejas, Reclamos y Sugerencias con portal publico para ciudadanos/clientes.

**Controlador**: `src/Controller/PqrsController.php`
**Servicio**: `src/Service/PqrsService.php`

## Endpoints Publicos (sin autenticacion)

### Formulario publico

```
GET /pqrs/formulario
```

Muestra el formulario de radicacion de PQRS. No requiere autenticacion.

---

### Crear PQRS

```
POST /pqrs/formulario
```

**Body (form-data)**:

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `type` | string | Si | `peticion`, `queja`, `reclamo`, `sugerencia` |
| `subject` | varchar(255) | Si | Asunto |
| `description` | text | Si | Descripcion detallada |
| `requester_name` | varchar(255) | Si | Nombre del solicitante |
| `requester_email` | varchar(255) | Si | Email del solicitante |
| `requester_phone` | varchar(50) | No | Telefono |
| `requester_id_number` | varchar(50) | No | Numero de identificacion |
| `requester_address` | text | No | Direccion |
| `requester_city` | varchar(100) | No | Ciudad |
| `attachments[]` | file | No | Archivos adjuntos |

**Proceso interno**:
1. Genera numero `PQRS-YYYY-NNNNN`
2. Registra IP y User-Agent del solicitante
3. Calcula SLA segun tipo (via SlaManagementService)
4. Guarda adjuntos
5. Despacha notificaciones (email al equipo + confirmacion al solicitante + WhatsApp)

**Respuesta**: Redireccion a pagina de confirmacion.

---

### Pagina de confirmacion

```
GET /pqrs/success/{pqrsNumber}
```

Muestra el numero de radicado al solicitante.

---

## Endpoints Internos (requieren autenticacion)

### Listar PQRS

```
GET /pqrs
GET /pqrs.json
```

**Filtros disponibles**:

| Parametro | Tipo | Descripcion |
|---|---|---|
| `status` | string | Estado |
| `type` | string | Tipo de PQRS |
| `priority` | string | Prioridad |
| `assignee_id` | int | Agente asignado |
| `search` | string | Busqueda en asunto/descripcion |

---

### Ver detalle

```
GET /pqrs/view/{id}
GET /pqrs/view/{id}.json
```

**Respuesta**: PQRS completo con datos del solicitante, comentarios, adjuntos, historial e informacion SLA.

Nota: Los campos `ip_address` y `user_agent` estan ocultos en las respuestas JSON por configuracion de la entidad.

---

### Agregar comentario

```
POST /pqrs/add-comment/{id}
```

**Body (form-data)**:

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `body` | string | Si | Contenido del comentario |
| `comment_type` | string | No | `public` (default), `internal` |
| `attachments[]` | file | No | Archivos adjuntos |
| `status` | string | No | Nuevo estado (cambio simultaneo) |

---

### Asignar agente

```
POST /pqrs/assign/{id}
```

**Body**:

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `assignee_id` | int | Si | ID del agente |

---

### Cambiar estado

```
POST /pqrs/change-status/{id}
```

**Body**:

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `status` | string | Si | Nuevo estado |

**Estados validos**: `nuevo`, `en_revision`, `en_proceso`, `resuelto`, `cerrado`

---

### Cambiar prioridad

```
POST /pqrs/change-priority/{id}
```

**Body**:

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `priority` | string | Si | Nueva prioridad |

---

### Descargar adjunto

```
GET /pqrs/download/{id}
```

**Parametros**: `id` es el ID del adjunto.

---

### Ver historial

```
GET /pqrs/history/{id}
GET /pqrs/history/{id}.json
```

---

### Estadisticas

```
GET /pqrs/statistics
GET /pqrs/statistics.json
```

**Filtros**:

| Parametro | Tipo | Descripcion |
|---|---|---|
| `date_range` | string | `all`, `30days`, `7days`, `today`, `custom` |
| `start_date` | string | Fecha inicio |
| `end_date` | string | Fecha fin |

**Respuesta**: Distribucion por tipo, estado, prioridad, canal; metricas SLA (primera respuesta y resolucion); rendimiento de agentes; tendencias.

---

## Acciones Masivas

### Asignacion masiva

```
POST /pqrs/bulk-assign
```

**Body**:

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `pqrs_ids[]` | int[] | Si | IDs de PQRS |
| `assignee_id` | int | Si | Agente a asignar |

### Cambio masivo de prioridad

```
POST /pqrs/bulk-change-priority
```

**Body**:

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `pqrs_ids[]` | int[] | Si | IDs de PQRS |
| `priority` | string | Si | Nueva prioridad |

### Eliminacion masiva

```
POST /pqrs/bulk-delete
```

**Body**:

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `pqrs_ids[]` | int[] | Si | IDs a eliminar |

---

## SLA por Tipo de PQRS

| Tipo | Primera respuesta | Resolucion |
|---|---|---|
| Peticion | 2 dias | 5 dias |
| Queja | 1 dia | 3 dias |
| Reclamo | 1 dia | 3 dias |
| Sugerencia | 3 dias | 7 dias |

---

## Permisos

| Accion | admin | agent | compras | servicio_cliente | requester |
|---|---|---|---|---|---|
| Formulario publico | N/A | N/A | N/A | N/A | N/A (sin auth) |
| Listar | Todos | Solo lectura | Solo lectura | Todos | No |
| Ver | Si | Si | Si | Si | No |
| Comentar | Si | No | No | Si | No |
| Asignar | Si | No | No | Si | No |
| Cambiar estado | Si | No | No | Si | No |
| Bulk actions | Si | No | No | Si | No |
