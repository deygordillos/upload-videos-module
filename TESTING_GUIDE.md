# 🧪 Guía de Pruebas - API de Videos

## Pre-requisitos

### 1. Base de Datos
```bash
# Ejecutar migración
mysql -u root -p < migrations/001_create_videos_table.sql

# Verificar tablas creadas
mysql -u root -p -e "USE your_database; SHOW TABLES LIKE 'video%';"
```

### 2. Configuración (.env)
```env
# API Keys (separadas por coma)
VALID_API_KEYS=test-key-12345,prod-key-67890,dev-key-abcde

# Directorio de uploads
UPLOAD_PATH=c:/uploads/videos
```

### 3. Constantes (config.php)
Verificar que existan:
```php
define('USER_DB', 'your_db_user');
define('PASS_DB', 'your_db_password');
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

### 4. Directorio de Uploads
```bash
# Crear directorio
mkdir -p c:/uploads/videos

# Dar permisos (Windows)
icacls "c:\uploads\videos" /grant Users:(OI)(CI)F

# Dar permisos (Linux/Mac)
chmod 755 c:/uploads/videos
```

### 5. Composer
```bash
# Instalar dependencias
composer install
```

---

## Pruebas con cURL

### 1. Health Check (Sin Autenticación)

```bash
curl -X GET http://localhost/api/videos/health
```

**Respuesta Esperada (200):**
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
    "timestamp": "2025-12-11 16:45:30"
  }
}
```

**Posibles Errores:**
- `"database": "error"` → Verificar constantes de BD en `config.php`
- `404 Not Found` → Verificar que las rutas estén registradas en `core.php`

---

### 2. Upload Video (Con Autenticación)

```bash
curl -X POST http://localhost/api/videos/upload \
  -H "X-API-Key: test-key-12345" \
  -F "video=@/path/to/your/video.mp4" \
  -F "project_id=PROJECT_001" \
  -F "video_identifier=VID_20251211_001" \
  -F 'metadata={"description":"Video de prueba","tags":["test","demo"],"author":"Test User"}'
```

**Respuesta Esperada (201):**
```json
{
  "status": {
    "code": 201,
    "message": "Video uploaded successfully"
  },
  "data": {
    "id": 1,
    "project_id": "PROJECT_001",
    "video_identifier": "VID_20251211_001",
    "original_filename": "video.mp4",
    "file_path": "PROJECT_001/2025/12/11/VID_20251211_001/video.mp4",
    "file_size": 15728640,
    "mime_type": "video/mp4",
    "status": "completed",
    "created_at": "2025-12-11 16:50:00",
    "updated_at": "2025-12-11 16:50:00"
  }
}
```

**Posibles Errores:**

| Error | Causa | Solución |
|-------|-------|----------|
| `401 Unauthorized` | API key inválida | Verificar `.env` → `VALID_API_KEYS` |
| `429 Too Many Requests` | Rate limit excedido | Esperar 1 minuto |
| `400 Bad Request: project_id is required` | Falta campo requerido | Agregar `-F "project_id=..."` |
| `400 Bad Request: No video file provided` | Falta archivo | Verificar path del archivo |
| `500 Internal Server Error` | Error de servidor | Revisar logs en `app/log/VideoUpload-YYYYMMDD.log` |

---

### 3. Get Video by ID

```bash
curl -X GET http://localhost/api/videos/1 \
  -H "X-API-Key: test-key-12345"
```

**Respuesta Esperada (200):**
```json
{
  "status": {
    "code": 200,
    "message": "OK"
  },
  "data": {
    "id": 1,
    "project_id": "PROJECT_001",
    "video_identifier": "VID_20251211_001",
    "original_filename": "video.mp4",
    "file_path": "PROJECT_001/2025/12/11/VID_20251211_001/video.mp4",
    "file_size": 15728640,
    "mime_type": "video/mp4",
    "status": "completed",
    "created_at": "2025-12-11 16:50:00"
  }
}
```

**Respuesta Error (404):**
```json
{
  "status": {
    "code": 404,
    "message": "Video not found"
  },
  "data": null
}
```

---

### 4. Get Videos by Project

```bash
# Sin paginación (default: página 1, 50 resultados)
curl -X GET http://localhost/api/videos/project/PROJECT_001 \
  -H "X-API-Key: test-key-12345"

# Con paginación
curl -X GET "http://localhost/api/videos/project/PROJECT_001?page=1&per_page=10" \
  -H "X-API-Key: test-key-12345"
```

**Respuesta Esperada (200):**
```json
{
  "status": {
    "code": 200,
    "message": "OK"
  },
  "data": {
    "videos": [
      {
        "id": 3,
        "project_id": "PROJECT_001",
        "video_identifier": "VID_20251211_003",
        "original_filename": "video3.mp4",
        "created_at": "2025-12-11 17:00:00"
      },
      {
        "id": 2,
        "project_id": "PROJECT_001",
        "video_identifier": "VID_20251211_002",
        "original_filename": "video2.mp4",
        "created_at": "2025-12-11 16:55:00"
      },
      {
        "id": 1,
        "project_id": "PROJECT_001",
        "video_identifier": "VID_20251211_001",
        "original_filename": "video.mp4",
        "created_at": "2025-12-11 16:50:00"
      }
    ],
    "pagination": {
      "page": 1,
      "per_page": 50,
      "count": 3
    }
  }
}
```

---

### 5. Delete Video (Soft Delete)

```bash
curl -X DELETE http://localhost/api/videos/1 \
  -H "X-API-Key: test-key-12345"
```

**Respuesta Esperada (200):**
```json
{
  "status": {
    "code": 200,
    "message": "Video deleted successfully"
  },
  "data": null
}
```

**Nota:** El archivo físico NO se elimina, solo se marca como eliminado en la BD (soft delete).

---

## Pruebas con Postman

### Importar Colección
1. Abrir Postman
2. File → Import
3. Seleccionar `postman_collection.json`
4. Configurar variables:
   - `base_url`: `http://localhost`
   - `api_key`: `test-key-12345`

### Endpoints Disponibles
- ✅ Health Check
- ✅ Upload Video
- ✅ Get Video by ID
- ✅ Get Videos by Project
- ✅ Delete Video

---

## Verificación de Logs

### Ubicación de Logs
```
app/log/
├── VideoUpload-20251211.log    ← Logs de upload
├── VideoBLL-20251211.log        ← Logs de BLL
└── VideoDAO-20251211.log        ← Logs de DAO
```

### Ejemplo de Log Exitoso
```
[tx_abc123] [video_bll] Starting video upload process
[tx_abc123] [video_dao] Video inserted successfully: ID=1
[tx_abc123] [video_bll] Video uploaded successfully: ID=1
```

### Ejemplo de Log con Error
```
[tx_xyz789] [video_bll_error] Validation error: File extension not allowed
[tx_xyz789] [video_dao_error] Insert failed: Duplicate entry
```

---

## Testing Automatizado

### Ejecutar Tests Unitarios
```bash
# Todos los tests
composer test

# Con cobertura
composer test:coverage

# Test específico
./vendor/bin/phpunit tests/Unit/VideoUploadDTOTest.php
```

### Tests Disponibles
- ✅ `VideoUploadDTOTest` - Validación de DTO
- ✅ `ApiResponseDTOTest` - Respuestas API
- ✅ `ApiAuthMiddlewareTest` - Autenticación

---

## Casos de Prueba Específicos

### Test 1: Upload con Metadata
```bash
curl -X POST http://localhost/api/videos/upload \
  -H "X-API-Key: test-key-12345" \
  -F "video=@video.mp4" \
  -F "project_id=PROJECT_001" \
  -F "video_identifier=VID_META_TEST" \
  -F 'metadata={"resolution":"1920x1080","fps":30,"duration":120,"codec":"h264"}'
```

### Test 2: Upload Duplicado (Debe Fallar)
```bash
# Primera vez - debe funcionar
curl -X POST http://localhost/api/videos/upload \
  -H "X-API-Key: test-key-12345" \
  -F "video=@video.mp4" \
  -F "project_id=PROJECT_001" \
  -F "video_identifier=VID_DUP_TEST"

# Segunda vez - debe retornar 409 Conflict
curl -X POST http://localhost/api/videos/upload \
  -H "X-API-Key: test-key-12345" \
  -F "video=@video2.mp4" \
  -F "project_id=PROJECT_001" \
  -F "video_identifier=VID_DUP_TEST"
```

**Respuesta Esperada (409):**
```json
{
  "status": {
    "code": 409,
    "message": "Video with this identifier already exists for this project"
  },
  "data": {
    "existing_video_id": 1
  }
}
```

### Test 3: Upload Archivo Grande (>500MB - Debe Fallar)
```bash
# Crear archivo de prueba de 501MB
dd if=/dev/zero of=large_video.bin bs=1M count=501

# Intentar upload
curl -X POST http://localhost/api/videos/upload \
  -H "X-API-Key: test-key-12345" \
  -F "video=@large_video.bin" \
  -F "project_id=PROJECT_001" \
  -F "video_identifier=VID_LARGE_TEST"
```

**Respuesta Esperada (400):**
```json
{
  "status": {
    "code": 400,
    "message": "File size exceeds maximum allowed (500MB)"
  },
  "data": null
}
```

### Test 4: Upload Extensión Inválida (Debe Fallar)
```bash
# Intentar subir archivo .txt
curl -X POST http://localhost/api/videos/upload \
  -H "X-API-Key: test-key-12345" \
  -F "video=@document.txt" \
  -F "project_id=PROJECT_001" \
  -F "video_identifier=VID_TXT_TEST"
```

**Respuesta Esperada (400):**
```json
{
  "status": {
    "code": 400,
    "message": "File extension not allowed. Allowed: mp4, mov, avi, wmv, webm, mkv"
  },
  "data": null
}
```

### Test 5: Rate Limiting
```bash
# Script para probar rate limiting (60 req/min)
for i in {1..65}; do
  echo "Request $i"
  curl -X GET http://localhost/api/videos/health \
    -H "X-API-Key: test-key-12345"
  sleep 0.5
done
```

**A partir del request 61, debería retornar:**
```json
{
  "status": {
    "code": 429,
    "message": "Rate limit exceeded"
  },
  "data": {
    "retry_after": 30
  }
}
```

---

## Verificación de Estructura de Archivos

```bash
# Verificar que los archivos se guardan en la estructura correcta
ls -la c:/uploads/videos/PROJECT_001/2025/12/11/VID_20251211_001/

# Debería mostrar:
# video.mp4
```

---

## Troubleshooting

### Error: "Failed to connect to database"
```bash
# Verificar conexión a BD
mysql -u root -p -e "SELECT 1"

# Verificar constantes
grep -E "USER_DB|PASS_DB|HOST_DB" config.php
```

### Error: "Failed to create directory structure"
```bash
# Verificar permisos
ls -la c:/uploads/

# Dar permisos
chmod 755 c:/uploads/videos
```

### Error: "API key not found"
```bash
# Verificar .env
cat .env | grep VALID_API_KEYS

# Verificar que se carga
php -r "require 'vendor/autoload.php'; var_dump($_ENV);"
```

---

## Checklist de Pruebas Completo

- [ ] Health check retorna 200 con "connected"
- [ ] Upload exitoso crea archivo en estructura correcta
- [ ] Upload guarda metadata en BD correctamente
- [ ] Upload duplicado retorna 409
- [ ] Upload sin API key retorna 401
- [ ] Upload con API key inválida retorna 401
- [ ] Upload archivo >500MB retorna 400
- [ ] Upload extensión inválida retorna 400
- [ ] Get by ID retorna video correcto
- [ ] Get by ID inexistente retorna 404
- [ ] Get by project retorna lista ordenada
- [ ] Get by project con paginación funciona
- [ ] Delete marca video como eliminado (soft delete)
- [ ] Rate limiting funciona después de 60 requests
- [ ] Logs se escriben correctamente
- [ ] Audit log registra acciones

---

## Scripts de Prueba Automatizada

### test_api.sh (Bash)
```bash
#!/bin/bash

API_KEY="test-key-12345"
BASE_URL="http://localhost/api/videos"

echo "🧪 Testing Video Upload API"

echo "\n1. Health Check..."
curl -s "$BASE_URL/health" | jq

echo "\n2. Upload Video..."
curl -s -X POST "$BASE_URL/upload" \
  -H "X-API-Key: $API_KEY" \
  -F "video=@test_video.mp4" \
  -F "project_id=TEST_001" \
  -F "video_identifier=VID_TEST_$(date +%s)" | jq

echo "\n3. Get Video by ID..."
curl -s "$BASE_URL/1" \
  -H "X-API-Key: $API_KEY" | jq

echo "\n4. Get Videos by Project..."
curl -s "$BASE_URL/project/TEST_001" \
  -H "X-API-Key: $API_KEY" | jq

echo "\n✅ Tests completed"
```

---

**Última actualización:** Diciembre 11, 2025  
**Autor:** SimpleData Corp  
**Versión:** 1.0.0
