# Despliegue en Easypanel

Guía rápida para desplegar Mesa de Ayuda en Easypanel.

## 🚀 Cambios Importantes

### Nginx
- ✅ Configurado para contenedor todo-en-uno
- ✅ PHP-FPM en `localhost:9000`
- ✅ Logs en `/var/www/html/logs/`

### Gmail Worker
- ⚠️ **Desactivado por defecto** (autostart=false)
- Se inicia manualmente después de configurar Gmail OAuth

## 📋 Pasos de Despliegue

### 1. Configurar en Easypanel

**En General Settings:**
- **Port**: `80` (importante!)
- **Dockerfile Path**: `./Dockerfile`

**En Domains:**
- ⚠️ **IMPORTANTE**: Configura un dominio con HTTPS habilitado
- Gmail OAuth requiere HTTPS para funcionar
- Easypanel proporciona certificados SSL automáticamente con Let's Encrypt

**En Environment Variables:**
```env
APP_ENV=production
DEBUG=false

# Database
DB_HOST=tu-servidor-mysql
DB_PORT=3306
DB_DATABASE=mesadeayuda
DB_USERNAME=usuario
DB_PASSWORD=contraseña

# Security
SECURITY_SALT=tu-salt-aleatorio

# HTTPS Configuration (requerido para Gmail OAuth)
TRUST_PROXY=true
# Opcional: FULL_BASE_URL=https://tudominio.com
```

### 2. Deploy desde GitHub

Easypanel detectará el `Dockerfile` en la raíz automáticamente y:
- Construirá la imagen
- Iniciará PHP-FPM y Nginx
- Ejecutará health check cada 30s en `/health`

**⚠️ Importante:** El health check pasará incluso sin migraciones. Esto es intencional para permitir el despliegue inicial.

### 3. Verificar que el Contenedor Está Corriendo

En los logs deberías ver:
```
INFO success: php-fpm entered RUNNING state
INFO success: nginx entered RUNNING state
```

Si ves `SIGQUIT` o el contenedor se reinicia constantemente:
- Verifica que el puerto 80 esté configurado en Easypanel
- Verifica los logs de nginx: `cat /var/www/html/logs/nginx-error.log`

### 4. La Aplicación se Conectará a la BD Automáticamente

El contenedor ya está configurado para usar las variables de entorno de Easypanel:
- `config/app_local.php` se genera automáticamente desde `config/app_local.example.php`
- Lee `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE`

### 5. Ejecutar Migraciones (CRÍTICO)

Una vez desplegado, accede a la **Terminal/Console** en Easypanel y ejecuta:

```bash
php bin/cake.php migrations migrate
```

Esto creará todas las tablas y datos iniciales.

### 4. Verificar que la App Funciona

Accede a la URL de tu app y verifica que carga correctamente.

### 5. Configurar Gmail OAuth

**IMPORTANTE**: Debes tener HTTPS configurado antes de continuar.

#### 5.1. Configurar Google Cloud Console

1. Ve a [Google Cloud Console](https://console.cloud.google.com)
2. Crea o selecciona tu proyecto
3. Ve a **APIs & Services** → **Credentials**
4. Crea credenciales OAuth 2.0 Client ID (tipo "Web application")
5. En **Authorized redirect URIs**, agrega:
   ```
   https://tudominio.com/admin/settings/gmail-auth
   ```
   ⚠️ **Debe ser HTTPS** - Google rechazará URLs HTTP

#### 5.2. Subir client_secret.json

1. Descarga el archivo `client_secret.json` de Google Cloud Console
2. Ve a `/admin/settings` en tu aplicación
3. En la sección **"Archivo de Configuración de Gmail"**:
   - Haz clic en **"Seleccionar archivo"**
   - Sube el archivo `client_secret.json`
   - Haz clic en **"Subir Archivo"**

#### 5.3. Autorizar Gmail

1. En `/admin/settings`, sección **"Configuración de Gmail"**
2. Haz clic en **"Autorizar Gmail"**
3. Completa el flujo OAuth de Google
4. Una vez autorizado, verás el estado como "Conectado"

### 6. Iniciar el Worker (Después de configurar Gmail)

En la **Terminal/Console** de Easypanel:

```bash
# Verificar que supervisor está corriendo
supervisorctl status

# Deberías ver:
# php-fpm                          RUNNING
# nginx                            RUNNING
# gmail-worker                     STOPPED

# Iniciar worker
start-worker

# O manualmente con supervisorctl
supervisorctl start gmail-worker

# Verificar que está corriendo
supervisorctl status gmail-worker

# Ver logs del worker
tail -f /var/www/html/logs/worker.log
```

**Nota**: Si ves el error `unix:///var/run/supervisor.sock no such file`, significa que Supervisor no está corriendo. Esto puede suceder si:
- El contenedor se acaba de iniciar y Supervisor aún no ha creado el socket
- Hay un problema con la configuración de Supervisor

**Solución**: Espera unos segundos y vuelve a intentar. Si persiste, verifica los logs:
```bash
cat /var/www/html/logs/supervisord.log
```

## 🔍 Verificar Estado de Servicios

```bash
# Ver todos los servicios
supervisorctl status

# Deberías ver:
# php-fpm                 RUNNING
# nginx                   RUNNING
# gmail-worker            STOPPED (hasta que lo inicies manualmente)
```

## 📊 Ver Logs

```bash
# Logs de Nginx
tail -f /var/www/html/logs/nginx-error.log
tail -f /var/www/html/logs/nginx-access.log

# Logs de PHP-FPM
tail -f /var/www/html/logs/php-fpm-error.log

# Logs del Worker
tail -f /var/www/html/logs/worker.log
tail -f /var/www/html/logs/worker-error.log

# Logs de Supervisor
tail -f /var/www/html/logs/supervisord.log
```

## 🛠️ Troubleshooting

### Google OAuth no acepta mi URL (error "redirect_uri_mismatch")

**Causa**: La aplicación está generando URLs HTTP en lugar de HTTPS.

**Solución**:

1. **Verifica que HTTPS esté habilitado en Easypanel**:
   - Ve a tu aplicación en Easypanel
   - En la sección **Domains**, asegúrate de tener un dominio configurado
   - Verifica que el certificado SSL esté activo (🔒 verde)

2. **Asegúrate de que `TRUST_PROXY=true` esté en Environment Variables**:
   ```bash
   TRUST_PROXY=true
   ```

3. **Opcionalmente, fuerza la URL base con HTTPS**:
   ```bash
   FULL_BASE_URL=https://tudominio.com
   ```

4. **Verifica que la URL de redirección sea correcta**:
   - En Google Cloud Console debe ser: `https://tudominio.com/admin/settings/gmail-auth`
   - En tu aplicación, ve a `/admin/settings` y verifica que los enlaces sean HTTPS

5. **Redespliega** después de cambiar las variables de entorno

### La aplicación genera URLs HTTP en lugar de HTTPS

**Síntoma**: Los enlaces en la aplicación apuntan a `http://` en lugar de `https://`

**Causa**: CakePHP no está detectando que está detrás de un proxy HTTPS.

**Solución**:
1. Agrega `TRUST_PROXY=true` a las Environment Variables en Easypanel
2. Verifica que Easypanel esté enviando el header `X-Forwarded-Proto: https`
3. Redespliega la aplicación

### Nginx no inicia

```bash
# Ver configuración
nginx -t

# Ver logs
cat /var/www/html/logs/nginx-error.log
```

### Error "unix:///var/run/supervisor.sock no such file"

**Causa**: Supervisor no está corriendo o el socket no se ha creado.

**Diagnóstico**:
```bash
# Verificar que supervisor está corriendo
ps aux | grep supervisord

# Ver logs de supervisor
cat /var/www/html/logs/supervisord.log

# Verificar si el socket existe
ls -la /var/run/supervisor.sock
```

**Solución**:

1. **Si el contenedor se acaba de iniciar**: Espera 10-20 segundos para que Supervisor se inicialice completamente.

2. **Si Supervisor no está corriendo**: El contenedor debe reiniciarse. En Easypanel, haz clic en "Restart" en la aplicación.

3. **Si persiste después de reiniciar**: Revisa los logs del contenedor para ver errores de inicio:
   ```bash
   cat /var/www/html/logs/supervisord.log
   ```

### Worker no funciona

```bash
# Verificar que el worker está corriendo
supervisorctl status gmail-worker

# Si está STOPPED, iniciarlo
supervisorctl start gmail-worker

# Verificar configuración de Gmail
php bin/cake.php import_gmail

# Ver logs específicos
tail -f /var/www/html/logs/worker-error.log
```

### Error de permisos

```bash
# Arreglar permisos
chown -R www-data:www-data /var/www/html/logs /var/www/html/tmp /var/www/html/webroot/uploads
chmod -R 775 /var/www/html/logs /var/www/html/tmp /var/www/html/webroot/uploads
```

### Reiniciar servicios

```bash
# Reiniciar Nginx
supervisorctl restart nginx

# Reiniciar PHP-FPM
supervisorctl restart php-fpm

# Reiniciar Worker
supervisorctl restart gmail-worker

# Reiniciar todo
supervisorctl restart all
```

## ✅ Checklist Post-Despliegue

- [ ] Dominio configurado en Easypanel con HTTPS habilitado (🔒)
- [ ] Variable `TRUST_PROXY=true` configurada
- [ ] Migraciones ejecutadas correctamente
- [ ] La aplicación carga en el navegador con HTTPS
- [ ] Login funciona
- [ ] Los enlaces internos usan HTTPS (no HTTP)
- [ ] `client_secret.json` subido vía panel de administración
- [ ] Gmail OAuth configurado y autorizado
- [ ] Worker iniciado manualmente
- [ ] Emails se importan correctamente
- [ ] Uploads funcionan
- [ ] WhatsApp y n8n configurados (si aplica)

## 🔄 Actualizar la Aplicación

Cada vez que hagas cambios en GitHub:

1. Easypanel detectará el cambio
2. Reconstruirá la imagen automáticamente
3. Reiniciará el contenedor

**Nota:** El worker se detendrá en cada despliegue. Debes reiniciarlo manualmente:

```bash
supervisorctl start gmail-worker
```

## 📝 Notas Importantes

1. **El worker NO se inicia automáticamente** - Esto evita errores en el despliegue inicial antes de configurar Gmail.

2. **Los logs están en `/var/www/html/logs/`** - No en `/var/log/` como en configuraciones tradicionales.

3. **Nginx escucha en puerto 80** - Easypanel maneja el routing y SSL.

4. **Base de datos externa** - Asegúrate de que sea accesible desde Easypanel.

## 🆘 Soporte

Si encuentras problemas:
1. Revisa los logs (ver sección "Ver Logs")
2. Verifica variables de entorno en Easypanel
3. Asegúrate de que las migraciones se ejecutaron
4. Verifica conectividad a la base de datos
