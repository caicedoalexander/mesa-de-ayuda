# Logo de correo servido desde host público — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el logo del pie de las notificaciones por correo se vea en Gmail, sirviéndolo como PNG desde `www.copcsa.com` en lugar de como SVG desde el servidor de la mesa de ayuda (que restringe por IP y es inalcanzable para Google).

**Architecture:** `EmailBrand::logoUrl()` es el único punto donde se construye la URL del logo, y `EmailFrame::renderFooter()` su único consumidor. El cambio sustituye la construcción dinámica desde `Configure::read('App.fullBaseUrl')` por una URL absoluta fija a un PNG alojado en el WordPress público de la organización. El asset PNG se genera desde el SVG existente y se versiona en el repo como fuente de lo que se sirve.

**Tech Stack:** PHP 8.5, CakePHP 5.x, PHPUnit, CakePHP CodeSniffer. Node + `sharp` (solo para la conversión SVG→PNG, una sola vez, fuera del repo).

**Spec:** `docs/superpowers/specs/2026-07-24-logo-correo-url-externa-design.md`

## Global Constraints

- `declare(strict_types=1);` es obligatorio en todo archivo PHP.
- Código y comentarios en inglés; documentación en español.
- `composer cs-fix && composer cs-check` antes de cualquier commit.
- La URL del logo va fija en código, **no** en `system_settings`. `EmailBrand` es
  deliberadamente configuración code-side: *"changing these requires a deploy, which is fine for
  a single-organization installation."*
- El PNG se muestra a 24×24 en el correo y se genera a 96×96 (4×, para pantallas de alta
  densidad), con fondo transparente.
- La firma `EmailBrand::logoUrl(): string` no cambia.
- **URL objetivo:** `https://www.copcsa.com/wp-content/uploads/2026/07/logo-mesa-ayuda.png`
  Esta es la URL *esperada*. La real la confirma la Task 2. Si WordPress devuelve otra (añade
  sufijo `-1` cuando el nombre ya existe en la biblioteca), usar la URL confirmada — literal y
  sin modificar — en todos los puntos de la Task 3.

## Estructura de archivos

| Archivo | Responsabilidad | Acción |
|---|---|---|
| `webroot/img/logo-mesa-ayuda.png` | Asset PNG 96×96 servido en el pie del correo; fuente versionada de la copia subida a WordPress | Crear |
| `src/Notification/Email/EmailBrand.php` | Constantes de marca del pie + URL del logo | Modificar |
| `tests/TestCase/Notification/Email/EmailBrandTest.php` | Contrato de `EmailBrand` | Modificar |
| `tests/TestCase/Notification/Email/Component/EmailFrameTest.php` | Contrato del HTML del marco de correo | Modificar |
| `src/Notification/Email/Component/EmailFrame.php` | Marco HTML del correo | **Sin cambios** — `width="24" height="24"` y `alt=""` siguen siendo correctos |

---

### Task 1: Generar el asset PNG desde el SVG

El entorno no tiene ImageMagick, rsvg-convert ni Inkscape, y la extensión PHP disponible es GD,
que no lee SVG. Se usa `sharp` (Node) instalado en el scratchpad, sin tocar las dependencias del
proyecto.

**Files:**
- Create: `webroot/img/logo-mesa-ayuda.png`
- Source: `webroot/img/logo-mesa-ayuda.svg` (existente, `viewBox="0 0 1024 1024"`, 5572 bytes)

**Interfaces:**
- Consumes: nada.
- Produces: el archivo `webroot/img/logo-mesa-ayuda.png`, 96×96 px, color type 6 (RGBA, con
  transparencia). La Task 2 lo sube a WordPress; la Task 3 depende de que exista.

- [ ] **Step 1: Instalar sharp en el scratchpad**

El scratchpad de esta sesión es
`C:\Users\sistema\AppData\Local\Temp\claude\C--Users-sistema-Documents-dev-mesadeayuda\a766a897-24e0-40f8-b1d1-e713798e7f27\scratchpad`.
Se instala ahí para no añadir `node_modules` ni `package.json` a un proyecto PHP.

```bash
SCRATCH="/c/Users/sistema/AppData/Local/Temp/claude/C--Users-sistema-Documents-dev-mesadeayuda/a766a897-24e0-40f8-b1d1-e713798e7f27/scratchpad"
mkdir -p "$SCRATCH/svg2png"
cd "$SCRATCH/svg2png" && npm init -y >/dev/null 2>&1 && npm install sharp --no-audit --no-fund
```

Esperado: `npm install` termina sin error y existe `$SCRATCH/svg2png/node_modules/sharp`.

- [ ] **Step 2: Convertir el SVG a PNG 96×96**

```bash
SCRATCH="/c/Users/sistema/AppData/Local/Temp/claude/C--Users-sistema-Documents-dev-mesadeayuda/a766a897-24e0-40f8-b1d1-e713798e7f27/scratchpad"
REPO="/c/Users/sistema/Documents/dev/mesadeayuda"
cd "$SCRATCH/svg2png" && node -e "
const sharp = require('sharp');
sharp('$REPO/webroot/img/logo-mesa-ayuda.svg', { density: 384 })
  .resize(96, 96, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
  .png({ compressionLevel: 9 })
  .toFile('$REPO/webroot/img/logo-mesa-ayuda.png')
  .then(i => console.log('OK', i.width + 'x' + i.height, i.size + ' bytes'))
  .catch(e => { console.error('FAIL', e.message); process.exit(1); });
"
```

`density: 384` fuerza a librsvg a rasterizar el SVG a alta resolución antes del resize, evitando
bordes dentados. `fit: 'contain'` con `alpha: 0` preserva la transparencia.

Esperado: `OK 96x96 <N> bytes`.

- [ ] **Step 3: Verificar la cabecera del PNG**

No basta con que el archivo exista: hay que confirmar dimensiones y canal alfa. Se lee el chunk
IHDR directamente, sin depender de herramientas externas.

```bash
cd /c/Users/sistema/Documents/dev/mesadeayuda && python -c "
import struct
d = open('webroot/img/logo-mesa-ayuda.png','rb').read()
assert d[:8] == b'\x89PNG\r\n\x1a\n', 'no es un PNG'
w, h = struct.unpack('>II', d[16:24])
print('width', w, 'height', h, 'bit_depth', d[24], 'color_type', d[25], 'bytes', len(d))
assert (w, h) == (96, 96), 'dimensiones incorrectas'
assert d[25] == 6, 'sin canal alfa (color_type debe ser 6 = RGBA)'
print('PNG OK')
"
```

Esperado: imprime las dimensiones y `PNG OK`. Si `color_type` no es 6, el fondo quedó opaco y
el logo se verá con un rectángulo blanco/negro sobre fondos de correo oscuros — repetir el
Step 2 revisando el `background`.

- [ ] **Step 4: Verificar visualmente el resultado**

Abrir `webroot/img/logo-mesa-ayuda.png` con la herramienta Read (renderiza imágenes) y confirmar
que se ve el logo completo, sin recortes ni bordes dentados. Comparar contra
`webroot/img/logo-mesa-ayuda.svg` si hay duda.

Esta comprobación es necesaria: los Steps 2 y 3 pasan igual si el SVG se rasterizara en blanco.

- [ ] **Step 5: Commit del asset**

```bash
cd /c/Users/sistema/Documents/dev/mesadeayuda
git add webroot/img/logo-mesa-ayuda.png
git commit -m "chore: agregar logo de mesa de ayuda en PNG 96x96 para correos

Gmail no renderiza SVG en correos. El PNG es el formato que soportan
Gmail, Outlook y Apple Mail por igual."
```

---

### Task 2: Subir el PNG a WordPress y confirmar la URL real

**Esta tarea la ejecuta la persona usuaria, no el agente.** El agente prepara, espera y verifica.
No continuar a la Task 3 sin una URL confirmada: escribir una URL adivinada en el código
reintroduce exactamente el fallo que este cambio elimina.

**Files:** ninguno del repo.

**Interfaces:**
- Consumes: `webroot/img/logo-mesa-ayuda.png` de la Task 1.
- Produces: la URL absoluta confirmada del PNG. La Task 3 la escribe en `EmailBrand` y en
  `EmailBrandTest`.

- [ ] **Step 1: Entregar el archivo y la instrucción**

Decirle a la persona usuaria, textualmente:

> El PNG está en `webroot/img/logo-mesa-ayuda.png`. Súbelo a la biblioteca de medios de
> `www.copcsa.com` (wp-admin → Medios → Añadir nuevo) y pásame la URL que WordPress te muestre
> en el panel de detalles del archivo.

- [ ] **Step 2: Verificar que la URL responde**

Con la URL que entregue la persona usuaria (aquí `<URL>`):

```bash
curl -sS -I -L --max-time 20 "<URL>" | head -10
```

Esperado: `HTTP/1.1 200 OK` y `Content-Type: image/png`.

Si responde 404, la URL está mal copiada. Si el `Content-Type` no es `image/png`, se subió el
archivo equivocado — volver al Step 1.

- [ ] **Step 3: Confirmar que es el mismo archivo**

```bash
cd /c/Users/sistema/Documents/dev/mesadeayuda
echo "local:  $(wc -c < webroot/img/logo-mesa-ayuda.png) bytes"
echo "remoto: $(curl -sS -I -L --max-time 20 "<URL>" | grep -i '^content-length' | tr -d '\r')"
```

Esperado: ambos tamaños coinciden. Si difieren, WordPress recomprimió o se subió otra versión;
no es bloqueante para el funcionamiento, pero anotarlo para no confundirse después.

- [ ] **Step 4: Registrar la URL confirmada**

Anotar la URL exacta. Es el único valor que la Task 3 necesita de esta tarea. Si difiere de
`https://www.copcsa.com/wp-content/uploads/2026/07/logo-mesa-ayuda.png`, la URL confirmada manda
sobre lo escrito en este plan.

---

### Task 3: Apuntar `EmailBrand` al PNG público

Los tests van primero. Al cambiar `EmailBrand`, la aserción de `EmailFrameTest` que busca
`logo-mesa-ayuda.svg` en el HTML también deja de ser cierta, así que ambos archivos de test se
actualizan en la misma tarea: de lo contrario la suite quedaría roja entre tareas.

**Files:**
- Modify: `src/Notification/Email/EmailBrand.php:6,19-28`
- Modify: `tests/TestCase/Notification/Email/EmailBrandTest.php` (archivo completo)
- Modify: `tests/TestCase/Notification/Email/Component/EmailFrameTest.php:8,13-30,38`

**Interfaces:**
- Consumes: la URL confirmada en la Task 2.
- Produces: `EmailBrand::logoUrl(): string` devuelve la URL absoluta del PNG. La firma no cambia,
  así que `EmailFrame::renderFooter()` (`src/Notification/Email/Component/EmailFrame.php:46-48`)
  sigue funcionando sin tocarse.

- [ ] **Step 1: Reescribir `EmailBrandTest` con el contrato nuevo**

`testLogoUrlReturnsAbsoluteUrlFromFullBaseUrl` afirma que la URL deriva de `App.fullBaseUrl`;
eso deja de ser cierto. Se reemplaza por una aserción de la URL exacta. El `setUp`/`tearDown`
que manipula `App.fullBaseUrl`, la propiedad `$previousFullBaseUrl` y el import de `Configure`
quedan huérfanos por este cambio y se eliminan.

Una sola aserción de igualdad exacta es suficiente: si alguien volviera a derivar la URL de
`App.fullBaseUrl`, `assertSame` fallaría. No hace falta un segundo test que manipule `Configure`
para probar lo mismo.

Contenido completo de `tests/TestCase/Notification/Email/EmailBrandTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Notification\Email;

use App\Notification\Email\EmailBrand;
use PHPUnit\Framework\TestCase;

final class EmailBrandTest extends TestCase
{
    public function testConstantsHaveExpectedValues(): void
    {
        self::assertSame('Compañía Operadora Portuaria Cafetera S.A.', EmailBrand::ORG_NAME);
        self::assertSame('Mesa de Ayuda', EmailBrand::TEAM_NAME);
    }

    /**
     * The helpdesk host restricts inbound traffic by IP, so Google's image proxy
     * cannot fetch anything served from it. The logo URL must stay pinned to the
     * public site and never be resolved against App.fullBaseUrl again.
     */
    public function testLogoUrlPointsToPublicPngHost(): void
    {
        self::assertSame(
            'https://www.copcsa.com/wp-content/uploads/2026/07/logo-mesa-ayuda.png',
            EmailBrand::logoUrl(),
        );
    }
}
```

Si la Task 2 confirmó una URL distinta, sustituirla en `testLogoUrlPointsToPublicPngHost`.

- [ ] **Step 2: Actualizar la aserción de `EmailFrameTest`**

En `tests/TestCase/Notification/Email/Component/EmailFrameTest.php`, cambiar la línea 38:

```php
        self::assertStringContainsString('logo-mesa-ayuda.svg', $html);
```

por:

```php
        self::assertStringContainsString('logo-mesa-ayuda.png', $html);
```

Y eliminar el andamiaje de `fullBaseUrl`, que queda huérfano porque `EmailFrame` solo lee
configuración a través de `EmailBrand`: borrar el import `use Cake\Core\Configure;` (línea 8),
la propiedad `private mixed $previousFullBaseUrl = null;` (línea 13) y los métodos `setUp` y
`tearDown` completos (líneas 15-30). Ninguna aserción de `testDoesNotRenderLegacyChrome`
depende de ellos.

El archivo queda así:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Notification\Email\Component;

use App\Notification\Email\Component\EmailFrame;
use App\Notification\Email\EmailBrand;
use PHPUnit\Framework\TestCase;

final class EmailFrameTest extends TestCase
{
    public function testRendersInnerBodyAndMinimalFooter(): void
    {
        $html = EmailFrame::render('<p>BODY</p>');

        self::assertStringContainsString('<p>BODY</p>', $html);
        // Footer shows the small logo and the two brand lines.
        self::assertStringContainsString('logo-mesa-ayuda.png', $html);
        self::assertStringContainsString(EmailBrand::TEAM_NAME, $html);
        self::assertStringContainsString(EmailBrand::ORG_NAME, $html);
    }

    public function testDoesNotRenderLegacyChrome(): void
    {
        $html = EmailFrame::render('<p>x</p>');

        // The old frame painted a 4px accent bar (`height:4px`), a header
        // strip with a logo and "Soporte Interno" subtitle, and a footer
        // with NIT/address/support email. None of that should remain.
        self::assertStringNotContainsString('height:4px', $html);
        self::assertStringNotContainsString('Soporte Interno', $html);
        self::assertStringNotContainsString('NIT', $html);
        self::assertStringNotContainsString('@operadoracafetera.com', $html);
    }
}
```

- [ ] **Step 3: Correr los tests y verificar que fallan**

```bash
cd /c/Users/sistema/Documents/dev/mesadeayuda
vendor/bin/phpunit --filter "EmailBrandTest|EmailFrameTest"
```

Esperado: **FAIL**. Dos fallos concretos:
- `testLogoUrlPointsToPublicPngHost` — el `EmailBrand` viejo devuelve
  `{App.fullBaseUrl}/img/logo-mesa-ayuda.svg`. Ojo: al haber eliminado el `setUp` que fijaba
  `mesa.example.com`, el valor que aparezca en el mensaje de fallo será el `App.fullBaseUrl`
  real del entorno de test (o una cadena vacía si no hay ninguno configurado). Cualquiera de los
  dos confirma el fallo esperado.
- `testRendersInnerBodyAndMinimalFooter` — falla porque el HTML aún contiene `.svg`.

Si los tests **pasan** en este punto, el cambio de `EmailBrand` ya se aplicó por error antes de
tiempo: revisar `git diff src/` antes de seguir.

- [ ] **Step 4: Implementar el cambio en `EmailBrand`**

Contenido completo de `src/Notification/Email/EmailBrand.php`:

```php
<?php
declare(strict_types=1);

namespace App\Notification\Email;

/**
 * Static branding constants for the email footer.
 *
 * Intentionally a code-side configuration: changing these requires a deploy,
 * which is fine for a single-organization installation.
 */
final class EmailBrand
{
    public const ORG_NAME = 'Compañía Operadora Portuaria Cafetera S.A.';
    public const TEAM_NAME = 'Mesa de Ayuda';

    /**
     * Absolute URL to the logo asset, hosted on the organization's public
     * website rather than on this app.
     *
     * Two constraints force this: the helpdesk host only accepts traffic from
     * whitelisted IPs, so Google's image proxy cannot fetch the asset, and mail
     * clients other than Apple Mail refuse to render SVG. The served file is a
     * 96x96 PNG; the source of truth for it lives in `webroot/img/`.
     */
    public static function logoUrl(): string
    {
        return 'https://www.copcsa.com/wp-content/uploads/2026/07/logo-mesa-ayuda.png';
    }
}
```

Cambios respecto al original: se elimina `use Cake\Core\Configure;` (huérfano tras quitar la
lectura de `App.fullBaseUrl`), se reescribe el docblock de `logoUrl()` porque el anterior
describía un comportamiento que ya no existe, y el cuerpo devuelve la URL absoluta.

Si la Task 2 confirmó una URL distinta, es la que va aquí.

- [ ] **Step 5: Correr los tests y verificar que pasan**

```bash
cd /c/Users/sistema/Documents/dev/mesadeayuda
vendor/bin/phpunit --filter "EmailBrandTest|EmailFrameTest"
```

Esperado: **PASS**, 4 tests (2 de `EmailBrandTest`, 2 de `EmailFrameTest`).

- [ ] **Step 6: Correr la suite completa**

```bash
cd /c/Users/sistema/Documents/dev/mesadeayuda
composer test
```

Esperado: verde. Confirma que ningún otro test dependía de que el logo fuera un SVG servido
desde `fullBaseUrl`.

- [ ] **Step 7: Estilo de código**

```bash
cd /c/Users/sistema/Documents/dev/mesadeayuda
composer cs-fix && composer cs-check
```

Esperado: `cs-check` sin errores. Si `cs-fix` modificó archivos, volver a correr el Step 5 antes
de commitear.

- [ ] **Step 8: Commit**

```bash
cd /c/Users/sistema/Documents/dev/mesadeayuda
git add src/Notification/Email/EmailBrand.php \
        tests/TestCase/Notification/Email/EmailBrandTest.php \
        tests/TestCase/Notification/Email/Component/EmailFrameTest.php
git commit -m "fix: servir el logo del correo como PNG desde el sitio publico

El servidor de la mesa de ayuda restringe por IP, asi que el proxy de
imagenes de Google no alcanzaba el logo. Ademas Gmail no renderiza SVG
en correos. La URL pasa a apuntar a un PNG alojado en copcsa.com."
```

---

### Task 4: Verificar el correo real en Gmail

Los tests confirman que el HTML apunta a la URL correcta; no pueden confirmar que Gmail la
renderice. Esta tarea la ejecuta la persona usuaria.

**Files:** ninguno.

**Interfaces:**
- Consumes: el despliegue del cambio de la Task 3.
- Produces: confirmación de que el problema original está resuelto.

- [ ] **Step 1: Desplegar el cambio al entorno donde corre el envío de correos**

El cambio es code-side, así que requiere deploy. Sin esto, la verificación no prueba nada.

- [ ] **Step 2: Disparar una notificación real**

Crear un ticket de prueba, que es lo que dispara `TicketCreated` → `TicketNotificationListener`
→ `EmailChannel`.

- [ ] **Step 3: Abrir el correo en Gmail y confirmar**

Revisar en Gmail web y en Gmail móvil:
- El logo se ve en el pie, junto a "Mesa de Ayuda" y el nombre de la organización.
- Se ve nítido, no pixelado (ahí se nota si el 4× funcionó).
- No aparece el ícono de imagen rota.

Si el logo sigue sin verse **y** Gmail muestra "Las imágenes no se muestran", es el bloqueo de
imágenes remotas del cliente, no este cambio: pulsar "Mostrar imágenes" y volver a comprobar.

- [ ] **Step 4: Comprobar un segundo cliente**

Abrir el mismo correo en Outlook web o en el móvil. Confirma que el PNG resolvió el problema de
formato de forma general, no solo en Gmail.

---

## Notas

- `src/Notification/Email/Component/EmailFrame.php` no se toca en ninguna tarea. Los atributos
  `width="24" height="24"` escalan la imagen al tamaño de display y el `alt=""` es correcto: el
  logo es decorativo porque el nombre de la marca va como texto justo al lado.
- `webroot/img/logo-mesa-ayuda.svg` se conserva. Sigue siendo la fuente del PNG, y su gemelo
  `webroot/img/logos/logo-mesa-ayuda.svg` (idéntico, 5572 bytes) lo consumen `templates/Users/login.php`,
  `templates/element/head.php` y `templates/cell/TicketsSidebar/display.php`. Consolidar ese
  duplicado está fuera del alcance de este plan.
