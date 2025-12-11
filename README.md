# 📹 Video Upload API - Secure REST API for Multi-Project Video Management

[![PHP Version](https://img.shields.io/badge/PHP-8.3.16-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Proprietary-red)](LICENSE)
[![OWASP](https://img.shields.io/badge/security-OWASP%20Top%2010-green)](https://owasp.org/www-project-top-ten/)

API segura para recepción y almacenamiento de videos desde aplicativos móviles, diseñada como módulo reutilizable para múltiples proyectos de la empresa.

## 📋 Tabla de Contenidos

- [Características](#características)
- [Arquitectura](#arquitectura)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Configuración](#configuración)
- [Uso de la API](#uso-de-la-api)
- [Estructura de Archivos](#estructura-de-archivos)
- [Seguridad](#seguridad)
- [Pruebas](#pruebas)
- [CI/CD](#cicd)
- [Contribución](#contribución)

## ✨ Características

- ✅ **PHP 8.3.16** con tipado estricto
- 🔒 **Seguridad OWASP Top 10** implementada
- 🗄️ **Almacenamiento organizado** por proyecto/año/mes/día/identificador
- 🔐 **Autenticación por API Key** con rate limiting
- 📊 **Auditoría completa** de operaciones
- 🧪 **Tests unitarios** con PHPUnit
- 🚀 **CI/CD** con GitLab DevSecOps pipeline
- 📝 **Logging detallado** con DayLog
- 🔄 **Arquitectura limpia** BLL/DAO/DTO
- 📦 **Migrations** para base de datos
- 🐳 **Docker** ready

## 🏗️ Arquitectura

```
┌─────────────────┐
│  Mobile App     │
└────────┬────────┘
         │ HTTPS + API Key
         ▼
┌─────────────────┐
│  API Gateway    │  ← Rate Limiting, Auth
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│   VideoBLL      │  ← Business Logic
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│   VideoDAO      │  ← Data Access (PDO)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│   MySQL DB      │  ← Videos metadata
└─────────────────┘
```

### Capas de la Aplicación

- **DTO** (Data Transfer Objects): Contratos de datos inmutables
- **BLL** (Business Logic Layer): Lógica de negocio y validaciones
- **DAO** (Data Access Object): Acceso a base de datos con prepared statements
- **Middleware**: Autenticación, autorización, rate limiting

## 📦 Requisitos

- PHP >= 8.3.16
- MySQL >= 8.0
- Composer 2.x
- Extensiones PHP:
  - PDO
  - pdo_mysql
  - fileinfo
  - json

## 🚀 Instalación

### 1. Clonar el repositorio

```bash
git clone <repository-url>
cd UPLOAD_VIDEOS_2
```

### 2. Instalar dependencias

```bash
composer install
```

### 3. Configurar variables de entorno

```bash
cp .env.example .env
# Editar .env con tus credenciales
```

### 4. Ejecutar migrations

```bash
mysql -u root -p < migrations/001_create_videos_table.sql
```

### 5. Crear directorios necesarios

```bash
mkdir -p uploads app/log
chmod 755 uploads app/log
```

## ⚙️ Configuración

### Archivo `.env`

```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=videos_db
DB_USER=root
DB_PASS=your_password

# Upload
UPLOAD_PATH=./uploads

# API Keys (genera con: openssl rand -hex 32)
VALID_API_KEYS=key1,key2,key3

# Environment
APP_ENV=production
APP_DEBUG=false
```

### Generar API Keys seguras

```bash
openssl rand -hex 32
```

## 📡 Uso de la API

### Base URL

```
https://your-domain.com/api
```

### Autenticación

Todas las peticiones requieren un API Key válido:

**Header:**
```
X-API-Key: your-api-key-here
```

O:

```
Authorization: Bearer your-api-key-here
```

### Endpoints

#### 1. Subir Video

**POST** `/api/videos/upload`

**Request:**
```bash
curl -X POST https://api-upload.simpledatacorp.com/v1/videos/upload \
  -H "X-API-Key: your-api-key" \
  -F "video=@/path/to/video.mp4" \
  -F "project_id=PROJECT_ABC" \
  -F "video_identifier=VIDEO_001" \
  -F 'metadata={"user_id":"123","device":"Android"}'
```

**Response (201):**
```json
{
  "status": {
    "code": 201,
    "description": "Video uploaded successfully"
  },
  "data": {
    "id": 1,
    "project_id": "PROJECT_ABC",
    "video_identifier": "VIDEO_001",
    "original_filename": "video.mp4",
    "file_path": "PROJECT_ABC/2025/12/10/VIDEO_001/video.mp4",
    "file_size": 15728640,
    "mime_type": "video/mp4",
    "status": "completed",
    "created_at": "2025-12-10 10:30:00"
  }
}
```

#### 2. Obtener Video por ID

**GET** `/api/videos/{id}`

**Response (200):**
```json
{
  "status": {
    "code": 200,
    "description": "Success"
  },
  "data": {
    "id": 1,
    "project_id": "PROJECT_ABC",
    "video_identifier": "VIDEO_001",
    "original_filename": "video.mp4",
    "file_path": "PROJECT_ABC/2025/12/10/VIDEO_001/video.mp4",
    "file_size": 15728640,
    "mime_type": "video/mp4"
  }
}
```

#### 3. Listar Videos por Proyecto

**GET** `/api/videos/project/{project_id}?page=1&per_page=50`

**Response (200):**
```json
{
  "status": {
    "code": 200,
    "description": "Success"
  },
  "data": {
    "videos": [...],
    "pagination": {
      "page": 1,
      "per_page": 50,
      "count": 10
    }
  }
}
```

#### 4. Eliminar Video (Soft Delete)

**DELETE** `/api/videos/{id}`

**Response (200):**
```json
{
  "status": {
    "code": 200,
    "description": "Video deleted successfully"
  },
  "data": null
}
```

#### 5. Health Check

**GET** `/api/health`

**Response (200):**
```json
{
  "status": {
    "code": 200,
    "description": "API is running"
  },
  "data": {
    "service": "Video Upload API",
    "version": "1.0.0",
    "database": "connected",
    "timestamp": "2025-12-10 10:30:00"
  }
}
```

### Códigos de Error

| Código | Descripción |
|--------|-------------|
| 400 | Bad Request - Datos inválidos |
| 401 | Unauthorized - API Key inválido o faltante |
| 404 | Not Found - Recurso no encontrado |
| 409 | Conflict - Video duplicado |
| 429 | Too Many Requests - Rate limit excedido |
| 500 | Internal Server Error - Error del servidor |

## 📁 Estructura de Archivos

```
/
├── app/
│   ├── BLL/
│   │   └── VideoBLL.php          # Lógica de negocio
│   ├── DAO/
│   │   └── VideoDAO.php          # Acceso a datos
│   ├── DTO/
│   │   ├── VideoUploadDTO.php    # DTO entrada
│   │   ├── VideoResponseDTO.php  # DTO salida
│   │   └── ApiResponseDTO.php    # DTO respuesta estándar
│   ├── Middleware/
│   │   └── ApiAuthMiddleware.php # Autenticación y rate limiting
│   └── log/                      # Logs de aplicación
├── migrations/
│   └── 001_create_videos_table.sql
├── tests/
│   ├── Unit/                     # Tests unitarios
│   └── uploads/                  # Archivos test
├── uploads/                      # Almacenamiento de videos
├── vendor/                       # Dependencias Composer
├── .env.example                  # Template variables entorno
├── .gitignore
├── .gitlab-ci.yml               # Pipeline CI/CD
├── api.php                      # Entry point API
├── composer.json
├── phpunit.xml                  # Configuración tests
└── README.md
```

### Estructura de Almacenamiento

Los videos se organizan automáticamente:

```
uploads/
└── {project_id}/
    └── {year}/
        └── {month}/
            └── {day}/
                └── {video_identifier}/
                    └── {filename}

Ejemplo:
uploads/PROJECT_ABC/2025/12/10/VIDEO_001/video.mp4
```

## 🔒 Seguridad (OWASP Top 10)

### 1. Control de Acceso (A01)
- ✅ Autenticación obligatoria con API Key
- ✅ Rate limiting (60 req/min por API key)
- ✅ Validación constante de permisos

### 2. Fallos Criptográficos (A02)
- ✅ Contraseñas nunca en texto plano
- ✅ Comunicación HTTPS obligatoria
- ✅ API Keys con hash seguro

### 3. Inyección (A03)
- ✅ Prepared statements (PDO) en todas las queries
- ✅ Validación estricta de entradas
- ✅ Sanitización de nombres de archivo

### 4. Configuración Incorrecta (A05)
- ✅ Variables de entorno para secretos
- ✅ Errores genéricos en producción
- ✅ Hardening de permisos de archivos

### 5. Validación de Entradas
- ✅ Validación de MIME types
- ✅ Validación de extensiones de archivo
- ✅ Límite de tamaño (500MB)
- ✅ Whitelist de tipos permitidos

### 6. Logging y Auditoría
- ✅ Trazabilidad completa (DayLog)
- ✅ Audit log de operaciones
- ✅ Transaction IDs únicos
- ✅ Sin datos sensibles en logs

## 🧪 Pruebas

### Ejecutar tests unitarios

```bash
./vendor/bin/phpunit
```

### Ejecutar con coverage

```bash
./vendor/bin/phpunit --coverage-html coverage
```

### Ejecutar tests específicos

```bash
./vendor/bin/phpunit tests/Unit/VideoUploadDTOTest.php
```

### Tests implementados

- ✅ VideoUploadDTO validation
- ✅ ApiResponseDTO formatting
- ✅ ApiAuthMiddleware authentication
- ✅ ApiAuthMiddleware rate limiting

## 🚀 CI/CD

El proyecto incluye un pipeline completo de DevSecOps en `.gitlab-ci.yml`:

### Stages

1. **Sanity** - Verificación del runner
2. **Dependencies** - Instalación de Composer
3. **Test** - PHPUnit con coverage
4. **SAST** - PHPStan + PHPCS (PSR-12)
5. **Security** - Gitleaks + Composer Audit
6. **Build** - Docker image
7. **Deploy** - Staging/Production

### Variables de CI/CD requeridas

```
CI_REGISTRY_USER
CI_REGISTRY_PASSWORD
SSH_PRIVATE_KEY
STAGING_SERVER
STAGING_USER
PRODUCTION_SERVER
PRODUCTION_USER
```

## 🐳 Docker

### Desarrollo con Docker

```bash
# Build
docker build -t video-upload-api .

# Run
docker run -p 8080:80 \
  -e DB_HOST=host.docker.internal \
  -e DB_NAME=videos_db \
  -e DB_USER=root \
  -e DB_PASS=password \
  video-upload-api
```

### Docker Compose

```bash
docker-compose up -d
```

## 📝 Logging

Todos los logs se almacenan en `app/log/` con el formato DayLog:

```
2025-12-10_api.log
```

Formato de log:
```
[2025-12-10 10:30:00] abc123 [video_bll] Video uploaded successfully: ID=1
```

## 🤝 Contribución

1. Crear branch feature: `git checkout -b feature/nueva-funcionalidad`
2. Commit: `git commit -m 'feat: agregar nueva funcionalidad'`
3. Push: `git push origin feature/nueva-funcionalidad`
4. Crear Merge Request

### Convenciones de Commits

- `feat:` Nueva funcionalidad
- `fix:` Corrección de bug
- `refactor:` Refactorización
- `test:` Agregar tests
- `docs:` Documentación
- `chore:` Tareas de mantenimiento

## 📞 Soporte

Para soporte técnico o preguntas, contactar al equipo de desarrollo en SimpleData Corp.

## 📄 Licencia

Código propietario © 2025 SimpleData Corp. Todos los derechos reservados.

---

**Desarrollado con ❤️ por SimpleData Corp**

**Versión:** 1.0.0  
**Última actualización:** Diciembre 2025
