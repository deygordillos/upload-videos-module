# Deployment Guide

## Requisitos del Sistema

### Mínimos
- **OS**: Linux/Windows/macOS
- **Docker**: 20.10+
- **Docker Compose**: 2.0+
- **RAM**: 512 MB
- **Storage**: 10 GB (+ espacio para videos)

### Recomendados
- **RAM**: 2 GB
- **Storage**: 100 GB SSD
- **CPU**: 2+ cores
- **MySQL**: 8.0+ (separado del contenedor)

## Instalación con Docker

### 1. Clonar Repositorio

```bash
git clone <repository-url>
cd UPLOAD_VIDEOS_2
```

### 2. Configurar Variables de Entorno

Copiar y editar archivo de configuración:

```bash
cp .env.example .env
```

Editar `.env`:

```env
# Aplicación
APP_ENV=production
APP_DEBUG=false
APP_PATH=/

# Base de Datos
BDD_HOST=your-db-host
BDD_USER=your-db-user
BDD_PASS=your-db-password
BDD_PORT=3306
BDD_SCHEMA=sdc_videos

# Seguridad - IMPORTANTE: Generar keys únicas
VALID_API_KEYS=prod-key-$(openssl rand -hex 16),backup-key-$(openssl rand -hex 16)

# Upload
UPLOAD_PATH=./uploads

# Logs
LOG_LEVEL=warning
LOG_PATH=./app/log
```

### 3. Crear Base de Datos

Ejecutar migración SQL:

```bash
# Conectar a MySQL
mysql -h your-db-host -u your-db-user -p

# Crear schema
CREATE DATABASE IF NOT EXISTS sdc_videos 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

# Usar schema
USE sdc_videos;

# Ejecutar migración
source migrations/001_create_videos_table.sql;
```

O con un solo comando:

```bash
mysql -h your-db-host -u your-db-user -p sdc_videos < migrations/001_create_videos_table.sql
```

### 4. Build de Imagen Docker

```bash
docker-compose build
```

### 5. Iniciar Contenedor

```bash
docker-compose up -d
```

### 6. Verificar Estado

```bash
# Ver logs
docker-compose logs -f

# Verificar contenedor
docker-compose ps

# Health check
curl http://localhost:8270/v1/videos/health
```

## Instalación Manual (Sin Docker)

### 1. Requisitos

- **PHP**: 8.3.16+
- **Apache**: 2.4+
- **MySQL**: 8.0+
- **Composer**: 2.x
- **Extensiones PHP**:
  - mysqli
  - pdo_mysql
  - fileinfo
  - json
  - mbstring

### 2. Instalar Dependencias

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Configurar Apache

**VirtualHost** (`/etc/apache2/sites-available/videos-api.conf`):

```apache
<VirtualHost *:80>
    ServerName videos-api.local
    DocumentRoot /var/www/videos-api/
    DirectoryIndex core.php

    <Directory /var/www/videos-api/>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # Rewrite para Slim Framework
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ core.php [QSA,L]
    </Directory>

    # Directorio de uploads
    <Directory /var/www/videos-api/uploads/>
        Options -Indexes
        Require all denied
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/videos-api-error.log
    CustomLog ${APACHE_LOG_DIR}/videos-api-access.log combined
</VirtualHost>
```

Habilitar sitio:

```bash
sudo a2ensite videos-api
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 4. Permisos

```bash
# Crear directorios
mkdir -p uploads app/log

# Establecer permisos
chown -R www-data:www-data uploads app/log
chmod -R 775 uploads app/log
```

### 5. Verificar Instalación

```bash
php -v
php -m | grep -E 'pdo|mysqli|fileinfo'
```

## Configuración de Producción

### 1. PHP Settings

Editar `php.ini` o crear `/usr/local/etc/php/conf.d/custom.ini`:

```ini
; Upload limits
upload_max_filesize = 100M
post_max_size = 100M
memory_limit = 256M
max_execution_time = 300

; Error handling
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED
log_errors = On
error_log = /var/log/php/error.log

; Security
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off

; Performance
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 60
```

### 2. Variables de Entorno Seguras

**NO** commitear `.env` a git. Usar secretos del sistema:

```bash
# Docker secrets
docker secret create api_keys /path/to/api_keys.txt

# Kubernetes secrets
kubectl create secret generic video-api-secrets \
  --from-literal=db-password='...' \
  --from-literal=api-keys='...'
```

### 3. HTTPS/SSL

#### Con Let's Encrypt (Certbot)

```bash
# Instalar certbot
sudo apt-get install certbot python3-certbot-apache

# Obtener certificado
sudo certbot --apache -d videos-api.yourdomain.com

# Auto-renovación
sudo certbot renew --dry-run
```

#### Con Certificado Propio

```apache
<VirtualHost *:443>
    ServerName videos-api.yourdomain.com
    
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    SSLCertificateChainFile /path/to/chain.pem
    
    # Resto de configuración...
</VirtualHost>
```

### 4. Firewall

```bash
# UFW (Ubuntu)
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# iptables
sudo iptables -A INPUT -p tcp --dport 80 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 443 -j ACCEPT
```

### 5. Rate Limiting (Nginx)

Si usas Nginx como reverse proxy:

```nginx
http {
    limit_req_zone $binary_remote_addr zone=api_limit:10m rate=60r/m;
    
    server {
        location /v1/videos/ {
            limit_req zone=api_limit burst=5 nodelay;
            proxy_pass http://backend:8270;
        }
    }
}
```

## Monitoreo

### 1. Health Check

Configurar monitoreo automático:

```bash
# Cron job (cada 5 minutos)
*/5 * * * * curl -f http://localhost:8270/v1/videos/health || echo "API DOWN" | mail -s "Alert" admin@company.com
```

### 2. Logs

Configurar rotación de logs:

**logrotate** (`/etc/logrotate.d/videos-api`):

```
/var/www/videos-api/app/log/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 0640 www-data www-data
}
```

### 3. Métricas

Instalar Prometheus exporter (opcional):

```bash
docker run -d \
  --name php-fpm-exporter \
  -p 9253:9253 \
  hipages/php-fpm_exporter:latest \
  --phpfpm.scrape-uri tcp://php-fpm:9000/status
```

## Backup

### 1. Base de Datos

```bash
# Backup diario
mysqldump -h localhost -u backup_user -p sdc_videos | gzip > backup_$(date +%Y%m%d).sql.gz

# Cron
0 2 * * * /usr/local/bin/backup-db.sh
```

### 2. Archivos de Video

```bash
# Rsync a servidor remoto
rsync -avz --delete /var/www/videos-api/uploads/ backup-server:/backups/videos/

# Cron (cada 6 horas)
0 */6 * * * /usr/local/bin/backup-videos.sh
```

### 3. Restauración

```bash
# Restaurar BD
gunzip < backup_20251211.sql.gz | mysql -h localhost -u root -p sdc_videos

# Restaurar archivos
rsync -avz backup-server:/backups/videos/ /var/www/videos-api/uploads/
```

## Escalamiento

### 1. Horizontal (Múltiples Instancias)

#### Load Balancer (Nginx)

```nginx
upstream video_api {
    server api1.local:8270 weight=1;
    server api2.local:8270 weight=1;
    server api3.local:8270 weight=1;
}

server {
    listen 80;
    server_name videos-api.company.com;
    
    location /v1/videos/ {
        proxy_pass http://video_api;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

#### Shared Storage (NFS)

```bash
# Servidor NFS
sudo apt-get install nfs-kernel-server
echo "/exports/videos *(rw,sync,no_subtree_check)" >> /etc/exports
sudo exportfs -ra

# Clientes
sudo mount -t nfs nfs-server:/exports/videos /var/www/videos-api/uploads
```

### 2. Vertical (Recursos)

```yaml
# docker-compose.yml
services:
  api:
    image: video-api:latest
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 2G
        reservations:
          cpus: '1.0'
          memory: 512M
```

### 3. Base de Datos

- **Replicación**: Master-Slave para reads
- **Sharding**: Por project_id
- **Connection Pooling**: PgBouncer/ProxySQL

## Troubleshooting

### API no responde

```bash
# Verificar contenedor
docker-compose ps
docker-compose logs --tail=100

# Verificar puertos
netstat -tulpn | grep 8270

# Verificar PHP
docker exec -it sdc_upload_videos_api php -v
```

### Errores de upload

```bash
# Verificar permisos
ls -la uploads/
docker exec -it sdc_upload_videos_api ls -la /api/uploads/

# Verificar límites PHP
docker exec -it sdc_upload_videos_api php -i | grep upload_max_filesize
```

### Base de datos no conecta

```bash
# Test de conexión
docker exec -it sdc_upload_videos_api php -r "
  \$pdo = new PDO('mysql:host=host.docker.internal;dbname=sdc_videos', 'user', 'pass');
  echo 'Connected OK';
"

# Ver logs de BD
docker-compose logs mysql
```

### Memoria insuficiente

```bash
# Aumentar límites PHP
docker exec -it sdc_upload_videos_api bash -c "echo 'memory_limit = 512M' >> /usr/local/etc/php/conf.d/custom.ini"
docker-compose restart
```

## Seguridad

### 1. Checklist Pre-Producción

- [ ] `.env` con valores de producción
- [ ] `APP_DEBUG=false`
- [ ] API keys únicas y complejas
- [ ] HTTPS habilitado
- [ ] Firewall configurado
- [ ] Permisos de archivos restrictivos (755/644)
- [ ] Directorio uploads inaccesible vía web
- [ ] Logs rotando automáticamente
- [ ] Backups automatizados
- [ ] Health checks activos

### 2. Hardening

```bash
# Deshabilitar funciones peligrosas en php.ini
disable_functions = exec,passthru,shell_exec,system,proc_open,popen

# Ocultar versión de Apache
ServerTokens Prod
ServerSignature Off

# Headers de seguridad
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "DENY"
Header always set X-XSS-Protection "1; mode=block"
```

### 3. Auditoría

```bash
# Revisar logs de acceso sospechosos
grep "429\|401" /var/log/apache2/videos-api-access.log

# Verificar integridad de archivos
find /var/www/videos-api -type f -mtime -1

# Audit log de BD
mysql -e "SELECT action, COUNT(*) FROM video_audit_log GROUP BY action;"
```

## Actualizaciones

### 1. Sin Downtime (Blue-Green Deployment)

```bash
# Levantar nueva versión en puerto diferente
docker-compose -f docker-compose.blue.yml up -d

# Verificar salud
curl http://localhost:8271/v1/videos/health

# Cambiar load balancer
# Update Nginx upstream

# Apagar versión anterior
docker-compose -f docker-compose.green.yml down
```

### 2. Con Downtime Mínimo

```bash
# 1. Modo mantenimiento
echo "503 Service Unavailable" > maintenance.html

# 2. Pull nueva versión
git pull origin main

# 3. Update dependencias
composer install --no-dev --optimize-autoloader

# 4. Migraciones BD
mysql -u user -p sdc_videos < migrations/002_new_migration.sql

# 5. Restart
docker-compose restart

# 6. Verificar
curl http://localhost:8270/v1/videos/health

# 7. Quitar mantenimiento
rm maintenance.html
```

## Anexos

### Variables de Entorno Completas

```env
# Application
APP_ENV=production|development
APP_DEBUG=true|false
APP_PATH=/

# Database
BDD_HOST=host.docker.internal
BDD_USER=sdc
BDD_PASS=secure_password
BDD_PORT=3306
BDD_SCHEMA=sdc_videos

# Security
VALID_API_KEYS=key1,key2,key3

# Upload
UPLOAD_PATH=./uploads

# Logging
LOG_LEVEL=debug|info|warning|error
LOG_PATH=./app/log
```

### Puertos Usados

| Puerto | Servicio | Descripción |
|--------|----------|-------------|
| 8270 | API HTTP | Puerto principal de la API |
| 3306 | MySQL | Base de datos (si está en Docker) |
| 80/443 | Web | Con reverse proxy |

### Comandos Útiles

```bash
# Ver logs en tiempo real
docker-compose logs -f --tail=100

# Reiniciar sin downtime
docker-compose restart

# Ejecutar comando en contenedor
docker exec -it sdc_upload_videos_api bash

# Backup rápido
docker exec mysql mysqldump sdc_videos > backup.sql

# Ver uso de recursos
docker stats sdc_upload_videos_api

# Limpiar volumes antiguos
docker volume prune
```

---

**Última actualización**: Diciembre 2025  
**Versión**: 1.0
