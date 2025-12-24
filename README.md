# Mesa de Ayuda - Sistema de tickets (CakePHP)

Aplicación de soporte y gestión de incidencias construida sobre CakePHP 5.x.
Permite la creación y seguimiento de tickets, comentarios, adjuntos, etiquetas
y flujos básicos para PQRS (peticiones, quejas, reclamos y sugerencias).

**Características principales:**
- Administración de tickets (creación, estado, asignación).
- Comentarios y historial de actividad por ticket.
- Gestión de adjuntos y plantillas de correo.
- Etiquetas y seguimiento por usuarios y equipos.
- Módulo de PQRS con comentarios y adjuntos.
- Migraciones y seeds incluidos para datos iniciales.

**Estructura relevante del proyecto:**
- `src/` — código de la aplicación (Controllers, Models, Services, Views).
- `config/` — configuración de la aplicación y credenciales locales.
- `Migrations/` — scripts de migración y seeds para la base de datos.
- `templates/` y `webroot/` — vistas y activos públicos.

## Requisitos
- PHP 8.1 o superior
- Composer
- Servidor de base de datos compatible (MySQL/MariaDB recomendado)

## Instalación rápida
1. Clona el repositorio y entra al directorio del proyecto.
2. Instala dependencias:

```bash
composer install
```

3. Crea el archivo de configuración local copiando el ejemplo y ajusta la base de datos:

```bash
cp config/app_local.example.php config/app_local.php
# Edita config/app_local.php para poner credenciales de DB y otros valores
```

4. Ejecuta migraciones y seeds:

```bash
bin/cake migrations migrate
bin/cake migrations seed
```

5. Inicia el servidor de desarrollo (opcional):

```bash
bin/cake server -p 8765
```

Visita `http://localhost:8765` en tu navegador.

## Desarrollo y contribución
- Revisa los tests en `tests/` y ejecuta con PHPUnit o la herramienta configurada.
- Antes de enviar cambios, asegúrate de aplicar `phpcs`/`phpstan` y las pruebas.
- Abre un issue o pull request para discutir cambios mayores.

## Licencia
Revisa el archivo de licencia del repositorio si existe. Si no, consulta
al mantenedor del proyecto.

---

Si quieres, puedo ajustar esta descripción (más técnica, más breve o incluir
instrucciones para Docker). Dime qué prefieres.
