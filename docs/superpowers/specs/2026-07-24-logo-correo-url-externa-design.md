# Logo de correo servido desde un host público

**Fecha:** 2026-07-24
**Estado:** implementado (commits `f7df737`, `3092138`, `87ac61c`)

## Problema

El pie de todas las notificaciones por correo renderiza:

```html
<img src="{App.fullBaseUrl}/img/logo-mesa-ayuda.svg" alt="" width="24" height="24" />
```

El logo no se muestra en Gmail por dos causas acumuladas:

1. **Restricción de red.** El servidor de la mesa de ayuda solo acepta conexiones desde IPs
   específicas. Los servidores de Google, que son quienes descargan y cachean las imágenes de
   un correo antes de mostrarlas, no están en esa lista y reciben una conexión rechazada.
2. **Formato.** Gmail no renderiza imágenes SVG en correos. Tampoco Outlook ni Yahoo; solo
   Apple Mail las soporta. Los clientes bloquean el formato porque un SVG puede contener
   script embebido.

Resolver solo (1) —cambiar el host— deja el ícono roto igual por (2).

## Solución

Servir el logo como **PNG** desde el WordPress público de la organización
(`www.copcsa.com`), con la URL fija en código.

La URL fija es coherente con el diseño ya existente de `EmailBrand`, cuyo docblock declara:
*"Intentionally a code-side configuration: changing these requires a deploy, which is fine for
a single-organization installation."* No se introduce una clave en `system_settings`.

## Componentes

### 1. Asset `webroot/img/logo-mesa-ayuda.png` (nuevo)

Generado desde `webroot/img/logo-mesa-ayuda.svg` (viewBox cuadrado `0 0 1024 1024`).

- Dimensiones: **96×96 px**, fondo transparente.
- Razón del tamaño: el logo se muestra a 24×24; 4× cubre pantallas de alta densidad y el peso
  queda en pocos KB.
- Se versiona en el repo junto al SVG para conservar la fuente exacta de lo que se sirve, aunque
  el archivo servido en producción sea la copia alojada en WordPress.
- Herramienta: `npx --yes sharp-cli` (el entorno no tiene ImageMagick, rsvg-convert ni Inkscape;
  la extensión PHP disponible es GD, que no lee SVG). Si `sharp-cli` no se instala, el respaldo
  es renderizar el SVG en Chrome y exportar el canvas a PNG; si tampoco, el usuario exporta el
  PNG desde su herramienta de diseño. La conversión es un paso de una sola vez, no una
  dependencia del build.

### 2. `src/Notification/Email/EmailBrand.php`

`logoUrl()` deja de construir la URL desde `Configure::read('App.fullBaseUrl')` y devuelve la
URL absoluta del PNG alojado en copcsa.com.

- El import `use Cake\Core\Configure;` queda huérfano por este cambio y se elimina.
- El método sigue siendo `public static function logoUrl(): string` — la firma no cambia, así
  que el único consumidor no se ve afectado.

### 3. `src/Notification/Email/Component/EmailFrame.php`

**Sin cambios.** Los atributos `width="24" height="24"` ya escalan la imagen al tamaño de
display, y el `alt=""` es correcto: el logo es decorativo porque el nombre de la marca aparece
como texto inmediatamente al lado.

### 4. Tests

Dos aserciones dejan de describir el comportamiento real:

- `tests/TestCase/Notification/Email/EmailBrandTest.php`
  `testLogoUrlReturnsAbsoluteUrlFromFullBaseUrl` afirma que la URL deriva de `fullBaseUrl`. Se
  reemplaza por un test que afirma la URL absoluta exacta. El `setUp`/`tearDown` que manipula
  `App.fullBaseUrl` queda huérfano y se elimina de esta clase.
- `tests/TestCase/Notification/Email/Component/EmailFrameTest.php:38` busca
  `logo-mesa-ayuda.svg` en el HTML renderizado; pasa a `logo-mesa-ayuda.png`. Esta clase
  también tiene el andamiaje de `fullBaseUrl` en `setUp`/`tearDown`, y `EmailFrame` solo lee
  configuración a través de `EmailBrand`: al dejar `EmailBrand` de consultar `Configure`, el
  andamiaje queda huérfano. Se elimina, junto con la propiedad `$previousFullBaseUrl` y el
  import `use Cake\Core\Configure;`. Ninguna otra aserción de la clase depende de él.

## Dependencia externa

La URL final la determina WordPress al subir el archivo a la biblioteca de medios. Si ya existe
un `logo-mesa-ayuda.png` en la biblioteca, WordPress añade un sufijo (`logo-mesa-ayuda-1.png`).

**Orden obligatorio:**

1. Generar el PNG.
2. El usuario lo sube manualmente por wp-admin y entrega la URL resultante.
3. Verificar la URL con `curl -I` antes de escribirla en el código.
4. Fijar la URL en `EmailBrand` y actualizar los tests.

No se escribe la URL en el código antes de confirmarla; una URL adivinada reintroduce
exactamente el fallo que este cambio busca eliminar.

## Verificación

- `curl -sS -I <url-del-png>` responde `200` con `Content-Type: image/png`.
- `vendor/bin/phpunit --filter "EmailBrandTest|EmailFrameTest"` en verde.
- `composer cs-fix && composer cs-check` limpio.
- Prueba manual de extremo a extremo: crear un ticket y abrir la notificación resultante en
  Gmail (cliente web y móvil) confirmando que el logo se ve.

## Fuera de alcance

- Hacer la URL del logo configurable desde `/admin` (clave en `SettingKeys` + migración + UI).
- Rediseñar el pie del correo.
- Consolidar el SVG duplicado: `webroot/img/logo-mesa-ayuda.svg` y
  `webroot/img/logos/logo-mesa-ayuda.svg` son idénticos (5572 bytes). El primero existe para el
  correo, el segundo lo consumen las vistas (`login.php`, `head.php`,
  `cell/TicketsSidebar/display.php`). Se documenta aquí porque se encontró durante la
  exploración, no porque este cambio lo requiera.
- Embeber el logo inline como parte MIME con `Content-ID` (CID) en vez de enlazarlo por URL. Es
  la solución canónica para logos en correo transaccional —elimina la dependencia externa, el
  riesgo de disponibilidad y la superficie de tracking— pero se descarta por costo real:
  `GmailService::createMimeMessage()` (`src/Service/GmailService.php:1147`) construye hoy un
  `multipart/mixed` plano con un solo boundary, y CID exige anidar un subárbol
  `multipart/related`, lo que es una refactorización del camino de envío, no un cambio de una
  línea. El lado entrante ya parsea imágenes inline con CID
  (`src/Service/GmailService.php:453-460`), de ahí la asimetría.

## Riesgos aceptados

Enlazar una imagen externa desde correos salientes introduce dos bordes nuevos, ambos de
severidad baja: es infraestructura first-party en otro host propio de la organización, no un
tercero no confiable, y el payload es cosmético —las imágenes no ejecutan script—.

- **Privacidad:** en Gmail la petición la hace el proxy de Google y la IP del destinatario no se
  expone, pero Outlook de escritorio, Thunderbird y Apple Mail la hacen directo — el log de
  acceso del WordPress registra IP y timestamp por cada apertura, un acuse de lectura que antes
  no existía.
- **Integridad:** quien comprometa el WordPress controla una imagen que se renderiza dentro de
  todo correo saliente. `<img>` no tiene equivalente a Subresource Integrity.
