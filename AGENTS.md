# AGENTS.md - Guía para Agentes de IA

## Contexto del Proyecto

Este es un proyecto de API REST segura para carga y gestión de videos desde aplicaciones móviles. La API está diseñada para ser multi-tenant, almacenando videos organizados por proyecto, fecha e identificador.

## Arquitectura

- **Patrón**: Clean Architecture con BLL/DAO/DTO
- **PHP**: 8.3.16 con tipado estricto (`declare(strict_types=1)`)
- **Base de datos**: MySQL 8.0+ con PDO y prepared statements
- **Seguridad**: OWASP Top 10 implementado
- **Testing**: PHPUnit para tests unitarios
- **CI/CD**: GitLab con pipeline DevSecOps

## Estructura de Capas

1. **DTO** (`app/DTO/`): Objetos de transferencia de datos inmutables
2. **BLL** (`app/BLL/`): Lógica de negocio, validaciones y orquestación
3. **DAO** (`app/DAO/`): Acceso a datos, solo queries con prepared statements
4. **Middleware** (`app/Middleware/`): Autenticación, rate limiting, CORS

## Convenciones de Código

### PHP
- Siempre usar `declare(strict_types=1);` al inicio de cada archivo
- Namespaces según PSR-4: `App\BLL`, `App\DAO`, `App\DTO`
- Tipado fuerte en todos los parámetros y retornos
- Usar `readonly` para propiedades inmutables en DTOs
- Sin valores hardcodeados, usar variables de entorno

### Base de Datos
- **SIEMPRE** usar prepared statements con PDO
- **NUNCA** concatenar SQL
- Especificar columnas explícitamente (no `SELECT *`)
- Usar índices en columnas de búsqueda
- Implementar soft deletes con `deleted_at`

### Seguridad
- Validar TODAS las entradas del cliente
- Sanitizar nombres de archivo (path traversal prevention)
- Validar MIME types con `finfo_file()`
- Implementar rate limiting
- Logs sin datos sensibles
- API Keys con comparación de tiempo constante

### Testing
- Tests unitarios para toda lógica de negocio
- Usar mocks para dependencias externas
- Nombrar tests descriptivamente: `testMethodName_Scenario_ExpectedResult`
- Cobertura mínima: 80%

## Patrones de Código Comunes

### DTO Inmutable
```php
declare(strict_types=1);

namespace App\DTO;

final class ExampleDTO
{
    public function __construct(
        public readonly string $field1,
        public readonly int $field2
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        // Validaciones aquí
    }
}
```

### BLL extendiendo BaseClass
```php
declare(strict_types=1);

namespace App\BLL;

use App\DAO\ExampleDAO;
use Libraries\DBConnectorPDO;
use Libraries\DayLog;

class ExampleBLL extends \App\BaseClass
{
    private ExampleDAO $dao;
    
    public function __construct(DBConnectorPDO $db)
    {
        parent::__construct($db); // Inicializa BaseClass
        $this->dao = new ExampleDAO($db);
    }
    
    public function exampleMethod($data, $operation)
    {
        $this->setOperation($operation);
        
        $tx = substr(uniqid(), 3);
        $log = new DayLog(BASE_HOME_PATH, __FUNCTION__);
        
        $this->dao->setLog($log);
        $this->dao->setTx($tx);
        
        $result = $this->dao->findById($data['id']);
        
        if ($this->dao->getError() != ERROR_CODE_OK) {
            $this->setError($this->dao->getError());
            $this->setErrorDescription($this->dao->getErrorDescription());
        } else {
            $this->setError(ERROR_CODE_OK);
            $this->setErrorDescription(ERROR_DESC_OK);
        }
        
        return $this->setBuildResponse($result);
    }
}
```

### DAO extendiendo BaseDAO
```php
declare(strict_types=1);

namespace App\DAO;

class ExampleDAO extends BaseDAO
{
    public function findById(int $id): ?array
    {
        $query = "SELECT id, name FROM table WHERE id = ? AND deleted_at IS NULL";
        $result = $this->executeSelect($query, [$id]);
        return !empty($result) ? $result[0] : null;
    }
    
    public function insert(array $data): bool|int
    {
        $query = "INSERT INTO table (name, value) VALUES (?, ?)";
        return $this->executeStatement($query, [$data['name'], $data['value']]);
    }
}
```

## Respuestas de API

Formato estándar:
```json
{
  "status": {
    "code": 200,
    "description": "Success"
  },
  "data": { }
}
```

## Comandos Útiles

```bash
# Tests
composer test
composer test:coverage

# Linting
composer lint
composer lint:fix

# Análisis estático
composer analyse

# Docker
docker-compose up -d
docker-compose logs -f app

# Migrations
mysql -u root -p < migrations/001_create_videos_table.sql
```

## Puntos Críticos de Seguridad

1. **NUNCA** confiar en datos del cliente
2. **SIEMPRE** validar entrada en el servidor
3. **SIEMPRE** usar prepared statements
4. **NUNCA** exponer stack traces al cliente
5. **SIEMPRE** sanitizar nombres de archivos
6. **SIEMPRE** validar MIME types
7. **SIEMPRE** implementar rate limiting
8. **NUNCA** hardcodear credenciales

## Enlaces de Documentación

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PSR-12](https://www.php-fig.org/psr/psr-12/)
- [PHPUnit](https://phpunit.de/)
- [PHPStan](https://phpstan.org/)

## Contacto

Para dudas sobre arquitectura o implementación, consultar el documento de arquitectura en ClickUp o contactar al equipo de desarrollo.

---

**Última actualización**: Diciembre 2025
