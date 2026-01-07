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

### 1. Configurar Variables de Entorno en Easypanel

```env
APP_ENV=production
DEBUG=false
DB_HOST=tu-servidor-mysql
DB_PORT=3306
DB_DATABASE=mesadeayuda
DB_USERNAME=usuario
DB_PASSWORD=contraseña
SECURITY_SALT=tu-salt-aleatorio
```

### 2. Deploy desde GitHub

Easypanel detectará el `Dockerfile` en la raíz automáticamente.

### 3. Ejecutar Migraciones

Una vez desplegado, accede a la **Terminal/Console** en Easypanel y ejecuta:

```bash
php bin/cake.php migrations migrate
```

### 4. Verificar que la App Funciona

Accede a la URL de tu app y verifica que carga correctamente.

### 5. Configurar Gmail OAuth

1. Accede al Admin Panel: `/admin/settings`
2. Configura las credenciales de Gmail OAuth
3. Autoriza la cuenta de Gmail

### 6. Iniciar el Worker (Después de configurar Gmail)

En la **Terminal/Console** de Easypanel:

```bash
# Iniciar worker
start-worker

# O manualmente con supervisorctl
supervisorctl start gmail-worker

# Ver estado
supervisorctl status

# Ver logs del worker
tail -f /var/www/html/logs/worker.log
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

### Nginx no inicia

```bash
# Ver configuración
nginx -t

# Ver logs
cat /var/www/html/logs/nginx-error.log
```

### Worker no funciona

```bash
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

- [ ] Migraciones ejecutadas correctamente
- [ ] La aplicación carga en el navegador
- [ ] Login funciona
- [ ] Gmail OAuth configurado
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
