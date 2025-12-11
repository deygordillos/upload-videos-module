# 🚀 Guía de Inicio Rápido - Video Upload API

## ⚡ Inicio Rápido en 5 Minutos

### Paso 1: Instalar Dependencias

```bash
# Instalar dependencias de Composer
composer install
```

### Paso 2: Configurar Variables de Entorno

```bash
# Copiar el archivo de ejemplo
cp .env.example .env

# Editar el archivo .env (usar tu editor favorito)
# Configurar las credenciales de la base de datos y generar API Keys
```

**Generar API Keys seguros:**
```bash
# Linux/Mac
openssl rand -hex 32

# Windows PowerShell
$bytes = New-Object byte[] 32
[System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
[System.BitConverter]::ToString($bytes).Replace("-", "").ToLower()
```

### Paso 3: Configurar Base de Datos

```bash
# Opción A: Con Docker (Recomendado)
docker-compose up -d db
# La migración se ejecuta automáticamente al iniciar el contenedor

# Opción B: Manual
mysql -u root -p < migrations/001_create_videos_table.sql
```

### Paso 4: Iniciar la Aplicación

```bash
# Opción A: Con Docker (Recomendado)
docker-compose up -d

# Verificar que los servicios están corriendo
docker-compose ps

# Ver logs
docker-compose logs -f app

# Opción B: Servidor PHP integrado (Solo desarrollo)
php -S localhost:8080 api.php
```

### Paso 5: Verificar Instalación

```bash
# Health check
curl http://localhost:8270/api/health

# Debería responder con:
# {
#   "status": {
#     "code": 200,
#     "message": "API is running"
#   },
#   "data": {
#     "service": "Video Upload API",
#     "version": "1.0.0",
#     "database": "connected",
#     "timestamp": "2025-12-10 10:30:00"
#   }
# }
```

---

## 📤 Prueba de Carga de Video

### Usando cURL

```bash
# Subir un video
curl -X POST http://localhost:8270/api/videos/upload \
  -H "X-API-Key: dev-api-key-123" \
  -F "video=@/ruta/al/video.mp4" \
  -F "project_id=PROJECT_TEST" \
  -F "video_identifier=VIDEO_001" \
  -F 'metadata={"user":"test","device":"curl"}'
```

### Usando Postman

1. **Método**: POST
2. **URL**: `http://localhost:8270/api/videos/upload`
3. **Headers**:
   - `X-API-Key`: `dev-api-key-123`
4. **Body** (form-data):
   - `video`: (archivo video.mp4)
   - `project_id`: `PROJECT_TEST`
   - `video_identifier`: `VIDEO_001`
   - `metadata`: `{"user":"test"}`

---

## 🧪 Ejecutar Tests

```bash
# Tests unitarios
composer test

# Tests con coverage
composer test:coverage

# Ver reporte de coverage
# Abrir: coverage/index.html en el navegador
```

---

## 🐛 Solución de Problemas Comunes

### Error: "Use of unknown class: 'Libraries\DatabaseConnection'"

**Solución**: Instalar dependencias
```bash
composer install
```

### Error: "Connection refused" al conectar a la base de datos

**Solución**: Verificar que MySQL está corriendo
```bash
# Con Docker
docker-compose ps

# Reiniciar servicios
docker-compose restart db
```

### Error: "API key is required"

**Solución**: Agregar el header de autenticación
```bash
# Asegúrate de incluir el header X-API-Key
-H "X-API-Key: tu-api-key-aqui"
```

### Error: "Failed to create directory structure"

**Solución**: Verificar permisos
```bash
# Linux/Mac
mkdir -p uploads app/log
chmod 755 uploads app/log

# Windows
mkdir uploads, app\log
```

### Error al subir archivos grandes

**Solución**: Ajustar configuración de PHP
```ini
# En php.ini
upload_max_filesize = 500M
post_max_size = 500M
max_execution_time = 300
```

---

## 🔧 Comandos Útiles

### Docker

```bash
# Iniciar todos los servicios
docker-compose up -d

# Detener servicios
docker-compose down

# Ver logs en tiempo real
docker-compose logs -f app

# Reiniciar un servicio específico
docker-compose restart app

# Acceder al contenedor
docker-compose exec app bash

# Ver estado de servicios
docker-compose ps
```

### Composer

```bash
# Instalar dependencias
composer install

# Actualizar dependencias
composer update

# Tests
composer test

# Lint (verificar código)
composer lint

# Análisis estático
composer analyse
```

### Base de Datos

```bash
# Acceder a MySQL desde Docker
docker-compose exec db mysql -u root -p

# Importar migración
docker-compose exec db mysql -u root -p videos_db < /docker-entrypoint-initdb.d/001_create_videos_table.sql

# Backup de base de datos
docker-compose exec db mysqldump -u root -p videos_db > backup.sql
```

---

## 📁 Estructura de Archivos Generados

Al subir un video, se organiza automáticamente:

```
uploads/
└── PROJECT_TEST/
    └── 2025/
        └── 12/
            └── 10/
                └── VIDEO_001/
                    └── video.mp4
```

**Patrón**: `{project_id}/{año}/{mes}/{día}/{video_identifier}/{filename}`

---

## 🌐 Acceder a los Servicios

| Servicio | URL | Credenciales |
|----------|-----|--------------|
| API | http://localhost:8270 | API Key configurado |
| phpMyAdmin | http://localhost:8271 | root / root_password |
| MySQL | localhost:3307 | root / root_password |

---

## 📊 Endpoints Disponibles

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/videos/upload` | Subir video |
| GET | `/api/videos/{id}` | Obtener video por ID |
| GET | `/api/videos/project/{id}` | Listar videos por proyecto |
| DELETE | `/api/videos/{id}` | Eliminar video |
| GET | `/api/health` | Health check |

---

## 🎯 Próximos Pasos

1. ✅ Configurar variables de entorno en `.env`
2. ✅ Ejecutar `composer install`
3. ✅ Iniciar servicios con `docker-compose up -d`
4. ✅ Probar health check
5. ✅ Subir un video de prueba
6. ✅ Ejecutar tests con `composer test`
7. ✅ Revisar documentación completa en `README.md`

---

## 📞 Soporte

- **Documentación completa**: Ver `README.md`
- **Guía para desarrolladores**: Ver `AGENTS.md`
- **Resumen de implementación**: Ver `IMPLEMENTATION_SUMMARY.md`
- **Estándares de código**: ClickUp Docs

---

**¡Tu API está lista para usar! 🎉**
