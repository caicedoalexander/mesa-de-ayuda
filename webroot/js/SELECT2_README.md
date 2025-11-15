# Select2 - Guía de Uso

Select2 está implementado en todo el sistema para mejorar los elementos `<select>` con búsqueda, tags, y mejor UX.

## Uso Básico

Por defecto, **todos los elementos `<select>`** se convierten automáticamente en Select2 con la configuración estándar.

```html
<select name="status" class="form-select">
    <option value="">Selecciona un estado</option>
    <option value="nuevo">Nuevo</option>
    <option value="abierto">Abierto</option>
    <option value="pendiente">Pendiente</option>
    <option value="resuelto">Resuelto</option>
</select>
```

## Opciones Personalizadas

### Placeholder Personalizado

```html
<select name="priority" data-placeholder="Selecciona una prioridad">
    <option value="baja">Baja</option>
    <option value="media">Media</option>
    <option value="alta">Alta</option>
    <option value="urgente">Urgente</option>
</select>
```

### Deshabilitar Botón de Limpiar

```html
<select name="category" data-allow-clear="false">
    <option value="soporte">Soporte</option>
    <option value="ventas">Ventas</option>
</select>
```

### Tags (Crear Opciones)

```html
<select name="keywords" data-tags="true" multiple>
    <option value="bug">Bug</option>
    <option value="feature">Feature</option>
</select>
```

### Ignorar Select2 en un Select

```html
<select name="simple" data-select2-ignore>
    <option value="1">Opción 1</option>
    <option value="2">Opción 2</option>
</select>
```

## Clases Especiales

### Búsqueda de Usuarios (AJAX)

```html
<select name="assignee_id" class="select2-users">
    <option value="">Buscar usuario...</option>
</select>
```

### Tags Múltiples

```html
<select name="tags[]" class="select2-tags" multiple>
    <option value="bug">Bug</option>
    <option value="feature">Feature</option>
</select>
```

### Selección Múltiple con Límite

```html
<select name="followers[]" class="select2-multiple-limit" data-max-selections="3" multiple>
    <option value="1">Usuario 1</option>
    <option value="2">Usuario 2</option>
    <option value="3">Usuario 3</option>
</select>
```

## Templates Personalizados

### Opciones con Iconos

```php
<?= $this->Form->select('status', [
    'nuevo' => 'Nuevo',
    'abierto' => 'Abierto',
    'resuelto' => 'Resuelto'
], [
    'class' => 'form-select',
    'data-icon' => 'ticket' // Agregar data-icon a cada option
]) ?>
```

JavaScript:
```javascript
$('#status').select2({
    theme: 'bootstrap-5',
    templateResult: window.select2TemplateWithIcon,
    templateSelection: window.select2TemplateWithIcon
});
```

### Opciones con Avatar

```html
<select id="user-select">
    <option value="1" data-avatar="/img/avatars/user1.jpg" data-email="user1@example.com">
        John Doe
    </option>
    <option value="2" data-avatar="/img/avatars/user2.jpg" data-email="user2@example.com">
        Jane Smith
    </option>
</select>
```

JavaScript:
```javascript
$('#user-select').select2({
    theme: 'bootstrap-5',
    templateResult: window.select2TemplateWithAvatar,
    templateSelection: window.select2TemplateWithAvatar
});
```

## Contenido Dinámico

Si se agrega contenido dinámicamente vía AJAX, re-inicializar Select2:

```javascript
// Después de cargar contenido AJAX
$.ajax({
    url: '/api/form',
    success: function(html) {
        $('#dynamic-container').html(html);

        // Re-inicializar Select2 en el nuevo contenido
        window.reinitializeSelect2('#dynamic-container');
    }
});
```

## Tamaños

### Select Pequeño

```html
<select class="form-select form-select-sm">
    <option>Opción 1</option>
</select>
```

### Select Grande

```html
<select class="form-select form-select-lg">
    <option>Opción 1</option>
</select>
```

## Eventos

### Escuchar Cambios

```javascript
$('#assignee').on('select2:select', function (e) {
    const data = e.params.data;
    console.log('Seleccionado:', data.id, data.text);
});

$('#assignee').on('select2:unselect', function (e) {
    const data = e.params.data;
    console.log('Deseleccionado:', data.id, data.text);
});
```

### Abrir/Cerrar Programáticamente

```javascript
// Abrir dropdown
$('#assignee').select2('open');

// Cerrar dropdown
$('#assignee').select2('close');
```

### Limpiar Selección

```javascript
$('#assignee').val(null).trigger('change');
```

## Estilos Personalizados

Los estilos de Select2 están personalizados en `webroot/css/styles.css` con:

- Colores del tema Bootstrap 5
- Enfoque con color primario (`--primary-color`)
- Dropdown con sombra
- Tags con color primario
- Estados hover y focus mejorados

## Ejemplos Completos

### Selector de Agente con Búsqueda

```php
<?= $this->Form->control('assignee_id', [
    'type' => 'select',
    'options' => $agents,
    'empty' => 'Selecciona un agente',
    'class' => 'form-select select2-users',
    'label' => 'Asignar a'
]) ?>
```

### Selector de Etiquetas Múltiples

```php
<?= $this->Form->control('tags._ids', [
    'type' => 'select',
    'multiple' => true,
    'options' => $tags,
    'class' => 'form-select select2-tags',
    'label' => 'Etiquetas'
]) ?>
```

### Selector de Prioridad Simple

```php
<?= $this->Form->control('priority', [
    'type' => 'select',
    'options' => [
        'baja' => 'Baja',
        'media' => 'Media',
        'alta' => 'Alta',
        'urgente' => 'Urgente'
    ],
    'class' => 'form-select',
    'label' => 'Prioridad',
    'data-placeholder' => 'Selecciona la prioridad'
]) ?>
```

## Troubleshooting

### Select2 no se inicializa

1. Verificar que jQuery se carga antes de Select2
2. Verificar que `select2-init.js` se incluye después de Select2
3. Verificar la consola del navegador para errores

### Dropdown no se abre

- Verificar z-index de contenedores padre
- Asegurarse de que el select no esté dentro de un modal con overflow hidden

### Ancho incorrecto

```javascript
// Forzar ancho específico
$('#my-select').select2({
    width: '100%' // o '300px', 'resolve', etc.
});
```

## Documentación Oficial

Para más opciones y configuraciones avanzadas:
https://select2.org/configuration/options-api
