# Logo de correo: dependencia del WordPress público

El pie de todas las notificaciones por correo (`EmailFrame`) carga el logo desde
un archivo que vive fuera de este repositorio, en el WordPress público de la
organización. Ese archivo es **load-bearing**: si desaparece o cambia de ruta,
el fallo es silencioso.

## Qué es y dónde vive

- **URL exacta:** `https://www.copcsa.com/wp-content/uploads/2026/07/logo-mesa-ayuda.png`
- Es un adjunto de la biblioteca de medios de WordPress **no asociado a ningún
  post**. Ese perfil ("medio sin usar") es justo el que borran las limpiezas
  automáticas de la biblioteca de medios, y el que rompen los plugins de
  offload/CDN al reescribir rutas de `uploads/`.
- Quien administra ese WordPress no lee la documentación de este repositorio,
  así que un cambio en esa URL no llega a este equipo antes de romper algo.

## Qué código depende de él

- `src/Notification/Email/EmailBrand.php:28` — `EmailBrand::logoUrl()` devuelve
  la URL de forma fija (no hay lookup en `system_settings`).
- Consumido por `src/Notification/Email/Component/EmailFrame.php`, que renderiza
  el `<img>` del pie de correo.

## Qué pasa si se borra o cambia de ruta

Los correos salientes siguen enviándose con normalidad, pero el `<img>` del pie
queda roto. **Ningún test ni healthcheck lo detecta** — la implementación de
`EmailBrand` no verifica que la URL siga viva. La primera señal sería alguien
reportando el logo roto, potencialmente meses después.

## El asset está versionado en el repo

La fuente de lo que se subió a WordPress está en `webroot/img/logo-mesa-ayuda.png`
(96×96, generado desde `webroot/img/logo-mesa-ayuda.svg`). Si el archivo de
WordPress se pierde, se puede volver a subir tal cual desde el repo — no hace
falta regenerarlo.

`webroot/img/logo-mesa-ayuda.svg` ya no lo referencia ningún camino de código
(las vistas usan el gemelo en `webroot/img/logos/logo-mesa-ayuda.svg`). Se
conserva a propósito como fuente del PNG; no es dead code para limpiar.

## Si hay que cambiar la URL

1. Subir el nuevo archivo a WordPress y confirmar la URL resultante (`curl -I`).
2. Editar `EmailBrand::logoUrl()` con la nueva URL.
3. Actualizar `tests/TestCase/Notification/Email/EmailBrandTest.php` con el
   mismo valor.
4. Desplegar.

## Recomendación operativa

Adjuntar el medio a alguna página del WordPress (por ejemplo, la página de
contacto o "quiénes somos") en vez de dejarlo huérfano. Esto lo saca del perfil
que targetea la limpieza automática de medios sin usar y de los plugins de
offload que reescriben rutas de adjuntos huérfanos.
