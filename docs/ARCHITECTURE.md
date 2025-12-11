# Arquitectura del Proyecto

## Visión General

Este proyecto implementa una API RESTful para gestión de videos siguiendo el patrón de arquitectura empresarial de SimpleData Corp, con separación clara de responsabilidades en capas BLL (Business Logic Layer) y DAO (Data Access Object).

## Stack Tecnológico

- **PHP**: 8.3.16
- **Framework**: Slim 4.15.1
- **Base de Datos**: MySQL 8.0+
- **Servidor Web**: Apache 2.4.62
- **Containerización**: Docker + Docker Compose
- **Testing**: PHPUnit 10.5.60
- **Análisis Estático**: PHPStan Level 5
- **Gestión de Dependencias**: Composer 2.x

## Estructura de Directorios

```
UPLOAD_VIDEOS_2/
├── app/
│   ├── BaseClass.php              # Clase base para BLL
│   ├── BaseComponent.php          # Clase base común para BLL y DAO
│   ├── BLL/                       # Business Logic Layer
│   │   └── VideoBLL.php          # Lógica de negocio de videos
│   ├── DAO/                       # Data Access Object
│   │   ├── BaseDAO.php           # Métodos base para acceso a datos
│   │   └── VideoDAO.php          # Acceso a datos de videos
│   ├── DTO/                       # Data Transfer Objects
│   │   ├── ApiResponseDTO.php
│   │   ├── VideoResponseDTO.php
│   │   └── VideoUploadDTO.php
│   ├── Routes/                    # Definición de rutas
│   │   └── VideoRoutes.php
│   ├── Middleware/                # Middlewares
│   │   ├── VideoAuthMiddleware.php
│   │   ├── AuthMiddleware.php (legacy)
│   │   └── ErrorHandlerMiddleware.php (legacy)
│   ├── Handlers/                  # Manejadores de errores
│   │   ├── HttpErrorHandler.php
│   │   └── ShutdownHandler.php
│   ├── Utils/                     # Utilidades compartidas
│   │   └── DatabaseConnection.php (deprecated)
│   └── log/                       # Logs de aplicación
├── libraries/                     # Bibliotecas empresariales
│   ├── DBConnectorPDO.php        # Conexión a BD con transacciones
│   └── DayLog.php                # Sistema de logging
├── migrations/                    # Migraciones de base de datos
│   └── 001_create_videos_table.sql
├── tests/                         # Suite de tests
│   └── Unit/
│       ├── ApiAuthMiddlewareTest.php
│       ├── ApiResponseDTOTest.php
│       └── VideoUploadDTOTest.php
├── docker/                        # Configuración Docker
│   └── apache.conf
├── docs/                          # Documentación
│   ├── API_REFERENCE.md
│   ├── ARCHITECTURE.md (este archivo)
│   ├── TESTING_GUIDE.md
│   └── ...
├── uploads/                       # Archivos subidos
├── vendor/                        # Dependencias de Composer
├── core.php                       # Entry point de la aplicación
├── config.php                     # Configuración global
├── composer.json                  # Dependencias PHP
├── phpunit.xml                    # Configuración de tests
├── phpstan.neon                   # Configuración de análisis estático
├── Dockerfile                     # Imagen Docker
├── docker-compose.yml             # Orquestación de contenedores
├── .env                           # Variables de entorno
└── README.md                      # Documentación principal
```

## Arquitectura de Capas

### 1. Capa de Entrada (Entry Point)

**Archivo**: `core.php`

- Bootstrapping de la aplicación Slim 4
- Carga de variables de entorno (.env)
- Configuración de error handling
- Registro de middlewares globales
- Registro de rutas

```
Flujo: HTTP Request → core.php → Middleware → Routes → BLL → DAO → Database
```

### 2. Capa de Rutas (Routes)

**Directorio**: `app/Routes/`

- Define endpoints HTTP
- Valida parámetros de entrada
- Instancia BLL con inyección de dependencias
- Construye respuestas HTTP

**Patrón:**
```php
$group->post('/upload', function (Request $request, Response $response) {
    $db = new DBConnectorPDO(...);
    $videoBLL = new VideoBLL($db, $uploadPath);
    $apiResponse = $videoBLL->uploadVideo($videoDTO);
    // ...
});
```

### 3. Capa de Negocio (BLL - Business Logic Layer)

**Directorio**: `app/BLL/`

**Responsabilidades:**
- ✅ Validaciones de negocio
- ✅ Orquestación de operaciones
- ✅ Transformación de datos
- ✅ Manejo de transacciones
- ✅ Logging de operaciones

**Clase Base**: `BaseClass extends BaseComponent`

**Herencia:**
```
BaseComponent (común)
    ↓
BaseClass (específico BLL)
    ↓
VideoBLL (implementación)
```

**Ejemplo - VideoBLL.php:**
```php
class VideoBLL extends \App\BaseClass
{
    private VideoDAO $dao;
    private string $uploadBasePath;
    
    public function __construct(DBConnectorPDO $db, string $uploadBasePath = './uploads')
    {
        parent::__construct($db);
        $this->dao = new VideoDAO($db);
        $this->uploadBasePath = $uploadBasePath;
    }
    
    public function uploadVideo(VideoUploadDTO $videoDTO): ApiResponseDTO
    {
        // 1. Validar archivo
        $this->validateVideoFile($videoDTO);
        
        // 2. Verificar duplicados
        $existing = $this->dao->findByProjectAndIdentifier(...);
        if ($existing !== null) {
            return ApiResponseDTO::error('Video already exists', 409);
        }
        
        // 3. Crear estructura de directorios
        $targetPath = $this->buildTargetPath(...);
        
        // 4. Mover archivo físico
        $this->moveUploadedFile(...);
        
        // 5. Insertar registro en BD
        $videoId = $this->dao->insert($videoResponseDTO);
        
        // 6. Crear log de auditoría
        $this->dao->createAuditLog($videoId, 'upload', ...);
        
        return ApiResponseDTO::success($videoResponseDTO, 201);
    }
}
```

### 4. Capa de Acceso a Datos (DAO - Data Access Object)

**Directorio**: `app/DAO/`

**Responsabilidades:**
- ✅ Queries SQL
- ✅ Preparación de statements
- ✅ Mapeo de resultados a DTOs
- ✅ Propagación automática de errores de BD

**Clase Base**: `BaseDAO extends BaseComponent`

**Métodos Heredados:**
- `executeSelect($query, $params)`: Para SELECT queries
- `executeStatement($query, $params)`: Para INSERT/UPDATE/DELETE

**Ejemplo - VideoDAO.php:**
```php
class VideoDAO extends BaseDAO
{
    public function findById(int $id): ?VideoResponseDTO
    {
        $query = "SELECT * FROM videos WHERE id = ? AND deleted_at IS NULL";
        $results = $this->executeSelect($query, [$id]);
        
        if (empty($results)) {
            return null;
        }
        
        return VideoResponseDTO::fromArray($results[0]);
    }
    
    public function insert(VideoResponseDTO $video): int|false
    {
        $query = "INSERT INTO videos (project_id, video_identifier, ...) 
                  VALUES (?, ?, ...)";
        
        $result = $this->executeStatement($query, [
            $video->projectId,
            $video->videoIdentifier,
            // ...
        ]);
        
        return $result !== false ? $this->db->getLastInsertId() : false;
    }
}
```

### 5. Capa de Objetos de Transferencia (DTO)

**Directorio**: `app/DTO/`

**Propósito**: Transporte inmutable de datos entre capas

**DTOs Implementados:**
- `VideoUploadDTO`: Datos de entrada para upload
- `VideoResponseDTO`: Representación de video desde BD
- `ApiResponseDTO`: Estructura estándar de respuestas

**Características:**
- ✅ Propiedades readonly (PHP 8.1+)
- ✅ Validación en constructor
- ✅ Métodos estáticos de construcción (`fromArray`, `fromUploadedFile`)
- ✅ Serialización a array (`toArray()`)

### 6. Capa de Base de Datos

**Biblioteca**: `libraries/DBConnectorPDO.php`

**Características:**
- ✅ PDO con prepared statements
- ✅ Gestión de transacciones
- ✅ Logging automático de queries
- ✅ Manejo de errores con códigos empresariales
- ✅ Soporte multi-schema

**Uso:**
```php
$db = new DBConnectorPDO($user, $pass, $host, $port, $schema);
$db->setTx($transactionId);
$db->openConnection();

// Ejecutar query
$stmt = $db->execPrepare($query, $params);
$results = $stmt->fetchAll();

// Commit/Rollback automático
```

### 7. Capa de Middleware

**Directorio**: `app/Middleware/`

**Middlewares Activos:**
1. **VideoAuthMiddleware**: Autenticación por API key + Rate limiting
2. **ErrorHandlerMiddleware**: Manejo global de errores
3. **CORS Middleware**: Headers CORS (configurado en core.php)

**Flujo de Request:**
```
HTTP Request
    ↓
CORS Middleware
    ↓
VideoAuthMiddleware (autenticación)
    ↓
Route Handler
    ↓
ErrorHandlerMiddleware (en caso de error)
    ↓
HTTP Response
```

## Patrones de Diseño

### 1. Dependency Injection

Todas las clases reciben sus dependencias por constructor:

```php
public function __construct(DBConnectorPDO $db, string $uploadBasePath = './uploads')
{
    parent::__construct($db);
    $this->dao = new VideoDAO($db);
    $this->uploadBasePath = $uploadBasePath;
}
```

### 2. Repository Pattern

El DAO actúa como repository, encapsulando el acceso a datos:

```php
// En vez de SQL directo en BLL:
$videoBLL->dao->findById(1);
$videoBLL->dao->findByProjectAndIdentifier('PROJECT', 'VIDEO_001');
```

### 3. DTO Pattern

Transferencia de datos con objetos inmutables:

```php
$videoDTO = new VideoUploadDTO(
    projectId: 'PROJECT_TEST',
    videoIdentifier: 'VIDEO_001',
    // ...
);
```

### 4. Factory Pattern

Construcción de objetos desde diferentes fuentes:

```php
$videoDTO = VideoUploadDTO::fromUploadedFile($uploadedFile, $parsedBody);
$responseDTO = VideoResponseDTO::fromArray($dbRow);
```

### 5. Template Method Pattern

BaseDAO define estructura, subclases implementan detalles:

```php
abstract class BaseDAO {
    protected function executeSelect($query, $params) {
        // Template común
    }
}

class VideoDAO extends BaseDAO {
    public function findById($id) {
        // Usa executeSelect
    }
}
```

## Flujo de Datos

### Upload de Video

```
1. HTTP POST /v1/videos/upload
   ↓
2. VideoAuthMiddleware: Valida API key + Rate limit
   ↓
3. VideoRoutes: Valida parámetros requeridos
   ↓
4. VideoUploadDTO: Encapsula datos validados
   ↓
5. VideoBLL.uploadVideo():
   a. Valida extensión y tamaño
   b. Verifica duplicados (DAO)
   c. Crea estructura de directorios
   d. Mueve archivo físico
   e. Inserta registro (DAO)
   f. Crea audit log (DAO)
   ↓
6. VideoDAO.insert(): Prepared statement
   ↓
7. DBConnectorPDO: Ejecuta query + logging
   ↓
8. ApiResponseDTO: Estructura respuesta
   ↓
9. HTTP Response 201 Created
```

### Get Video by ID

```
1. HTTP GET /v1/videos/{id}
   ↓
2. VideoAuthMiddleware: Valida API key
   ↓
3. VideoRoutes: Extrae {id} del path
   ↓
4. VideoBLL.getVideoById(id)
   ↓
5. VideoDAO.findById(id): SELECT query
   ↓
6. DBConnectorPDO: Ejecuta + fetchAll
   ↓
7. VideoResponseDTO.fromArray(row)
   ↓
8. ApiResponseDTO: Envuelve resultado
   ↓
9. HTTP Response 200 OK
```

## Base de Datos

### Esquema Principal

**Tabla: `videos`**
```sql
CREATE TABLE `videos` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id` VARCHAR(50) NOT NULL,
  `video_identifier` VARCHAR(100) NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `duration` INT UNSIGNED NULL,
  `width` INT UNSIGNED NULL,
  `height` INT UNSIGNED NULL,
  `upload_ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `metadata` JSON NULL,
  `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  
  INDEX `idx_project_id` (`project_id`),
  INDEX `idx_video_identifier` (`video_identifier`),
  INDEX `idx_status` (`status`),
  INDEX `idx_deleted_at` (`deleted_at`),
  UNIQUE KEY `uk_project_video` (`project_id`, `video_identifier`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Tabla: `video_audit_log`**
```sql
CREATE TABLE `video_audit_log` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `video_id` BIGINT UNSIGNED NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `user_id` VARCHAR(100) NULL,
  `ip_address` VARCHAR(45) NULL,
  `details` JSON NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX `idx_video_id` (`video_id`),
  INDEX `idx_action` (`action`),
  FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Índices y Performance

- **Primary Key**: `id` (BIGINT UNSIGNED) para escalabilidad
- **Unique Constraint**: `(project_id, video_identifier, deleted_at)` para soft delete
- **Índices**: project_id, video_identifier, status, deleted_at para queries frecuentes
- **Foreign Key**: video_audit_log → videos con CASCADE DELETE

## Seguridad

### 1. Autenticación

- API Key en header `X-API-Key`
- Validación contra lista configurable en `.env`
- Sin estado (stateless)

### 2. Rate Limiting

- 60 requests/minuto por API key
- Ventana deslizante de 60 segundos
- Cache en memoria (property estática)

### 3. Validación de Entrada

- Tipos MIME permitidos (whitelist)
- Tamaño máximo de archivo (100MB)
- Sanitización de nombres de archivo
- Prepared statements (anti-SQL injection)

### 4. Soft Delete

- Campo `deleted_at` en vez de DELETE físico
- Permite recuperación de datos
- Auditoría completa

### 5. Logging

- Todas las operaciones registradas
- IP del cliente capturada
- User agent almacenado
- Audit trail inmutable

## Escalabilidad

### Horizontal

- ✅ Stateless design permite múltiples instancias
- ✅ Upload path configurable (puede ser shared storage)
- ✅ Base de datos centralizada

### Vertical

- ✅ Estructura de directorios evita saturación
- ✅ Índices optimizados
- ✅ Prepared statements con connection pooling

### Storage

```
uploads/
└── PROJECT_A/              ← Multi-tenant
    └── 2025/               ← Año (max 12 subdirs)
        └── 01/             ← Mes (max 31 subdirs)
            └── 15/         ← Día (max ~1000 videos/día)
                └── VIDEO_001/  ← Unique
```

Capacidad teórica: **Ilimitada** (limitado solo por storage físico)

## Testing

### Pirámide de Tests

```
           /\
          /  \  E2E (manual con Postman)
         /____\
        /      \  Integration (pendiente)
       /________\
      /          \  Unit Tests (14 tests)
     /____________\
```

### Cobertura Actual

- **Unit Tests**: 14 tests, 110 assertions
- **PHPStan**: Level 5, 0 errores
- **Coverage**: No disponible (sin Xdebug)

### Tests Automatizados

```bash
# Unit tests
./vendor/bin/phpunit --testdox

# Análisis estático
./vendor/bin/phpstan analyse

# Code style
./vendor/bin/phpcs
```

## Deployment

### Docker

```bash
# Build
docker-compose build

# Start
docker-compose up -d

# Logs
docker-compose logs -f

# Stop
docker-compose down
```

### Variables de Entorno

Copiar `.env.example` a `.env` y configurar:

```env
BDD_HOST=host.docker.internal
BDD_USER=sdc
BDD_PASS=your-password
BDD_SCHEMA=sdc_videos
VALID_API_KEYS=key1,key2,key3
UPLOAD_PATH=./uploads
```

### Health Check

```bash
curl http://localhost:8270/v1/videos/health
```

## Monitoreo

### Logs

- **Aplicación**: `app/log/`
- **Apache**: `docker logs sdc_upload_videos_api`
- **PHP Errors**: `log/php-error-YYYYMMDD.log`

### Métricas

- Rate limit por API key (en memoria)
- Audit log en base de datos
- Timestamps en todas las operaciones

## Mejoras Futuras

### Corto Plazo
- [ ] Tests de integración
- [ ] Coverage reports con Xdebug
- [ ] Docker healthcheck mejorado

### Mediano Plazo
- [ ] Procesamiento asíncrono de videos
- [ ] Extracción de metadatos (duration, width, height)
- [ ] Thumbnails automáticos
- [ ] Compresión de videos

### Largo Plazo
- [ ] CDN integration
- [ ] Streaming adaptativo (HLS)
- [ ] Transcoding multi-resolution
- [ ] Machine Learning (detección de contenido)

---

**Última actualización**: Diciembre 2025  
**Versión de documento**: 1.0
