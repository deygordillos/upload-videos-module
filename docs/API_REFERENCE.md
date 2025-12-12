# API Reference - Video Upload API

## Información General

- **Versión**: 1.0.0
- **Base URL**: `http://localhost:8270/v1/videos`
- **Autenticación**: API Key (Header `X-API-Key`)
- **Rate Limit**: 60 requests/minuto por API key
- **Formato**: JSON

## Endpoints

### 1. Health Check

Verifica el estado del servicio y la conexión a la base de datos.

```http
GET /v1/videos/health
```

**Headers**
```
X-API-Key: your-api-key-1
```

**Response 200 OK**
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
    "timestamp": "2025-12-11 18:35:32"
  }
}
```

---

### 2. Upload Video

Sube un archivo de video con metadatos asociados.

```http
POST /v1/videos/upload
```

**Headers**
```
X-API-Key: your-api-key-1
Content-Type: multipart/form-data
```

**Body (multipart/form-data)**
| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `video` | file | Sí | Archivo de video (max 500MB) |
| `project_id` | string | Sí | Identificador del proyecto |
| `video_identifier` | string | No | Identificador único del video (generado automáticamente si no se proporciona) |
| `metadata` | string | No | JSON con metadatos adicionales |

**Tipos MIME aceptados**
- `video/mp4`
- `video/quicktime` (mov)
- `video/x-msvideo` (avi)
- `video/x-ms-wmv` (wmv)
- `video/webm`
- `video/x-matroska` (mkv)

**Response 201 Created**
```json
{
  "status": {
    "code": 201,
    "description": "Video uploaded successfully"
  },
  "data": {
    "id": 1,
    "project_id": "PROJECT_TEST",
    "video_identifier": "VIDEO_1734567890_a3f2b8c1",
    "original_filename": "videotest_20251211.mp4",
    "file_path": "PROJECT_TEST/2025/12/11/VIDEO_1734567890_a3f2b8c1_videotest_20251211.mp4",
    "file_size": 6990444,
    "mime_type": "video/mp4",
    "duration": null,
    "width": null,
    "height": null,
    "metadata": null,
    "status": "completed",
    "created_at": "2025-12-11 18:51:49",
    "updated_at": "2025-12-11 18:51:49"
  }
}

**Nota sobre videoIdentifier**: 
- Si no se proporciona `video_identifier` en el request, el backend genera automáticamente uno con el formato: `VIDEO_{timestamp}_{randomhex}`
- Ejemplo: `VIDEO_1734567890_a3f2b8c1`
- La estructura de almacenamiento es: `{project_id}/{año}/{mes}/{día}/{video_identifier}_{filename}`
```

**Response 400 Bad Request**
```json
{
  "status": {
    "code": 400,
    "description": "project_id is required"
  },
  "data": null
}
```

**Response 409 Conflict**
```json
{
  "status": {
    "code": 409,
    "description": "Video with this identifier already exists"
  },
  "data": null
}
```

---

### 3. Get Video by ID

Obtiene los detalles de un video específico por su ID.

```http
GET /v1/videos/{id}
```

**Path Parameters**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `id` | integer | ID del video |

**Headers**
```
X-API-Key: your-api-key-1
```

**Response 200 OK**
```json
{
  "status": {
    "code": 200,
    "description": "Success"
  },
  "data": {
    "id": 1,
    "project_id": "PROJECT_TEST",
    "video_identifier": "VIDEO_TEST_001",
    "original_filename": "videotest_20251211.mp4",
    "file_path": "PROJECT_TEST/2025/12/11/VIDEO_TEST_001/videotest_20251211.mp4",
    "file_size": 6990444,
    "mime_type": "video/mp4",
    "duration": null,
    "width": null,
    "height": null,
    "metadata": null,
    "status": "completed",
    "created_at": "2025-12-11 18:51:49",
    "updated_at": "2025-12-11 18:51:49"
  }
}
```

**Response 404 Not Found**
```json
{
  "status": {
    "code": 404,
    "description": "Video not found"
  },
  "data": null
}
```

---

### 4. Get Videos by Project

Lista videos de un proyecto con paginación.

```http
GET /v1/videos/project/{project_id}?page={page}&per_page={per_page}
```

**Path Parameters**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `project_id` | string | Identificador del proyecto |

**Query Parameters**
| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `page` | integer | 1 | Número de página |
| `per_page` | integer | 10 | Videos por página (max 100) |

**Headers**
```
X-API-Key: your-api-key-1
```

**Response 200 OK**
```json
{
  "status": {
    "code": 200,
    "description": "Success"
  },
  "data": {
    "videos": [
      {
        "id": 1,
        "project_id": "PROJECT_TEST",
        "video_identifier": "VIDEO_TEST_001",
        "original_filename": "videotest_20251211.mp4",
        "file_path": "PROJECT_TEST/2025/12/11/VIDEO_TEST_001/videotest_20251211.mp4",
        "file_size": 6990444,
        "mime_type": "video/mp4",
        "duration": null,
        "width": null,
        "height": null,
        "metadata": null,
        "status": "completed",
        "created_at": "2025-12-11 18:51:49",
        "updated_at": "2025-12-11 18:51:49"
      }
    ],
    "pagination": {
      "page": 1,
      "per_page": 10,
      "count": 1
    }
  }
}
```

---

### 5. Delete Video

Elimina un video (soft delete).

```http
DELETE /v1/videos/{id}
```

**Path Parameters**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `id` | integer | ID del video |

**Headers**
```
X-API-Key: your-api-key-1
```

**Response 200 OK**
```json
{
  "status": {
    "code": 200,
    "description": "Video deleted successfully"
  },
  "data": null
}
```

**Response 404 Not Found**
```json
{
  "status": {
    "code": 404,
    "description": "Video not found"
  },
  "data": null
}
```

---

## Códigos de Error

| Código | Descripción | Causa Común |
|--------|-------------|-------------|
| 400 | Bad Request | Parámetros faltantes o inválidos |
| 401 | Unauthorized | API key inválida o faltante |
| 404 | Not Found | Recurso no encontrado |
| 409 | Conflict | Video duplicado (mismo project_id + video_identifier) |
| 413 | Payload Too Large | Archivo mayor a 100MB |
| 415 | Unsupported Media Type | Tipo MIME no soportado |
| 429 | Too Many Requests | Rate limit excedido |
| 500 | Internal Server Error | Error del servidor |

---

## Estructura de Almacenamiento

Los videos se almacenan siguiendo esta estructura jerárquica:

```
uploads/
└── {project_id}/              ← Identificador del proyecto
    └── {YYYY}/                ← Año de subida
        └── {MM}/              ← Mes de subida
            └── {DD}/          ← Día de subida
                └── {video_identifier}/    ← Identificador único del video
                    └── {original_filename}  ← Archivo original
```

**Ejemplo:**
```
uploads/PROJECT_TEST/2025/12/11/VIDEO_TEST_001/videotest_20251211.mp4
```

**Ventajas:**
- ✅ Escalabilidad: Soporta millones de videos sin saturar directorios
- ✅ Multi-tenant: Cada proyecto aislado
- ✅ Trazabilidad: La fecha está en la ruta física
- ✅ Duplicados: `video_identifier` previene sobrescrituras
- ✅ Backup: Fácil respaldar por proyecto o rango de fechas

---

## Rate Limiting

- **Límite**: 60 requests por minuto por API key
- **Ventana**: Deslizante de 60 segundos
- **Header de respuesta 429**:
  ```json
  {
    "status": {
      "code": 429,
      "description": "Rate limit exceeded. Try again later."
    },
    "data": null
  }
  ```

---

## Auditoría

Todas las operaciones se registran en la tabla `video_audit_log`:

| Campo | Descripción |
|-------|-------------|
| `video_id` | ID del video afectado |
| `action` | Acción realizada (upload, update, delete) |
| `user_id` | ID del usuario (si aplica) |
| `ip_address` | IP del cliente |
| `details` | Detalles adicionales en JSON |
| `created_at` | Timestamp de la acción |

---

## Ejemplos de Uso

### Ejemplo con cURL (Windows PowerShell)

**Upload de video:**
```powershell
curl.exe -X POST "http://localhost:8270/v1/videos/upload" `
  -H "X-API-Key: your-api-key-1" `
  -F "video=@C:\path\to\video.mp4;type=video/mp4" `
  -F "project_id=PROJECT_TEST" `
  -F "video_identifier=VIDEO_001"
```

**Obtener video:**
```powershell
curl.exe -X GET "http://localhost:8270/v1/videos/1" `
  -H "X-API-Key: your-api-key-1"
```

**Listar videos:**
```powershell
curl.exe -X GET "http://localhost:8270/v1/videos/project/PROJECT_TEST?page=1&per_page=10" `
  -H "X-API-Key: your-api-key-1"
```

**Eliminar video:**
```powershell
curl.exe -X DELETE "http://localhost:8270/v1/videos/1" `
  -H "X-API-Key: your-api-key-1"
```

### Ejemplo con Postman

Importa la colección desde `postman_collection.json` que incluye:
- ✅ 9 casos de prueba predefinidos
- ✅ Variables de entorno configuradas
- ✅ Casos de error incluidos

---

## Configuración

### Variables de Entorno (.env)

```env
# Base de datos
BDD_HOST=host.docker.internal
BDD_USER=sdc
BDD_PASS=your-password
BDD_PORT=3306
BDD_SCHEMA=sdc_videos

# Aplicación
APP_ENV=development
APP_DEBUG=true
APP_PATH=/

# Seguridad
VALID_API_KEYS=your-api-key-1,your-api-key-2,your-api-key-3

# Upload
UPLOAD_PATH=./uploads

# Logs
LOG_LEVEL=info
LOG_PATH=./app/log
```

---

## Límites y Restricciones

| Recurso | Límite |
|---------|--------|
| Tamaño máximo de archivo | 100 MB |
| Rate limit | 60 req/min |
| Longitud `project_id` | 50 caracteres |
| Longitud `video_identifier` | 100 caracteres |
| Longitud `original_filename` | 255 caracteres |
| Videos por página (max) | 100 |

---

## Soporte

Para reportar issues o solicitar features, contactar al equipo de desarrollo.

**Última actualización**: Diciembre 2025
