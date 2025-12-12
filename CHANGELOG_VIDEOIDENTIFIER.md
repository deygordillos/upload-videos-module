# Changelog: VideoIdentifier Auto-generation

## Fecha: 2025-12-11

### Cambios Implementados

#### 1. VideoUploadDTO (app/DTO/VideoUploadDTO.php)
- ✅ **Cambio**: `videoIdentifier` es ahora OPCIONAL (nullable: `?string $videoIdentifier = null`)
- ✅ **Validación**: Se permite `null`, solo valida longitud si se proporciona un valor
- ✅ **Método estático**: `fromUploadedFile()` acepta `videoIdentifier` opcional
- ✅ **Otros parámetros opcionales**: También se hicieron opcionales `originalFilename`, `tmpFilePath`, `fileSize`, `mimeType` para mayor flexibilidad

#### 2. VideoBLL (app/BLL/VideoBLL.php)
- ✅ **Auto-generación**: Si `videoIdentifier` es `null`, se genera automáticamente con formato: `VIDEO_{timestamp}_{randomhex}`
  - Ejemplo: `VIDEO_1734567890_a3f2b8c1`
- ✅ **Nueva función**: `generateVideoIdentifier()` - Genera ID único con `time()` y `bin2hex(random_bytes(4))`
- ✅ **Estructura simplificada**: Cambio de path de almacenamiento
  - **Antes**: `uploads/PROJECT_ID/YYYY/MM/DD/VIDEO_IDENTIFIER/filename.mp4`
  - **Después**: `uploads/PROJECT_ID/YYYY/MM/DD/VIDEO_IDENTIFIER_filename.mp4`
- ✅ **Actualización de DTO**: Se crea un nuevo `VideoUploadDTO` con el `videoIdentifier` generado antes de insertar en BD

#### 3. Tests de Integración (tests/Integration/VideoBLLIntegrationTest.php)
- ✅ **testUploadRealVideoFile**: Cambio a `videoIdentifier: null` para probar generación automática
- ✅ **Nuevas assertions**: 
  - Verifica que `video_identifier` existe en la respuesta
  - Valida formato con regex: `/^VIDEO_\d+_[a-f0-9]{8}$/`
- ✅ **testUploadVideoWithInvalidMimeType**: Actualizado a `videoIdentifier: null`
- ✅ **testUploadVideoWithMissingRequiredFields**: Cambio de test - ahora valida `originalFilename` vacío en lugar de `videoIdentifier`

#### 4. Tests Unitarios (tests/Unit/VideoUploadDTOTest.php)
- ✅ **Nuevo test**: `testVideoUploadDTOWithNullIdentifier()` - Verifica que `null` es aceptado
- ✅ **Nuevo test**: `testFromUploadedFileWithoutVideoIdentifier()` - Valida método estático con `videoIdentifier: null`
- ✅ **Actualización**: Todos los tests de validación ahora usan `videoIdentifier: null`

#### 5. Documentación (docs/API_REFERENCE.md)
- ✅ **Campo video_identifier**: Cambiado de "Requerido: Sí" a "Requerido: No"
- ✅ **Descripción actualizada**: "Identificador único del video (generado automáticamente si no se proporciona)"
- ✅ **Límite de tamaño**: Corregido de 100MB a 500MB (según validación)
- ✅ **Ejemplo de respuesta**: Actualizado con nuevo formato de `video_identifier` y `file_path`
- ✅ **Nota adicional**: Explicación del formato de generación automática y estructura de almacenamiento

### Beneficios de los Cambios

1. **Simplicidad del Cliente**: Las apps móviles ya no necesitan generar ni enviar `videoIdentifier`
2. **Backend como fuente de verdad**: El servidor controla la generación de identificadores únicos
3. **Unicidad garantizada**: Timestamp + random bytes asegura IDs únicos sin colisiones
4. **Estructura simplificada**: Eliminación de carpetas anidadas innecesarias
5. **Retrocompatibilidad**: Los clientes que envíen `videoIdentifier` seguirán funcionando

### Estructura de Almacenamiento

**Antes**:
```
uploads/
  PROJECT_ID/
    2025/
      12/
        11/
          VIDEO_IDENTIFIER/
            filename.mp4
```

**Después**:
```
uploads/
  PROJECT_ID/
    2025/
      12/
        11/
          VIDEO_1734567890_a3f2b8c1_filename.mp4
```

### Tests Actualizados

**Total de tests**: 17 (14 unitarios + 3 integración)

- ✅ `testVideoUploadDTOWithNullIdentifier` (nuevo)
- ✅ `testFromUploadedFileWithoutVideoIdentifier` (nuevo)
- ✅ `testUploadRealVideoFile` (modificado - verifica generación automática)
- ✅ `testInvalidProjectIdThrowsException` (actualizado)
- ✅ `testFileSizeTooLargeThrowsException` (actualizado)
- ✅ `testInvalidMimeTypeThrowsException` (actualizado)
- ✅ `testUploadVideoWithInvalidMimeType` (actualizado)
- ✅ `testUploadVideoWithMissingRequiredFields` (modificado - ahora valida filename)

### Próximos Pasos

1. ✅ Commit de cambios
2. ⏳ Push a GitLab
3. ⏳ Verificar que CI/CD pase todos los tests (17/17)
4. ⏳ Validar en ambiente de desarrollo/staging
5. ⏳ Comunicar cambio a equipos de desarrollo móvil

### Notas Técnicas

- **PHP Version**: Requiere PHP 8.1+ por uso de readonly properties y named arguments
- **Compatibilidad**: PHP 7.4 no soporta esta sintaxis (esperado en CI/CD con PHP 8.3)
- **GitLab CI/CD**: Configurado con `php:8.3-cli` para integration tests
- **Formato videoIdentifier**: `VIDEO_` + timestamp Unix (10 dígitos) + `_` + 8 caracteres hexadecimales

### Ejemplo de Request

**Antes (videoIdentifier requerido)**:
```bash
curl -X POST http://localhost:8270/v1/videos/upload \
  -H "X-API-Key: your-api-key-1" \
  -F "video=@videotest.mp4" \
  -F "project_id=PROJECT_TEST" \
  -F "video_identifier=VIDEO_TEST_001"
```

**Después (videoIdentifier opcional)**:
```bash
curl -X POST http://localhost:8270/v1/videos/upload \
  -H "X-API-Key: your-api-key-1" \
  -F "video=@videotest.mp4" \
  -F "project_id=PROJECT_TEST"
```

### Validaciones en CI/CD

Los tests se ejecutarán automáticamente en GitLab CI/CD:

```bash
# Unit Tests (composer:latest)
composer test:unit

# Integration Tests (php:8.3-cli)
composer test:integration
```

Ambos jobs deben pasar para confirmar que la implementación es correcta.
