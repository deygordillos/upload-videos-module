# Video Upload API - Documentación

Bienvenido a la documentación del **Video Upload API**, un sistema de gestión de videos empresarial basado en Slim 4 y siguiendo el patrón arquitectónico de capas BLL/DAO de la empresa.

## Índice de Documentación

### 📘 Guías Principales

1. **[API Reference](./API_REFERENCE.md)**
   - Referencia completa de todos los endpoints
   - Ejemplos de requests y responses
   - Códigos de error y su significado
   - Estructura de almacenamiento de archivos
   - Rate limiting y seguridad

2. **[Architecture](./ARCHITECTURE.md)**
   - Stack tecnológico y decisiones de diseño
   - Estructura de directorios y componentes
   - Patrones de diseño implementados
   - Flujos de datos y arquitectura de capas
   - Esquema de base de datos
   - Consideraciones de seguridad y escalabilidad

3. **[Development Guide](./DEVELOPMENT.md)**
   - Configuración del entorno de desarrollo
   - Estándares de código (PSR-12)
   - Workflow de Git y convenciones de commits
   - Testing y debugging
   - Herramientas recomendadas
   - Troubleshooting común

4. **[Deployment Guide](./DEPLOYMENT.md)**
   - Instalación con Docker y manual
   - Configuración de producción
   - Seguridad y hardening
   - Monitoreo y logs
   - Backup y restauración
   - Escalamiento horizontal/vertical

### 📗 Guías Específicas

5. **[Testing Guide](./TESTING_GUIDE.md)**
   - Estrategia de testing (Unit, Integration)
   - Cobertura y métricas
   - Tests existentes y cómo ejecutarlos
   - Mocking y fixtures
   - CI/CD con GitHub Actions

6. **[Quick Start](./QUICKSTART.md)**
   - Inicio rápido en 5 minutos
   - Primeros pasos con la API
   - Ejemplos de uso básico

7. **[Integration Guide](./INTEGRATION_GUIDE.md)**
   - Guía para integrar la API en otros sistemas
   - Librerías cliente recomendadas
   - Manejo de errores en clientes
   - Ejemplos en diferentes lenguajes

### 📙 Referencias Técnicas

8. **[Implementation Summary](./IMPLEMENTATION_SUMMARY.md)**
   - Resumen de la implementación actual
   - Decisiones técnicas tomadas
   - Cambios principales realizados

9. **[Before/After Comparison](./BEFORE_AFTER_COMPARISON.md)**
   - Comparación del código antes y después de la refactorización
   - Métricas de mejora (líneas de código, complejidad)
   - Lecciones aprendidas

10. **[Adaptation Summary](./ADAPTATION_SUMMARY.md)**
    - Proceso de adaptación al patrón empresarial
    - Migraciones realizadas
    - Compatibilidad con sistemas existentes

11. **[Agents](./AGENTS.md)**
    - Documentación de agentes y automatizaciones
    - Workflows implementados

## Vista Rápida

### ¿Qué es este proyecto?

API REST para subir, gestionar y eliminar videos, con:

- ✅ Upload de videos hasta 100MB
- ✅ Almacenamiento organizado por proyecto/fecha/identificador
- ✅ Soft delete con audit log
- ✅ Rate limiting (60 req/min)
- ✅ Autenticación por API Key
- ✅ Validaciones MIME y extensiones
- ✅ PHPUnit tests (14 tests, 100% passing)
- ✅ PHPStan Level 5 (0 errores)

### Stack Tecnológico

- **Backend**: PHP 8.3.16 + Slim 4.15.1
- **Base de Datos**: MySQL 8.0+
- **Contenedores**: Docker + Docker Compose
- **Testing**: PHPUnit 10.5.60
- **Static Analysis**: PHPStan Level 5
- **Web Server**: Apache 2.4.62

### Instalación Rápida

```bash
# Clonar
git clone <repo-url>
cd sdc-video-upload-api

# Configurar
cp .env.example .env

# Levantar con Docker
docker-compose up -d

# Verificar
curl http://localhost:8270/v1/videos/health
```

Ver [Quick Start](./QUICKSTART.md) para más detalles.

### Estructura de Carpetas

```
sdc-video-upload-api/
├── app/                    # Código de aplicación
│   ├── BLL/               # Business Logic Layer
│   ├── DAO/               # Data Access Objects
│   ├── DTO/               # Data Transfer Objects
│   ├── Routes/            # Definición de rutas
│   ├── Middleware/        # Middlewares
│   └── Utils/             # Utilidades
├── docs/                  # 📚 Esta documentación
├── tests/                 # Tests automatizados
├── migrations/            # Migraciones de BD
├── uploads/               # Archivos subidos (git ignored)
├── docker/                # Configuración Docker
└── vendor/                # Dependencias (git ignored)
```

### Endpoints Principales

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/v1/videos/health` | Health check |
| POST | `/v1/videos/upload` | Subir video |
| GET | `/v1/videos/{id}` | Obtener video por ID |
| GET | `/v1/videos/project/{projectId}` | Listar videos de proyecto |
| DELETE | `/v1/videos/{id}` | Eliminar video (soft delete) |

Ver [API Reference](./API_REFERENCE.md) para documentación completa.

### Autenticación

Todas las rutas requieren API Key en header:

```bash
curl -H "Authorization: Bearer test-key-12345" \
     http://localhost:8270/v1/videos/health
```

### Testing

```bash
# Tests unitarios
composer test

# Análisis estático
composer phpstan

# Cobertura (requiere Xdebug)
composer test:coverage
```

Ver [Testing Guide](./TESTING_GUIDE.md) para más información.

## Navegación por Caso de Uso

### Soy desarrollador nuevo en el proyecto
1. Leer [Quick Start](./QUICKSTART.md)
2. Configurar entorno con [Development Guide](./DEVELOPMENT.md)
3. Entender arquitectura en [Architecture](./ARCHITECTURE.md)
4. Revisar estándares de código en [Development Guide](./DEVELOPMENT.md)

### Necesito integrar la API en mi aplicación
1. Leer [API Reference](./API_REFERENCE.md) para conocer endpoints
2. Seguir [Integration Guide](./INTEGRATION_GUIDE.md) para tu lenguaje
3. Importar [Postman Collection](../postman_collection.json)
4. Revisar códigos de error en [API Reference](./API_REFERENCE.md)

### Voy a desplegar a producción
1. Leer [Deployment Guide](./DEPLOYMENT.md) completo
2. Configurar variables de entorno seguras
3. Seguir checklist de seguridad
4. Configurar monitoreo y backups

### Necesito hacer cambios en el código
1. Revisar [Architecture](./ARCHITECTURE.md) para entender el diseño
2. Seguir estándares en [Development Guide](./DEVELOPMENT.md)
3. Escribir tests según [Testing Guide](./TESTING_GUIDE.md)
4. Ejecutar `composer phpstan` y `composer test` antes de commit

### Hay un bug en producción
1. Revisar logs en `app/log/` o con `docker-compose logs`
2. Consultar sección Troubleshooting en [Deployment Guide](./DEPLOYMENT.md)
3. Revisar códigos de error en [API Reference](./API_REFERENCE.md)
4. Si es DB, consultar [Architecture](./ARCHITECTURE.md) para schema

## Contribuir

### Proceso de Contribución

1. Fork del repositorio
2. Crear rama feature: `git checkout -b feature/nueva-funcionalidad`
3. Hacer cambios siguiendo [Development Guide](./DEVELOPMENT.md)
4. Escribir tests y verificar con `composer test`
5. Ejecutar `composer phpstan` (debe dar 0 errores)
6. Commit con formato [Conventional Commits](https://www.conventionalcommits.org/)
7. Push y crear Pull Request

### Estándares

- **Código**: PSR-12
- **Tests**: PHPUnit, mínimo 70% coverage
- **Static Analysis**: PHPStan Level 5 sin errores
- **Commits**: Conventional Commits
- **Documentación**: Actualizar docs/ cuando sea necesario

## Soporte

### Canales de Comunicación

- **Issues**: GitHub Issues para bugs y features
- **Discussions**: GitHub Discussions para preguntas
- **Email**: soporte@company.com para soporte empresarial

### Recursos Útiles

- [Slim Framework Docs](https://www.slimframework.com/docs/v4/)
- [PSR-12 Standard](https://www.php-fig.org/psr/psr-12/)
- [PHPUnit Manual](https://phpunit.de/documentation.html)
- [PHPStan Guide](https://phpstan.org/user-guide/getting-started)

## Changelog

### v1.0.0 (Diciembre 2025)

- ✅ Refactorización completa a patrón BLL/DAO empresarial
- ✅ Migración a DBConnectorPDO (namespace Libraries)
- ✅ Tests unitarios completos (14 tests)
- ✅ PHPStan Level 5 sin errores
- ✅ Docker con límites de upload 100MB
- ✅ Documentación completa en carpeta docs/
- ✅ Postman collection actualizada
- ✅ Audit log implementado
- ✅ Rate limiting funcional
- ✅ Soft delete con restauración

Ver [Implementation Summary](./IMPLEMENTATION_SUMMARY.md) para más detalles.

## Licencia

[Incluir licencia del proyecto]

---

**Última actualización**: Diciembre 2025  
**Versión**: 1.0  
**Mantenido por**: Team Backend

## Navegación Rápida

- [← Volver al README principal](../README.md)
- [API Reference →](./API_REFERENCE.md)
- [Development Guide →](./DEVELOPMENT.md)
- [Deployment Guide →](./DEPLOYMENT.md)
