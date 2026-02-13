# API de Compras

Modulo de gestion de compras con flujo de aprobacion y trazabilidad.

**Controlador**: `src/Controller/ComprasController.php`
**Servicio**: `src/Service/ComprasService.php`

## Endpoints

### Listar compras

```
GET /compras
GET /compras.json
```

**Filtros disponibles**:

| Parametro | Tipo | Descripcion |
|---|---|---|
| `status` | string | Estado de la compra |
| `priority` | string | Prioridad |
| `assignee_id` | int | Responsable asignado |
| `search` | string | Busqueda en asunto/descripcion |

**Respuesta**: Lista paginada de compras con datos de solicitante y responsable.

---

### Ver detalle de compra

```
GET /compras/view/{id}
GET /compras/view/{id}.json
```

**Respuesta**: Compra completa con comentarios, adjuntos, historial e informacion SLA.

---

### Agregar comentario

```
POST /compras/add-comment/{id}
```

**Body (form-data)**:

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `body` | string | Si | Contenido del comentario |
| `comment_type` | string | No | `public` (default), `internal` |
| `attachments[]` | file | No | Archivos adjuntos |
| `status` | string | No | Nuevo estado (cambio simultaneo) |

---

### Asignar responsable

```
POST /compras/assign/{id}
```

**Body**:

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `assignee_id` | int | Si | ID del usuario a asignar |

---

### Cambiar estado

```
POST /compras/change-status/{id}
```

**Body**:

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `status` | string | Si | Nuevo estado |

**Estados validos**: `nuevo`, `en_revision`, `aprobado`, `en_proceso`, `completado`, `rechazado`

---

### Cambiar prioridad

```
POST /compras/change-priority/{id}
```

**Body**:

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `priority` | string | Si | Nueva prioridad |

**Prioridades validas**: `baja`, `media`, `alta`, `urgente`

---

### Descargar adjunto

```
GET /compras/download/{id}
```

**Parametros**: `id` es el ID del adjunto.

**Respuesta**: Descarga del archivo.

---

### Ver historial

```
GET /compras/history/{id}
GET /compras/history/{id}.json
```

**Respuesta**: Log de cambios con usuario, campo, valores y descripcion.

---

### Convertir a ticket

```
POST /compras/convert-to-ticket/{id}
```

Convierte la compra en un ticket de soporte. Copia comentarios y adjuntos. La compra cambia a estado `convertido`.

**Respuesta**: Redireccion al nuevo ticket creado.

---

### Estadisticas

```
GET /compras/statistics
GET /compras/statistics.json
```

**Filtros**:

| Parametro | Tipo | Descripcion |
|---|---|---|
| `date_range` | string | `all`, `30days`, `7days`, `today`, `custom` |
| `start_date` | string | Fecha inicio |
| `end_date` | string | Fecha fin |

**Respuesta**: Distribucion por estado, prioridad; metricas SLA; tasa de aprobacion; tendencias.

---

## Acciones Masivas

### Asignacion masiva

```
POST /compras/bulk-assign
```

**Body**:

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `compra_ids[]` | int[] | Si | IDs de compras |
| `assignee_id` | int | Si | Responsable a asignar |

### Cambio masivo de prioridad

```
POST /compras/bulk-change-priority
```

**Body**:

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `compra_ids[]` | int[] | Si | IDs de compras |
| `priority` | string | Si | Nueva prioridad |

### Eliminacion masiva

```
POST /compras/bulk-delete
```

**Body**:

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `compra_ids[]` | int[] | Si | IDs de compras a eliminar |

---

## SLA en Compras

Cada compra tiene dos metricas SLA calculadas al momento de creacion:

| Campo | Descripcion | Default |
|---|---|---|
| `first_response_sla_due` | Plazo para primera respuesta | 1 dia |
| `resolution_sla_due` | Plazo para resolucion | 3 dias |

La configuracion de SLA se administra desde `/admin/sla-management`.

---

## Permisos

| Accion | admin | agent | compras | servicio_cliente | requester |
|---|---|---|---|---|---|
| Listar | Todos | Solo lectura | Todos | Solo lectura | No |
| Ver | Si | Si | Si | Si | No |
| Comentar | Si | No | Si | No | No |
| Asignar | Si | No | Si | No | No |
| Cambiar estado | Si | No | Si | No | No |
| Convertir a ticket | Si | No | Si | No | No |
| Bulk actions | Si | No | Si | No | No |
