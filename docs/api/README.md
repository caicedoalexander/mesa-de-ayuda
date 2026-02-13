# Convenciones API

## Formato de Respuesta

El sistema soporta respuestas JSON agregando la extension `.json` a cualquier URL.

```
GET /tickets.json
GET /tickets/view/42.json
```

### Respuesta exitosa

```json
{
  "success": true,
  "data": { ... }
}
```

### Respuesta de error

```json
{
  "success": false,
  "message": "Descripcion del error"
}
```

## Autenticacion

La API usa autenticacion basada en sesion de CakePHP (cookie). Se requiere iniciar sesion previamente en la interfaz web.

### Rutas publicas (sin autenticacion)

| Ruta | Descripcion |
|---|---|
| `GET /pqrs/formulario` | Formulario publico de PQRS |
| `POST /pqrs/create` | Crear PQRS desde formulario |
| `GET /pqrs/success/{pqrsNumber}` | Pagina de confirmacion |
| `GET /health` | Health check (Docker) |
| `GET /users/login` | Pagina de login |

### Rutas autenticadas

Todas las demas rutas requieren sesion activa. El acceso esta controlado por roles:

| Rol | Acceso completo | Solo lectura |
|---|---|---|
| `admin` | Todos los modulos + Admin | - |
| `agent` | Tickets | Compras, PQRS |
| `compras` | Compras | Tickets, PQRS |
| `servicio_cliente` | PQRS | Tickets, Compras |
| `requester` | Tickets propios | - |

## Codigos HTTP

| Codigo | Uso |
|---|---|
| 200 | Operacion exitosa |
| 302 | Redireccion (despues de crear/modificar) |
| 400 | Solicitud invalida (datos faltantes o incorrectos) |
| 401 | No autenticado |
| 403 | Sin permisos para la accion |
| 404 | Recurso no encontrado |
| 500 | Error interno del servidor |

## Convenciones de Rutas

- **Listado**: `GET /{modulo}` - Lista con filtros y paginacion
- **Detalle**: `GET /{modulo}/view/{id}` - Vista detallada de un registro
- **Acciones**: `POST /{modulo}/{accion}/{id}` - Modificaciones sobre un registro
- **Bulk**: `POST /{modulo}/bulk-{accion}` - Acciones masivas
- **Estadisticas**: `GET /{modulo}/statistics` - Dashboard del modulo
- **Historial**: `GET /{modulo}/history/{id}` - Log de cambios del registro

## Filtros de Listado

Los endpoints de listado soportan filtros via query string:

| Parametro | Descripcion | Ejemplo |
|---|---|---|
| `status` | Filtrar por estado | `?status=nuevo` |
| `priority` | Filtrar por prioridad | `?priority=alta` |
| `assignee_id` | Filtrar por agente asignado | `?assignee_id=5` |
| `search` | Busqueda por texto | `?search=impresora` |
| `view` | Vista predefinida | `?view=my_tickets` |

La paginacion sigue las convenciones de CakePHP con parametros `page`, `limit`, `sort`, `direction`.
