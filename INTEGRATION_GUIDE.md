# Guía de Integración - API de Videos

## Resumen de Cambios

La API de videos ha sido adaptada para seguir el patrón arquitectónico estándar de la empresa, utilizando `BaseClass`, `BaseComponent` y `DBConnectorPDO` en lugar de las implementaciones standalone originales.

## Arquitectura Actualizada

### Capas Implementadas

```
┌─────────────────────────────────────────────┐
│           Slim 4 Routes Layer               │
│     (VideoRoutes.php - REST Endpoints)      │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│        Business Logic Layer (BLL)           │
│   VideoBLL extends \App\BaseClass           │
│   - Validación de archivos                  │
│   - Lógica de almacenamiento                │
│   - Manejo de respuestas                    │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│        Data Access Layer (DAO)              │
│   VideoDAO extends BaseDAO                  │
│   - executeSelect()                         │
│   - executeStatement()                      │
│   - Prepared statements                     │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│         Database Connection                 │
│   DBConnectorPDO (Libraries)                │
│   - PDO con manejo de errores               │
│   - Logging automático                      │
│   - Métricas de performance                 │
└─────────────────────────────────────────────┘
```

## Cambios Principales

### 1. VideoBLL (Business Logic Layer)

**Antes:**
```php
class VideoBLL
{
    public function __construct(DatabaseConnection $db, string $uploadBasePath = './uploads')
    {
        $this->dao = new VideoDAO($db);
        $this->log = $db->getLog();
        $this->tx = $db->getTx();
    }
}
```

**Después:**
```php
class VideoBLL extends \App\BaseClass
{
    public function __construct(DBConnectorPDO $db, string $uploadBasePath = './uploads')
    {
        parent::__construct($db);
        $this->dao = new VideoDAO($db);
        $this->uploadBasePath = rtrim($uploadBasePath, '/\\');
    }
}
```

**Beneficios:**
- Hereda métodos de logging y manejo de errores de `BaseClass`
- Acceso a `$this->log`, `$this->tx`, `$this->db` automáticamente
- Consistencia con otros BLL de la empresa (ej: `ConfigurationsBLL`)

### 2. VideoDAO (Data Access Object)

**Antes:**
```php
class VideoDAO extends BaseDAO
{
    private PDO $pdo;
    private DayLog $log;
    private string $tx;

    public function __construct(DatabaseConnection $db)
    {
        $this->pdo = $db->getConnection();
        $this->log = $db->getLog();
        $this->tx = $db->getTx();
    }

    public function insert(VideoUploadDTO $video, string $filePath): ?int
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':project_id', $video->projectId, PDO::PARAM_STR);
        // ... más bindValue
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }
}
```

**Después:**
```php
class VideoDAO extends BaseDAO
{
    public function insert(VideoUploadDTO $video, string $filePath): ?int
    {
        $query = "INSERT INTO videos (...) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $video->projectId,
            $video->videoIdentifier,
            $video->originalFilename,
            // ... más parámetros
        ];
        
        $result = $this->executeStatement($query, $params);
        
        if ($result && is_int($result)) {
            return $result;
        }
        return null;
    }
}
```

**Beneficios:**
- Uso de `executeStatement()` y `executeSelect()` de `BaseDAO`
- Manejo automático de errores
- Prepared statements con placeholders `?`
- No necesita gestionar PDO manualmente
- Propagación automática de errores

### 3. VideoRoutes (Slim 4 Integration)

**Antes:**
```php
$db = new DatabaseConnection();
$db->setTx($tx);
$db->setLog($log);
```

**Después:**
```php
$db = new DBConnectorPDO(USER_DB, PASS_DB, HOST_DB, PORT_DB, SCHEMA_DB);
$db->setTx($tx);
$db->setLog($log);
$db->openConnection();
```

**Beneficios:**
- Usa constantes globales de configuración (`USER_DB`, `PASS_DB`, etc.)
- Consistente con el resto de la aplicación
- Gestión explícita de conexión con `openConnection()`

## Estructura de Archivos Actualizada

```
app/
├── BLL/
│   ├── VideoBLL.php          ← extends \App\BaseClass
│   └── ConfigurationsBLL.php ← patrón de referencia
├── DAO/
│   ├── VideoDAO.php          ← extends BaseDAO, usa executeStatement/executeSelect
│   ├── BaseDAO.php           ← clase base con métodos comunes
│   └── ConfigurationsDAO.php ← patrón de referencia
├── DTO/
│   ├── VideoUploadDTO.php    ← sin cambios (inmutable)
│   ├── VideoResponseDTO.php  ← sin cambios
│   └── ApiResponseDTO.php    ← sin cambios
├── Routes/
│   └── VideoRoutes.php       ← usa DBConnectorPDO
├── Middleware/
│   └── VideoAuthMiddleware.php ← PSR-15 compatible
├── BaseClass.php             ← clase base para BLL
└── BaseComponent.php         ← clase base común
```

## Endpoints de la API

Las rutas están registradas en `core.php`:

```php
$app->group('/api/videos', function (RouteCollectorProxy $group) {
    VideoRoutes::register($group);
})->add(new VideoAuthMiddleware());
```

### Endpoints Disponibles

| Método | Ruta | Descripción | Auth |
|--------|------|-------------|------|
| GET | `/api/videos/health` | Health check | No |
| POST | `/api/videos/upload` | Subir video | Sí |
| GET | `/api/videos/{id}` | Obtener por ID | Sí |
| GET | `/api/videos/project/{projectId}` | Listar por proyecto | Sí |
| DELETE | `/api/videos/{id}` | Eliminar video | Sí |

## Configuración Requerida

### 1. Variables de Entorno (.env)

```env
# API Authentication
VALID_API_KEYS=test-key-12345,prod-key-67890

# Upload Configuration
UPLOAD_PATH=c:/uploads/videos
```

### 2. Constantes (config.php)

Asegúrate de que estas constantes estén definidas:

```php
define('USER_DB', 'your_db_user');
define('PASS_DB', 'your_db_pass');
define('HOST_DB', 'localhost');
define('PORT_DB', 3306);
define('SCHEMA_DB', 'your_database');
define('BASE_HOME_PATH', __DIR__);

// Error codes
define('ERROR_CODE_OK', '0');
define('ERROR_DESC_OK', 'OK');
define('ERROR_CODE_500', '500');
define('ERROR_CODE_NO_FOUND_RECORD', '404');
define('ERROR_DESC_NO_FOUND_RECORD', 'No records found');
```

### 3. Base de Datos

Ejecutar la migración:

```bash
mysql -u root -p < migrations/001_create_videos_table.sql
```

## Testing

### 1. Health Check (Sin autenticación)

```bash
curl http://localhost/api/videos/health
```

Respuesta esperada:
```json
{
  "status": {
    "code": 200,
    "message": "API is running"
  },
  "data": {
    "service": "Video Upload API",
    "version": "1.0.0",
    "database": "connected",
    "timestamp": "2025-12-11 15:30:00"
  }
}
```

### 2. Upload Video (Con autenticación)

```bash
curl -X POST http://localhost/api/videos/upload \
  -H "X-API-Key: test-key-12345" \
  -F "video=@/path/to/video.mp4" \
  -F "project_id=PROJECT_001" \
  -F "video_identifier=VID_12345" \
  -F 'metadata={"description":"Test video","tags":["test"]}'
```

### 3. Get Video by ID

```bash
curl http://localhost/api/videos/1 \
  -H "X-API-Key: test-key-12345"
```

## Patrones de Código

### 1. Crear un nuevo método en BLL

```php
/**
 * Método de ejemplo siguiendo el patrón de la empresa
 * @param array $data Input data
 * @param string $operation Nombre de la operación
 * @return array Estructura de respuesta
 */
public function exampleMethod($data, $operation)
{
    $this->setOperation($operation);
    
    $tx = substr(uniqid(), 3);
    $log = new DayLog(BASE_HOME_PATH, __FUNCTION__);
    $log->writeLog("$tx Init:::\n");
    
    $this->dao->setLog($log);
    $this->dao->setTx($tx);
    
    // Lógica de negocio aquí
    $result = $this->dao->someMethod($data);
    
    if ($this->dao->getError() != ERROR_CODE_OK) {
        $this->setError($this->dao->getError());
        $this->setErrorDescription($this->dao->getErrorDescription());
    } else {
        $this->setError(ERROR_CODE_OK);
        $this->setErrorDescription(ERROR_DESC_OK);
    }
    
    $arrayResponse = $this->setBuildResponse($result);
    $log->writeLog("$tx Response: " . json_encode($arrayResponse) . "\n");
    
    return $arrayResponse;
}
```

### 2. Crear un nuevo método en DAO

```php
/**
 * Método de ejemplo usando executeSelect
 * @param int $id
 * @return array
 */
public function exampleSelect(int $id): array
{
    $query = "SELECT id, name, value 
              FROM example_table 
              WHERE id = ? AND deleted_at IS NULL";
    
    return $this->executeSelect($query, [$id]);
}

/**
 * Método de ejemplo usando executeStatement
 * @param array $data
 * @return bool|int
 */
public function exampleInsert(array $data): bool|int
{
    $query = "INSERT INTO example_table (name, value) 
              VALUES (?, ?)";
    
    return $this->executeStatement($query, [$data['name'], $data['value']]);
}
```

## Seguridad Implementada

✅ **OWASP Top 10 Compliance:**
- API Key Authentication
- Rate Limiting (60 req/min)
- Prepared Statements (SQL Injection prevention)
- File Type Validation (MIME type checking)
- Path Traversal Prevention
- Input Validation en DTOs
- Soft Deletes
- Audit Logging

## Logging

Todos los logs se escriben automáticamente en:
```
app/log/VideoUpload-YYYYMMDD.log
app/log/VideoBLL-YYYYMMDD.log
app/log/VideoDAO-YYYYMMDD.log
```

Formato:
```
[tx_id] [video_bll] Starting video upload process
[tx_id] [video_dao] Video inserted successfully: ID=123
```

## Troubleshooting

### Error: "Class 'App\Utils\DatabaseConnection' not found"

**Solución:** Ya actualizado. Ahora usa `Libraries\DBConnectorPDO`.

### Error: "Call to undefined method getConnection()"

**Solución:** Cambiar a `$db->openConnection()` y usar métodos de `BaseDAO`.

### Error: "SQLSTATE[HY000] [1045] Access denied"

**Solución:** Verificar constantes en `config.php`:
```php
define('USER_DB', 'correct_user');
define('PASS_DB', 'correct_password');
```

### Error: "Failed to store video file"

**Solución:** Verificar permisos del directorio:
```bash
mkdir -p c:/uploads/videos
chmod 755 c:/uploads/videos
```

## Próximos Pasos

1. ✅ Ejecutar migración de base de datos
2. ✅ Configurar `.env` con API keys
3. ✅ Configurar `UPLOAD_PATH`
4. ⏳ Probar health check
5. ⏳ Probar upload con Postman
6. ⏳ Revisar logs para errores
7. ⏳ Ejecutar tests unitarios

## Contacto

Para dudas sobre esta integración, consultar:
- `AGENTS.md` - Guía para agentes de IA
- `README.md` - Documentación completa
- `QUICKSTART.md` - Inicio rápido

---

**Última actualización:** Diciembre 11, 2025  
**Versión:** 1.0.0 (Adaptada a patrón de empresa)
