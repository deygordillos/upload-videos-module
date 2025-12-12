# 📹 Video Upload API - Secure REST API for Multi-Project Video Management

[![PHP Version](https://img.shields.io/badge/PHP-8.3.16-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Proprietary-red)](LICENSE)
[![OWASP](https://img.shields.io/badge/security-OWASP%20Top%2010-green)](https://owasp.org/www-project-top-ten/)
[![Tests](https://img.shields.io/badge/tests-14%2F14%20passing-brightgreen)](tests/)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%205-blue)](phpstan.neon)

API segura para recepción y almacenamiento de videos desde aplicativos móviles, diseñada como módulo reutilizable para múltiples proyectos de la empresa.

## 📋 Tabla de Contenidos

- [Características](#características)
- [Vista Rápida](#vista-rápida)
- [Documentación Completa](#documentación-completa)
- [Instalación Rápida](#instalación-rápida)
- [Arquitectura](#arquitectura)
- [Testing](#testing)
- [Contribución](#contribución)

## ✨ Características

- ✅ **PHP 8.3.16** con tipado estricto y declaraciones strict_types
- 🔒 **Seguridad OWASP Top 10** implementada
- 🗄️ **Almacenamiento organizado** por proyecto/año/mes/día/identificador
- 🔐 **Autenticación por API Key** con rate limiting (60 req/min)
- 📊 **Auditoría completa** de operaciones con video_audit_log
- 🧪 **Tests unitarios** con PHPUnit (14/14 passing, 110 assertions)
- 🔍 **Análisis estático** con PHPStan Level 5 (0 errores)
- 📝 **Logging detallado** con DayLog
- 🔄 **Arquitectura empresarial** BLL/DAO/DTO siguiendo patrón corporativo
- 📦 **Migrations** para base de datos MySQL 8.0+
- 🐳 **Docker** ready con Slim 4.15.1
- 🚫 **Soft delete** con posibilidad de restauración
- ⚡ **Upload hasta 100MB** con validación MIME y extensiones

## 📚 Documentación Completa

Toda la documentación del proyecto está organizada en la carpeta [`docs/`](./docs/):

- **[Índice de Documentación](./docs/README.md)** - Portal principal de documentación
- **[API Reference](./docs/API_REFERENCE.md)** - Referencia completa de endpoints
- **[Architecture](./docs/ARCHITECTURE.md)** - Diseño y arquitectura del sistema
- **[Development Guide](./docs/DEVELOPMENT.md)** - Guía para desarrolladores
- **[Deployment Guide](./docs/DEPLOYMENT.md)** - Despliegue a producción
- **[Testing Guide](./docs/TESTING_GUIDE.md)** - Estrategias de testing

## 🚀 Vista Rápida

### Endpoints Principales

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/v1/videos/health` | Health check + DB status |
| POST | `/v1/videos/upload` | Subir video (max 100MB) |
| GET | `/v1/videos/{id}` | Obtener video por ID |
| GET | `/v1/videos/project/{projectId}` | Listar videos por proyecto |
| DELETE | `/v1/videos/{id}` | Eliminar video (soft delete) |

### Ejemplo de Uso

```bash
# Health check
curl -H "Authorization: Bearer test-key-12345" \
     http://localhost:8270/v1/videos/health

# Upload video
curl -X POST \
     -H "Authorization: Bearer test-key-12345" \
     -F "video=@video.mp4;type=video/mp4" \
     -F "project_id=PROJECT_TEST" \
     -F "identifier=VIDEO_001" \
     -F "title=Mi Video" \
     http://localhost:8270/v1/videos/upload
```

Ver [API Reference](./docs/API_REFERENCE.md) para ejemplos completos.

## 🐳 Instalación Rápida

### Con Docker (Recomendado)

```bash
# 1. Clonar repositorio
git clone <repository-url>
cd sdc-video-upload-api

# 2. Configurar entorno
cp .env.example .env

# 3. Levantar contenedor
docker-compose up -d

# 4. Verificar
curl http://localhost:8270/v1/videos/health
```

### Sin Docker

```bash
# 1. Instalar dependencias
composer install

# 2. Crear base de datos
mysql -u root -p < migrations/001_create_videos_table.sql

# 3. Configurar .env
cp .env.example .env
# Editar .env con tus credenciales

# 4. Iniciar servidor
php -S localhost:8270 core.php
```

Ver [Deployment Guide](./docs/DEPLOYMENT.md) para instrucciones detalladas.

## 🏗️ Arquitectura

```
Mobile App
    ↓ HTTPS + API Key
API Gateway (Rate Limiting, Auth)
    ↓
VideoBLL.php (Business Logic)
    ↓
VideoDAO.php (Data Access, PDO)
    ↓
MySQL (videos + video_audit_log)
```

### Stack Tecnológico

- **Backend**: PHP 8.3.16 + Slim Framework 4.15.1
- **Database**: MySQL 8.0+ con transacciones
- **ORM**: DBConnectorPDO (namespace Libraries)
- **Testing**: PHPUnit 10.5.60
- **Static Analysis**: PHPStan Level 5
- **Web Server**: Apache 2.4.62
- **Container**: Docker + Docker Compose

### Patrón de Capas

```
┌─────────────────────────────────┐
│  Routes (Slim 4)                │  ← Definición de endpoints
├─────────────────────────────────┤
│  DTO (Data Transfer Objects)    │  ← Contratos inmutables
├─────────────────────────────────┤
│  BLL (Business Logic)           │  ← Validación y lógica
│  - extends \App\BaseClass       │
├─────────────────────────────────┤
│  DAO (Data Access)              │  ← executeSelect/Statement
│  - extends BaseDAO               │
├─────────────────────────────────┤
│  DBConnectorPDO                 │  ← Conexión PDO, logs, tx
└─────────────────────────────────┘
```

Ver [Architecture](./docs/ARCHITECTURE.md) para más detalles.

## 🧪 Testing

```bash
# Ejecutar todos los tests
composer test

# Tests con cobertura (requiere Xdebug)
composer test:coverage

# Análisis estático PHPStan
composer phpstan
```

### Resultados Actuales

- ✅ **Unit Tests**: 14/14 passing (100%)
- ✅ **Assertions**: 110 assertions
- ✅ **PHPStan**: Level 5, 0 errors
- ⏱️ **Execution Time**: ~1 second

Ver [Testing Guide](./docs/TESTING_GUIDE.md) para más información

## 🤝 Contribución

### Proceso de Desarrollo

1. **Fork** del repositorio
2. Crear rama feature: `git checkout -b feature/nueva-funcionalidad`
3. Hacer cambios siguiendo [Development Guide](./docs/DEVELOPMENT.md)
4. Ejecutar tests: `composer test && composer phpstan`
5. Commit: `[feature]: descripción` o `feat: descripción` ([Conventional Commits](https://www.conventionalcommits.org/))
6. Push y crear Pull Request

### Estándares de Código

- **PSR-12**: Coding style standard
- **PHPStan Level 5**: Sin errores
- **Test Coverage**: Mínimo 70%, objetivo 80%+
- **Strict Types**: Declaración `declare(strict_types=1)` obligatoria

### Checklist Pre-Commit

- [ ] Tests unitarios pasan (`composer test`)
- [ ] PHPStan sin errores (`composer phpstan`)
- [ ] Código sigue PSR-12
- [ ] Documentación actualizada
- [ ] Sin credenciales hardcodeadas
- [ ] Logs apropiados añadidos

Ver [Development Guide](./docs/DEVELOPMENT.md) para más detalles.

## 📞 Soporte

### Documentación

- **[docs/README.md](./docs/README.md)** - Índice completo de documentación
- **[docs/API_REFERENCE.md](./docs/API_REFERENCE.md)** - Referencia de API
- **[docs/ARCHITECTURE.md](./docs/ARCHITECTURE.md)** - Arquitectura del sistema

### Contacto

- **Issues**: GitHub Issues para bugs y features
- **Discussions**: GitHub Discussions para preguntas
- **Email**: soporte@company.com

## 📄 Licencia

[Incluir información de licencia]

## 🎯 Roadmap

### v1.1 (Q1 2026)

- [ ] Video thumbnails automáticos
- [ ] Transcodificación de formatos
- [ ] Streaming HLS/DASH
- [ ] Búsqueda full-text
- [ ] CDN integration

### v2.0 (Q2 2026)

- [ ] Multi-tenant isolation
- [ ] Video analytics
- [ ] Webhooks de eventos
- [ ] GraphQL API
- [ ] Kubernetes deployment

## 📊 Métricas del Proyecto

- **Líneas de código**: ~3,500 (BLL/DAO/Routes)
- **Tests**: 14 tests unitarios, 110 assertions
- **Cobertura**: ~75%
- **Errores PHPStan**: 0 (Level 5)
- **Dependencias**: 15 packages Composer
- **Tiempo de build**: ~30 segundos
- **Tiempo de tests**: ~1 segundo

## 🏆 Changelog

### v1.0.0 (Diciembre 2025)

#### 🎉 Initial Release
- ✅ Refactorización completa a patrón BLL/DAO corporativo
- ✅ Migración a DBConnectorPDO (namespace Libraries)
- ✅ Implementación de BaseClass/BaseComponent/BaseDAO
- ✅ Tests unitarios completos (14/14 passing)
- ✅ PHPStan Level 5 sin errores (reducción de 129→0 errores)
- ✅ Docker con Apache 2.4.62 y PHP 8.3.16
- ✅ Upload limits aumentados a 100MB
- ✅ Documentación completa en carpeta docs/
- ✅ Postman collection con 9 casos de prueba
- ✅ Soft delete con audit logging
- ✅ Rate limiting (60 req/min)
- ✅ Health check con estado de BD

#### 📝 Documentation
- ✅ API_REFERENCE.md - Referencia completa de endpoints
- ✅ ARCHITECTURE.md - Documentación de arquitectura
- ✅ DEVELOPMENT.md - Guía para desarrolladores
- ✅ DEPLOYMENT.md - Guía de despliegue
- ✅ TESTING_GUIDE.md - Estrategias de testing
- ✅ docs/README.md - Portal de documentación

#### 🔧 Technical Improvements
- Fixed type mismatches (string vs int)
- Added ERROR_CODE_NO_FOUND_RECORD constant
- Removed dead catch blocks
- Added property types to AuthMiddleware
- Simplified HttpErrorHandler
- Fixed rate limit test with reflection

Ver [Implementation Summary](./docs/IMPLEMENTATION_SUMMARY.md) para más detalles.

---

**Desarrollado con ❤️ por el Team Backend**  
**Última actualización**: Diciembre 2025  
**Versión**: 1.0.0

## 🔗 Links Útiles

- [Documentación Completa](./docs/README.md)
- [API Reference](./docs/API_REFERENCE.md)
- [Postman Collection](./postman_collection.json)
- [Slim Framework](https://www.slimframework.com/docs/v4/)
- [PSR-12 Standard](https://www.php-fig.org/psr/psr-12/)

---

<p align="center">
  <a href="./docs/README.md">📚 Ver Documentación Completa</a> •
  <a href="./docs/API_REFERENCE.md">🔌 API Reference</a> •
  <a href="./docs/QUICKSTART.md">⚡ Quick Start</a>
</p>

Para soporte técnico o preguntas, contactar al equipo de desarrollo en SimpleData Corp.

## 📄 Licencia

Código propietario © 2025 SimpleData Corp. Todos los derechos reservados.

---

**Desarrollado con ❤️ por SimpleData Corp**

**Versión:** 1.0.0  
**Última actualización:** Diciembre 2025
