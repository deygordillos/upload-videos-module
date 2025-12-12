# Development Guide

## Configuración del Entorno de Desarrollo

### Requisitos

- **PHP**: 8.3.16+
- **Composer**: 2.x
- **Docker Desktop**: 20.10+ (opcional pero recomendado)
- **Git**: 2.x
- **IDE**: VSCode/PhpStorm recomendados
- **MySQL**: 8.0+ (o usar contenedor Docker)

### Instalación Inicial

#### 1. Clonar Repositorio

```bash
git clone <repository-url>
cd sdc-video-upload-api
```

#### 2. Instalar Dependencias

```bash
# Dependencias de producción + desarrollo
composer install

# Solo producción (no recomendado para desarrollo)
composer install --no-dev
```

#### 3. Configurar Entorno Local

Crear archivo `.env` desde la plantilla:

```bash
cp .env.example .env
```

Editar `.env` para desarrollo:

```env
# Development settings
APP_ENV=development
APP_DEBUG=true
APP_PATH=/

# Local database
BDD_HOST=host.docker.internal
BDD_USER=root
BDD_PASS=root
BDD_PORT=3306
BDD_SCHEMA=sdc_videos

# Clave de prueba (no usar en producción)
VALID_API_KEYS=test-key-12345,dev-key-67890

# Directorios locales
UPLOAD_PATH=./uploads
LOG_PATH=./app/log
LOG_LEVEL=debug
```

#### 4. Base de Datos

**Con Docker** (recomendado):

```bash
# Ya está incluido en docker-compose.yml
docker-compose up -d

# Ejecutar migraciones
docker exec -i sdc_upload_videos_mysql mysql -uroot -proot sdc_videos < migrations/001_create_videos_table.sql
```

**Sin Docker**:

```bash
# Crear BD
mysql -u root -p -e "CREATE DATABASE sdc_videos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Ejecutar migraciones
mysql -u root -p sdc_videos < migrations/001_create_videos_table.sql
```

#### 5. Iniciar Servidor

**Con Docker**:

```bash
docker-compose up
```

**Sin Docker**:

```bash
# Usar PHP built-in server (solo desarrollo)
php -S localhost:8270 -t . core.php
```

#### 6. Verificar Instalación

```bash
# Health check
curl http://localhost:8270/v1/videos/health

# Debe retornar:
# {"status":{"code":200},"database":"connected"}
```

## Estructura del Proyecto

```
sdc-video-upload-api/
├── app/                          # Código de la aplicación
│   ├── BLL/                      # Business Logic Layer
│   │   └── VideoBLL.php          # Lógica de negocio para videos
│   ├── DAO/                      # Data Access Objects
│   │   ├── BaseDAO.php           # Clase base para DAOs
│   │   ├── VideoDAO.php          # Acceso a datos de videos
│   │   └── ConfigurationsDAO.php # Ejemplo de patrón DAO
│   ├── DTO/                      # Data Transfer Objects
│   │   ├── ApiResponseDTO.php    # Respuesta estándar de API
│   │   ├── VideoResponseDTO.php  # DTO para respuesta de video
│   │   └── VideoUploadDTO.php    # DTO para upload de video
│   ├── Routes/                   # Definición de rutas
│   │   └── VideoRoutes.php       # Rutas de videos
│   ├── Middleware/               # Middlewares
│   │   ├── AuthMiddleware.php    # Autenticación (legacy)
│   │   └── ErrorHandlerMiddleware.php
│   ├── Handlers/                 # Manejadores de errores
│   │   ├── HttpErrorHandler.php
│   │   └── ShutdownHandler.php
│   ├── Utils/                    # Utilidades
│   │   ├── DatabaseConnection.php
│   │   └── DayLog.php            # Logging diario
│   ├── Estructure/               # Clases base (legacy)
│   │   ├── BaseMethod.php
│   │   ├── BLL/
│   │   │   └── CapacityBLL.php   # Ejemplo de BLL
│   │   └── DAO/
│   │       └── CapacityDAO.php   # Ejemplo de DAO
│   ├── emailTemplates/           # Plantillas de email
│   └── log/                      # Logs de aplicación (git ignored)
├── docs/                         # Documentación
│   ├── API_REFERENCE.md          # Referencia completa de API
│   ├── ARCHITECTURE.md           # Documentación de arquitectura
│   ├── DEPLOYMENT.md             # Guía de despliegue
│   ├── DEVELOPMENT.md            # Esta guía
│   ├── TESTING_GUIDE.md          # Guía de testing
│   └── ...
├── migrations/                   # Migraciones de base de datos
│   └── 001_create_videos_table.sql
├── tests/                        # Tests automatizados
│   ├── Unit/                     # Tests unitarios
│   │   ├── ApiAuthMiddlewareTest.php
│   │   ├── VideoBLLTest.php
│   │   └── VideoDAOTest.php
│   └── Integration/              # Tests de integración (comentados)
├── uploads/                      # Archivos subidos (git ignored)
├── vendor/                       # Dependencias Composer (git ignored)
├── docker/                       # Configuración Docker
│   └── apache.conf               # Configuración de Apache
├── .env                          # Variables de entorno (git ignored)
├── .env.example                  # Plantilla de .env
├── composer.json                 # Dependencias PHP
├── config.php                    # Configuración global
├── core.php                      # Punto de entrada de la aplicación
├── docker-compose.yml            # Orchestration de Docker
├── Dockerfile                    # Imagen Docker
├── phpunit.xml                   # Configuración PHPUnit
├── phpstan.neon                  # Configuración PHPStan
└── README.md                     # Documentación principal
```

## Workflow de Desarrollo

### 1. Crear Nueva Feature

```bash
# Crear rama desde main
git checkout main
git pull origin main
git checkout -b feature/nombre-feature

# Trabajar en la feature...

# Commit con mensaje descriptivo
git add .
git commit -m "feat: descripción de la feature"

# Push
git push origin feature/nombre-feature

# Crear Pull Request en GitHub/GitLab
```

### 2. Convenciones de Commitsits:

**Formato del Proyecto** (preferido):
```
[feature]: nueva funcionalidad
[fix]: corrección de bug
[docs]: cambios en documentación
[style]: formateo, sin cambios de lógica
[refactor]: refactorización sin cambiar funcionalidad
[test]: añadir o modificar tests
[chore]: cambios en build, config, etc.
```

**[Conventional Commits](https://www.conventionalcommits.org/)** (también aceptado):
```
feat: nueva funcionalidad
fix: corrección de bug
docs: cambios en documentación
style: formateo, sin cambios de lógica
refactor: refactorización sin cambiar funcionalidad
test: añadir o modificar tests
chore: cambios en build, config, etc.
```

Ejemplos:

```bash
# Formato del proyecto
git commit -m "[feature]: add video thumbnail generation"
git commit -m "[fix]: correct MIME type validation in VideoBLL"

# Conventional Commits (también válido)
git commit -m "feat: add video thumbnail generation"
git commit -m "fix: correct MIME type validation in VideoBLL"
```

### 3. Code Review Checklist

Antes de crear PR, verificar:

- [ ] Código sigue estándares (PSR-12)
- [ ] Tests unitarios añadidos/actualizados
- [ ] PHPStan sin errores (`composer phpstan`)
- [ ] PHPUnit tests pasan (`composer test`)
- [ ] Documentación actualizada
- [ ] Sin credenciales hardcodeadas
- [ ] Variables de config en `.env`
- [ ] Logs apropiados añadidos
- [ ] Manejo de errores implementado

## Estándares de Código

### PSR-12 Coding Style

El proyecto sigue [PSR-12](https://www.php-fig.org/psr/psr-12/):

```php
<?php

declare(strict_types=1);

namespace App\BLL;

use App\DAO\VideoDAO;
use Libraries\Utils\DBConnectorPDO;

/**
 * VideoBLL - Business logic para gestión de videos
 */
class VideoBLL extends \App\BaseClass
{
    private VideoDAO $videoDAO;

    public function __construct(DBConnectorPDO $dbConnector)
    {
        parent::__construct($dbConnector);
        $this->videoDAO = new VideoDAO($dbConnector);
    }

    public function uploadVideo(array $data): array
    {
        // Validación
        if (empty($data['project_id'])) {
            return $this->setErrorResponse(
                ERROR_CODE_BAD_REQUEST,
                'project_id es requerido'
            );
        }

        // Lógica de negocio...
    }
}
```

### Naming Conventions

#### Clases

- **BLL**: `{Entity}BLL.php` (ej: `VideoBLL.php`)
- **DAO**: `{Entity}DAO.php` (ej: `VideoDAO.php`)
- **DTO**: `{Entity}{Purpose}DTO.php` (ej: `VideoUploadDTO.php`)
- **Routes**: `{Entity}Routes.php` (ej: `VideoRoutes.php`)

#### Métodos

- **Public**: `camelCase` (ej: `uploadVideo()`)
- **Private**: `camelCase` (ej: `validateVideoFile()`)
- **Setters**: `set{Property}()` (ej: `setTitle()`)
- **Getters**: `get{Property}()` (ej: `getVideoId()`)
- **Validators**: `validate{What}()` (ej: `validateFileSize()`)
- **Builders**: `build{What}()` (ej: `buildTargetPath()`)

#### Variables

```php
// Camel case
$videoTitle = 'Mi video';
$uploadPath = './uploads';

// Constantes en mayúsculas
const MAX_FILE_SIZE = 104857600;
const ALLOWED_MIMES = ['video/mp4', 'video/mpeg'];

// Arrays asociativos descriptivos
$videoData = [
    'project_id' => 'PROJECT_123',
    'identifier' => 'VIDEO_001',
    'title' => 'Video de prueba',
];
```

### Type Hints

Siempre usar type hints en PHP 8.3+:

```php
// ✅ CORRECTO
public function uploadVideo(
    string $projectId,
    array $fileData,
    ?string $title = null
): array {
    // ...
}

// ❌ INCORRECTO
public function uploadVideo($projectId, $fileData, $title = null) {
    // ...
}
```

### Documentación de Código

```php
/**
 * Sube un video al sistema
 *
 * @param string $projectId ID del proyecto
 * @param array $fileData Datos del archivo ($_FILES)
 * @param string|null $title Título del video (opcional)
 * @return array Response con resultado de la operación
 * @throws \Exception Si el archivo no es válido
 */
public function uploadVideo(
    string $projectId,
    array $fileData,
    ?string $title = null
): array {
    // ...
}
```

### Manejo de Errores

```php
// Usar setErrorResponse del BaseClass
if ($validationError) {
    return $this->setErrorResponse(
        ERROR_CODE_BAD_REQUEST,
        'Descripción del error'
    );
}

// Try-catch para operaciones de DB/filesystem
try {
    $result = $this->videoDAO->insert($data);
} catch (\Throwable $e) {
    $this->logError('Error al insertar video', $e);
    return $this->setErrorResponse(
        ERROR_CODE_INTERNAL_SERVER,
        'Error interno del servidor'
    );
}
```

## Testing

### Ejecutar Tests

```bash
# Todos los tests
composer test

# Solo tests unitarios
./vendor/bin/phpunit --testsuite Unit

# Test específico
./vendor/bin/phpunit tests/Unit/VideoBLLTest.php

# Con cobertura (requiere Xdebug)
composer test:coverage
```

### Escribir Tests Unitarios

Ejemplo de test para BLL:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\BLL\VideoBLL;
use App\DAO\VideoDAO;
use Libraries\Utils\DBConnectorPDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class VideoBLLTest extends TestCase
{
    private VideoBLL $videoBLL;
    private VideoDAO|MockObject $mockVideoDAO;
    private DBConnectorPDO|MockObject $mockDB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock dependencies
        $this->mockDB = $this->createMock(DBConnectorPDO::class);
        $this->mockVideoDAO = $this->createMock(VideoDAO::class);
        
        // Inject mocks
        $this->videoBLL = new VideoBLL($this->mockDB);
        $reflection = new \ReflectionClass($this->videoBLL);
        $property = $reflection->getProperty('videoDAO');
        $property->setAccessible(true);
        $property->setValue($this->videoBLL, $this->mockVideoDAO);
    }

    public function testUploadVideoSuccess(): void
    {
        // Arrange
        $data = [
            'project_id' => 'PROJECT_TEST',
            'identifier' => 'VIDEO_001',
            'title' => 'Test Video'
        ];
        
        $this->mockVideoDAO->expects($this->once())
            ->method('insert')
            ->willReturn(['id' => 1]);
        
        // Act
        $result = $this->videoBLL->uploadVideo($data);
        
        // Assert
        $this->assertIsArray($result);
        $this->assertEquals(201, $result['code']);
    }
}
```

### Coverage Target

- **Mínimo**: 70%
- **Objetivo**: 80%+
- **Crítico (BLL/DAO)**: 90%+

## Análisis Estático

### PHPStan

```bash
# Análisis completo (Level 5)
composer phpstan

# Level específico
./vendor/bin/phpstan analyse --level=4

# Con errores baseline
./vendor/bin/phpstan analyse --generate-baseline
```

Configuración en `phpstan.neon`:

```neon
parameters:
    level: 5
    paths:
        - app
    bootstrapFiles:
        - phpstan-bootstrap.php
    ignoreErrors:
        # Solo ignorar errores externos inevitables
        - '#Call to an undefined method Psr\\Container\\ContainerInterface::get#'
```

### PHP CS Fixer

```bash
# Instalar (si no está)
composer require --dev friendsofphp/php-cs-fixer

# Fix automático
./vendor/bin/php-cs-fixer fix

# Dry-run (ver qué cambiaría)
./vendor/bin/php-cs-fixer fix --dry-run --diff
```

## Debugging

### Xdebug con VSCode

#### 1. Instalar Xdebug en Docker

Añadir a `Dockerfile`:

```dockerfile
RUN pecl install xdebug-3.3.0 \
    && docker-php-ext-enable xdebug

COPY docker/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
```

Crear `docker/xdebug.ini`:

```ini
[xdebug]
zend_extension=xdebug.so
xdebug.mode=debug
xdebug.client_host=host.docker.internal
xdebug.client_port=9003
xdebug.start_with_request=yes
```

#### 2. Configurar VSCode

Crear `.vscode/launch.json`:

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "pathMappings": {
                "/api": "${workspaceFolder}"
            }
        }
    ]
}
```

#### 3. Usar Debugger

1. Poner breakpoint en VSCode (click en margen izquierdo)
2. Iniciar debug (F5)
3. Hacer request a la API
4. VSCode pausará en el breakpoint

### Logging

```php
// En BLL/DAO
use Libraries\Utils\DayLog;

// Debug level
$this->logDebug('Iniciando upload de video', ['project_id' => $projectId]);

// Info level
$this->logInfo('Video subido exitosamente', ['video_id' => $videoId]);

// Error level
$this->logError('Error al procesar video', $exception);
```

Ver logs:

```bash
# Con Docker
docker-compose logs -f

# Logs de aplicación
tail -f app/log/$(date +%Y%m%d).log

# Filtrar por nivel
grep "ERROR" app/log/*.log
```

## Base de Datos

### Migraciones

Crear nueva migración:

```bash
# Crear archivo
touch migrations/002_add_video_categories.sql
```

Formato de migración:

```sql
-- migrations/002_add_video_categories.sql
-- Descripción: Añade tabla de categorías de videos
-- Fecha: 2025-12-11

-- Crear tabla
CREATE TABLE IF NOT EXISTS video_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Añadir columna a videos
ALTER TABLE videos 
ADD COLUMN category_id INT UNSIGNED NULL AFTER description,
ADD CONSTRAINT fk_videos_category 
    FOREIGN KEY (category_id) 
    REFERENCES video_categories(id);

-- Datos iniciales
INSERT INTO video_categories (name) VALUES 
    ('Tutorial'),
    ('Presentación'),
    ('Otro');
```

Ejecutar migración:

```bash
# Con Docker
docker exec -i sdc_upload_videos_mysql mysql -uroot -proot sdc_videos < migrations/002_add_video_categories.sql

# Sin Docker
mysql -u root -p sdc_videos < migrations/002_add_video_categories.sql
```

### Queries de Desarrollo

```sql
-- Ver todos los videos
SELECT * FROM videos WHERE deleted_at IS NULL;

-- Ver audit log
SELECT * FROM video_audit_log ORDER BY created_at DESC LIMIT 10;

-- Limpiar datos de prueba
DELETE FROM videos WHERE project_id = 'PROJECT_TEST';
DELETE FROM video_audit_log WHERE video_id NOT IN (SELECT id FROM videos);

-- Resetear auto_increment
ALTER TABLE videos AUTO_INCREMENT = 1;

-- Ver tamaño de uploads
SELECT 
    project_id,
    COUNT(*) as total_videos,
    SUM(file_size) as total_size_bytes,
    ROUND(SUM(file_size)/1024/1024, 2) as total_size_mb
FROM videos 
WHERE deleted_at IS NULL
GROUP BY project_id;
```

## Herramientas Recomendadas

### VSCode Extensions

- **PHP Intelephense**: Autocompletado y navegación
- **PHP Debug**: Integración con Xdebug
- **GitLens**: Historial de Git avanzado
- **Docker**: Gestión de contenedores
- **REST Client**: Testing de API desde VSCode
- **Error Lens**: Ver errores inline
- **Todo Tree**: Gestionar TODOs en código

### Postman/Thunder Client

Importar colección:

```bash
# Abrir Postman
# Import > postman_collection.json
```

Variables de entorno en Postman:

```json
{
  "base_url": "http://localhost:8270",
  "api_key": "test-key-12345"
}
```

### MySQL Workbench

Conexión local:

- **Host**: localhost
- **Port**: 3306 (o el puerto expuesto en docker-compose.yml)
- **User**: root
- **Password**: root
- **Schema**: sdc_videos

## Performance

### Profiling con Xdebug

```bash
# Habilitar profiling en docker/xdebug.ini
xdebug.mode=profile
xdebug.output_dir=/tmp/xdebug

# Analizar con webgrind o kcachegrind
```

### Query Optimization

```php
// Activar query logging
$this->dbConnector->enableQueryLog();

// Ejecutar operaciones...

// Ver queries
$queries = $this->dbConnector->getQueryLog();
foreach ($queries as $query) {
    echo "Query: {$query['sql']}\n";
    echo "Time: {$query['time']}ms\n";
}
```

### Caching (Futuro)

```php
// Redis para cache de respuestas frecuentes
$redis = new Redis();
$redis->connect('redis', 6379);

$cacheKey = "video:{$videoId}";
if ($redis->exists($cacheKey)) {
    return json_decode($redis->get($cacheKey), true);
}

$video = $this->videoDAO->findById($videoId);
$redis->setex($cacheKey, 3600, json_encode($video)); // Cache 1 hora
```

## Troubleshooting Común

### Puerto 8270 ocupado

```bash
# Windows
netstat -ano | findstr :8270
taskkill /PID <PID> /F

# Linux/Mac
lsof -i :8270
kill -9 <PID>

# O cambiar puerto en docker-compose.yml
ports:
  - "8271:80"  # Usar 8271 en lugar de 8270
```

### Permisos de uploads/

```bash
# Docker
docker exec -it sdc_upload_videos_api chmod -R 775 /api/uploads
docker exec -it sdc_upload_videos_api chown -R www-data:www-data /api/uploads

# Sin Docker (Linux)
sudo chown -R $USER:www-data uploads/
chmod -R 775 uploads/
```

### Composer muy lento

```bash
# Usar mirrors locales
composer config -g repos.packagist composer https://packagist.org

# Limpiar cache
composer clear-cache

# Parallel downloads (Composer 2.2+)
composer config -g allow-plugins.composer/installers true
```

### PHPUnit no encuentra clases

```bash
# Regenerar autoload
composer dump-autoload

# Verificar autoload en composer.json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Libraries\\": "vendor/Libraries/"
    }
}
```

## Recursos

### Documentación Interna

- [API Reference](./API_REFERENCE.md) - Endpoints completos
- [Architecture](./ARCHITECTURE.md) - Diseño del sistema
- [Testing Guide](./TESTING_GUIDE.md) - Estrategias de testing
- [Deployment](./DEPLOYMENT.md) - Despliegue a producción

### Referencias Externas

- [Slim Framework](https://www.slimframework.com/docs/v4/)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [PHPStan Rules](https://phpstan.org/user-guide/rules)
- [Docker Compose](https://docs.docker.com/compose/)

---

**Última actualización**: Diciembre 2025  
**Versión**: 1.0  
**Mantenedor**: Team Backend
