<?php
namespace Libraries;

use Libraries\DayLog;

/**
 * Clase para gestionar conexiones a la base de datos con inyección de dependencias.
 * Utiliza PDO para una gestión de errores y seguridad mejoradas.
 * Incluye logging detallado, manejo de transacciones y métricas de performance.
 * 
 * @version 2.0.0
 * @author Dey Gordillo <dey.gordillo@simpledatacorp.com>
 */
class DBConnectorPDO
{
    // Constantes de error
    const ERROR_CODE_OK = '0';
    const ERROR_DESC_OK = 'OK';
    const ERROR_CODE_CONSTRUCTOR_PARAMETERS = '100';
    const ERROR_DESC_CONSTRUCTOR_PARAMETERS = 'Invalid parameters in constructor, it will not connect to database.';
    const ERROR_DESC_OK_DISCONNECTED = 'Disconnect success.';
    const FLAG_DISCONNECTED = true;
    const PDO_DEFAULT_PORT = 3306;

    // Propiedades de conexión
    private string $szUser;
    private string $szPass;
    private string $szIpAddress;
    private string $szSchema;
    private int $nPort;
    
    // Propiedades de estado
    private string $nError = self::ERROR_CODE_OK;
    private string $szErrorDescription = '';
    private ?\PDO $pdo = null;
    private bool $bFlagDisconnected = self::FLAG_DISCONNECTED;
    private int $nAffectedRows = 0;
    private int $nNumRows = 0;
    
    // Propiedades de logging y transacciones
    private ?DayLog $log = null;
    private string $tx = '';
    
    // Métricas de performance
    private array $queryMetrics = [];
    private int $connectionCount = 0;
    
    /**
     * @var bool Modo debug para logging detallado
     */
    private bool $debugMode = false;

    /**
     * Constructor mejorado con validaciones y logging
     * 
     * @param string|null $szUser Usuario de base de datos
     * @param string|null $szPass Contraseña de base de datos
     * @param string|null $szIpAddress Host de base de datos
     * @param int $nPort Puerto de base de datos
     * @param string|null $szSchema Esquema de base de datos
     * @param bool $debugMode Habilitar modo debug para logging detallado
     */
    public function __construct(
        ?string $szUser = null, 
        ?string $szPass = null, 
        ?string $szIpAddress = null, 
        int $nPort = self::PDO_DEFAULT_PORT, 
        ?string $szSchema = null,
        bool $debugMode = false
    ) {
        // Inicializar logging
        $this->log = new DayLog(BASE_HOME_PATH, 'DBConnectorPDO');
        $this->debugMode = $debugMode;
        
        // Validar parámetros de construcción
        if (empty($szUser) || empty($szPass) || empty($szIpAddress) || empty($szSchema)) {
            $this->setError(self::ERROR_CODE_CONSTRUCTOR_PARAMETERS);
            $this->setErrorDescription(self::ERROR_DESC_CONSTRUCTOR_PARAMETERS);
            $this->bFlagDisconnected = self::FLAG_DISCONNECTED;
            
            $this->log->writeLog("[constructor] Error: Parámetros inválidos en constructor\n");
        } else {
            $this->szUser = $szUser;
            $this->szPass = $szPass;
            $this->szIpAddress = $szIpAddress;
            $this->nPort = $nPort;
            $this->szSchema = $szSchema;
        }
    }

    /**
     * Destructor mejorado con logging de métricas finales
     */
    public function __destruct()
    {
        // Log de métricas finales antes de cerrar
        if (!empty($this->queryMetrics)) {
            $stats = $this->getPerformanceStats();
            $this->log->writeLog("{$this->tx} [destructor] Estadísticas finales: {$stats['total_queries']} queries, {$stats['total_time']}ms total, {$stats['avg_time']}ms promedio\n");
        }
        
        $this->closeConnection();
    }

    /**
     * Abre la conexión a la base de datos con configuración optimizada
     * @return \PDO|null
     */
    public function openConnection(): ?\PDO
    {
        if ($this->pdo === null) {
            $startTime = microtime(true);
            
            try {
                $dsn = "mysql:host={$this->szIpAddress};dbname={$this->szSchema};port={$this->nPort};charset=utf8mb4";
                
                // Configuración optimizada de PDO
                $options = [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::ATTR_STRINGIFY_FETCHES => false,
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci",
                    \PDO::ATTR_TIMEOUT => 30,
                    \PDO::ATTR_PERSISTENT => false
                ];
                
                $this->pdo = new \PDO($dsn, $this->szUser, $this->szPass, $options);
                
                $this->connectionCount++;
                $connectionTime = round((microtime(true) - $startTime) * 1000, 2);
                
                $this->setError(self::ERROR_CODE_OK);
                $this->setErrorDescription(self::ERROR_DESC_OK);
                $this->bFlagDisconnected = false;
                
                $this->log->writeLog("{$this->tx} [db_open] Conexión exitosa en {$connectionTime}ms (conexión #{$this->connectionCount})\n");
                
            } catch (\PDOException $e) {
                $connectionTime = round((microtime(true) - $startTime) * 1000, 2);
                
                $this->setError($e->getCode());
                $this->setErrorDescription($e->getMessage());
                $this->bFlagDisconnected = self::FLAG_DISCONNECTED;
                
                $this->log->writeLog("{$this->tx} [db_error] Error de conexión después de {$connectionTime}ms: " . $e->getMessage() . "\n");
                
                // En producción, no mostrar detalles sensibles
                if (!$this->debugMode) {
                    $this->setErrorDescription("Error de conexión a la base de datos");
                }
            }
        }
        
        return $this->pdo;
    }

    /**
     * Cierra la conexión a la base de datos y limpia recursos
     */
    public function closeConnection(): void
    {
        if ($this->pdo) {
            try {
                // Si hay una transacción activa, hacer rollback
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollback();
                    $this->log->writeLog("{$this->tx} [db_close] Rollback de transacción activa antes del cierre\n");
                }
                
                $this->pdo = null;
                $this->setError(self::ERROR_CODE_OK);
                $this->setErrorDescription(self::ERROR_DESC_OK_DISCONNECTED);
                $this->bFlagDisconnected = self::FLAG_DISCONNECTED;
                
                // Log de métricas finales
                $totalQueries = count($this->queryMetrics);
                if ($totalQueries > 0) {
                    $avgTime = array_sum(array_column($this->queryMetrics, 'time')) / $totalQueries;
                    $this->log->writeLog("{$this->tx} [db_close] Conexión cerrada. Queries ejecutados: $totalQueries, tiempo promedio: " . round($avgTime, 2) . "ms\n");
                } else {
                    $this->log->writeLog("{$this->tx} [db_close] Conexión cerrada sin queries ejecutados\n");
                }
                
                // Limpiar métricas
                $this->queryMetrics = [];
                
            } catch (\Throwable $e) {
                $this->log->writeLog("{$this->tx} [db_close] Error al cerrar conexión: " . $e->getMessage() . "\n");
            }
        }
    }

    /**
     * Método mejorado para consultas INSERT, UPDATE y DELETE
     * Incluye logging detallado, métricas y manejo de transacciones
     * 
     * @param string $szQuery Consulta a realizar en BD
     * @param array $params Parámetros para la consulta
     * @return bool|int true si la operación fue exitosa, o lastInsertId para INSERTs
     */
    public function executeStmt(string $szQuery, array $params = []): bool|int
    {
        $result = false;
        $startTime = microtime(true);
        $this->openConnection();
        
        if (!$this->pdo) {
            $this->log->writeLog("{$this->tx} [db_error] No hay conexión disponible para executeStmt\n");
            return false;
        }
        
        $autoTransaction = !$this->pdo->inTransaction();
        
        try {
            // Iniciar transacción automática si no hay una activa
            if ($autoTransaction) {
                $this->pdo->beginTransaction();
            }
            
            $this->log->writeLog("{$this->tx} [db_query] " . str_replace(["\n", "\r", "\t"], " ", $szQuery) . " | [db_params] " . json_encode($params) . "\n");
            
            $stmt = $this->pdo->prepare($szQuery);
            $stmt->execute($params);
            $this->setAffectedRows($stmt->rowCount());
            
            // Detectar si es un INSERT y obtener el ID ANTES del commit
            $isInsert = stripos(trim($szQuery), 'INSERT') === 0;
            $insertId = 0;
            
            if ($isInsert) {
                $insertId = $this->getLastInsertId();
            }
            
            // Confirmar transacción automática DESPUÉS de obtener el lastInsertId
            if ($autoTransaction) {
                $this->pdo->commit();
            }
            
            $result = $isInsert ? $insertId : true;

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);
            
            if ($isInsert) {
                $this->log->writeLog("{$this->tx} [db_response] INSERT - lastInsertId: {$insertId}, affected_rows: {$this->nAffectedRows}, time: {$executionTime}ms\n");
            } else {
                $this->log->writeLog("{$this->tx} [db_response] affected_rows: {$this->nAffectedRows}, time: {$executionTime}ms\n");
            }
            // Guardar métricas
            $this->queryMetrics[] = [
                'type' => $isInsert ? 'INSERT' : 'STMT',
                'time' => $executionTime,
                'affected_rows' => $this->nAffectedRows,
                'insert_id' => $isInsert ? $insertId : null
            ];
            
            $this->setError(self::ERROR_CODE_OK);
            $this->setErrorDescription(self::ERROR_DESC_OK);
            
        } catch (\PDOException $e) {
            // Rollback de transacción automática en caso de error
            if ($autoTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->setError($e->getCode());
            $this->setErrorDescription($e->getMessage());
            
            $this->log->writeLog("{$this->tx} [db_error] Error después de {$executionTime}ms: " . $e->getMessage() . "\n");
            
            // En producción, no mostrar detalles sensibles
            if (!$this->debugMode) {
                $this->setErrorDescription("Error en la ejecución de la consulta");
            }
        }
        
        return $result;
    }

    /**
     * Método para consultas SELECT que retorna arrays mixtos (compatibilidad legacy)
     * @deprecated Usar executeStmtResultAssoc() para mejor performance y consistencia
     * @param string $szQuery Consulta SELECT a realizar
     * @param array $params Parámetros para la consulta
     * @return array Resultados como array mixto (numérico y asociativo)
     */
    public function executeStmtResult(string $szQuery, array $params = []): array
    {
        $arrayData = [];
        $startTime = microtime(true);
        $this->openConnection();
        
        if (!$this->pdo) {
            $this->log->writeLog("{$this->tx} [db_error] No hay conexión disponible para executeStmtResult\n");
            return [];
        }
        
        try {
            $this->log->writeLog("{$this->tx} [db_query] " . str_replace(["\n", "\r", "\t"], " ", $szQuery) . " | [db_params] " . json_encode($params) . "\n");
            
            $stmt = $this->pdo->prepare($szQuery);
            
            // Detectar si usa parámetros posicionales (?) o nombrados (:param)
            $isPositional = !empty($params) && is_numeric(array_keys($params)[0]);
            
            if ($isPositional) {
                // Para parámetros posicionales, usar execute() directamente
                $stmt->execute($params);
            } else {
                // Para parámetros nombrados, usar bindValue con tipado mejorado
                foreach ($params as $key => $value) {
                    if (is_array($value) && count($value) === 2) {
                        $stmt->bindValue($key, $value[0], $value[1]);
                    } else {
                        // Auto-detectar tipo de dato
                        $paramType = \PDO::PARAM_STR; // Por defecto
                        if (is_int($value)) {
                            $paramType = \PDO::PARAM_INT;
                        } elseif (is_bool($value)) {
                            $paramType = \PDO::PARAM_BOOL;
                        } elseif (is_null($value)) {
                            $paramType = \PDO::PARAM_NULL;
                        }
                        
                        $stmt->bindValue($key, $value, $paramType);
                    }
                }
                $stmt->execute();
            }
            
            $arrayData = $stmt->fetchAll(\PDO::FETCH_BOTH);
            $this->setNumRows(count($arrayData));
            
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);
            
            $this->setError(self::ERROR_CODE_OK);
            $this->setErrorDescription(self::ERROR_DESC_OK);
            
            $this->log->writeLog("{$this->tx} [db_response] {$this->nNumRows} rows, time: {$executionTime}ms\n");
            
        } catch (\PDOException $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->setError($e->getCode());
            $this->setErrorDescription($e->getMessage());
            
            $this->log->writeLog("{$this->tx} [db_error] Error SELECT legacy después de {$executionTime}ms: " . $e->getMessage() . "\n");
            
            if (!$this->debugMode) {
                $this->setErrorDescription("Error en la consulta SELECT");
            }
        }
        
        return $arrayData;
    }


    /**
     * Método mejorado para consultas SELECT que retorna arrays asociativos
     * Incluye logging detallado, métricas y mejor manejo de parámetros tipados
     * 
     * @param string $szQuery Consulta SELECT a realizar
     * @param array $params Parámetros para la consulta
     *        Puede ser array simple ([':id' => 5]) o con tipo ([':id' => [5, PDO::PARAM_INT]])
     * @return array Resultados como array asociativo
     */
    public function executeStmtResultAssoc(string $szQuery, array $params = []): array
    {
        $arrayData = [];
        $startTime = microtime(true);
        $this->openConnection();
        
        if (!$this->pdo) {
            $this->log->writeLog("{$this->tx} [db_error] No hay conexión disponible para executeStmtResultAssoc\n");
            return [];
        }
        
        try {
            // Log de la consulta
            $this->log->writeLog("{$this->tx} [db_query] " . str_replace(["\n", "\r", "\t"], " ", $szQuery) . " | [db_params] " . json_encode($params) . "\n");
            
            $stmt = $this->pdo->prepare($szQuery);
            
            // Detectar si usa parámetros posicionales (?) o nombrados (:param)
            $isPositional = !empty($params) && is_numeric(array_keys($params)[0]);
            
            if ($isPositional) {
                // Para parámetros posicionales, usar execute() directamente (más simple y eficiente)
                $stmt->execute($params);
            } else {
                // Para parámetros nombrados, usar bindValue con tipado mejorado
                foreach ($params as $key => $value) {
                    if (is_array($value) && count($value) === 2) {
                        $stmt->bindValue($key, $value[0], $value[1]);
                    } else {
                        // Auto-detectar tipo de dato
                        $paramType = \PDO::PARAM_STR; // Por defecto
                        if (is_int($value)) {
                            $paramType = \PDO::PARAM_INT;
                        } elseif (is_bool($value)) {
                            $paramType = \PDO::PARAM_BOOL;
                        } elseif (is_null($value)) {
                            $paramType = \PDO::PARAM_NULL;
                        }
                        
                        $stmt->bindValue($key, $value, $paramType);
                    }
                }
                $stmt->execute();
            }
            $arrayData = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->setNumRows(count($arrayData));
            
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);
            
            // Guardar métricas
            $this->queryMetrics[] = [
                'type' => 'SELECT',
                'time' => $executionTime,
                'rows' => $this->nNumRows
            ];
            
            $this->setError(self::ERROR_CODE_OK);
            $this->setErrorDescription(self::ERROR_DESC_OK);
            
            $this->log->writeLog("{$this->tx} [db_response] {$this->nNumRows} rows, time: {$executionTime}ms\n");
            
        } catch (\PDOException $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->setError($e->getCode());
            $this->setErrorDescription($e->getMessage());
            
            $this->log->writeLog("{$this->tx} [db_error] Error SELECT después de {$executionTime}ms: " . $e->getMessage() . "\n");
            
            // En producción, no mostrar detalles sensibles
            if (!$this->debugMode) {
                $this->setErrorDescription("Error en la consulta SELECT");
            }
        }
        
        return $arrayData;
    }

    public function getAffectedRows()
    {
        return $this->nAffectedRows;
    }

    public function getNumRows()
    {
        return $this->nNumRows;
    }

    /**
     * Obtiene el último ID insertado
     * @return int
     */
    public function getLastInsertId(): int
    {
        if ($this->pdo) {
            $insertId = $this->pdo->lastInsertId();
            $this->log->writeLog("{$this->tx} [db_lastInsertId] {$insertId}\n");
            return (int)$insertId;
        }
        return 0;
    }

    public function getError()
    {
        return $this->nError;
    }

    public function getErrorDescription()
    {
        return $this->szErrorDescription;
    }

    private function setError($nError)
    {
        $this->nError = $nError;
    }

    private function setErrorDescription($szErrorDescription)
    {
        $this->szErrorDescription = $szErrorDescription;
    }

    private function setAffectedRows($nAffectedRows)
    {
        $this->nAffectedRows = $nAffectedRows;
    }

    private function setNumRows($nNumRows)
    {
        $this->nNumRows = $nNumRows;
    }

    /**
     * Establece el transaction ID para logging
     * @param string $tx Transaction ID
     */
    public function setTx(string $tx): void
    {
        $this->tx = $tx;
    }

    /**
     * Inicia una transacción manual
     * @return bool
     */
    public function beginTransaction(): bool
    {
        $this->openConnection();
        
        if (!$this->pdo) {
            return false;
        }
        
        try {
            if ($this->pdo->beginTransaction()) {
                $this->log->writeLog("{$this->tx} [transaction] BEGIN\n");
                return true;
            }
            return false;
        } catch (\PDOException $e) {
            $this->log->writeLog("{$this->tx} [transaction error] BEGIN: " . $e->getMessage() . "\n");
            return false;
        }
    }

    /**
     * Confirma una transacción
     * @return bool
     */
    public function commit(): bool
    {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            if ($this->pdo->commit()) {
                $this->log->writeLog("{$this->tx} [transaction] COMMIT\n");
                return true;
            }
            return false;
        } catch (\PDOException $e) {
            $this->log->writeLog("{$this->tx} [transaction error] COMMIT: " . $e->getMessage() . "\n");
            return false;
        }
    }

    /**
     * Revierte una transacción
     * @return bool
     */
    public function rollback(): bool
    {
        if (!$this->pdo) {
            return false;
        }
        
        try {
            if ($this->pdo->rollback()) {
                $this->log->writeLog("{$this->tx} [transaction] ROLLBACK\n");
                return true;
            }
            return false;
        } catch (\PDOException $e) {
            $this->log->writeLog("{$this->tx} [transaction error] ROLLBACK: " . $e->getMessage() . "\n");
            return false;
        }
    }

    /**
     * Verifica si hay una transacción activa
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->pdo ? $this->pdo->inTransaction() : false;
    }

    /**
     * Habilita o deshabilita el modo debug
     * @param bool $debug
     */
    public function setDebugMode(bool $debug): void
    {
        $this->debugMode = $debug;
        $this->log->writeLog("{$this->tx} [debug] Debug mode: " . ($debug ? 'ENABLED' : 'DISABLED') . "\n");
    }

    /**
     * Obtiene las métricas de queries ejecutados
     * @return array
     */
    public function getQueryMetrics(): array
    {
        return $this->queryMetrics;
    }

    /**
     * Obtiene estadísticas de performance
     * @return array
     */
    public function getPerformanceStats(): array
    {
        $totalQueries = count($this->queryMetrics);
        if ($totalQueries === 0) {
            return [
                'total_queries' => 0,
                'avg_time' => 0,
                'max_time' => 0,
                'min_time' => 0,
                'total_time' => 0
            ];
        }
        
        $times = array_column($this->queryMetrics, 'time');
        
        return [
            'total_queries' => $totalQueries,
            'avg_time' => round(array_sum($times) / $totalQueries, 2),
            'max_time' => max($times),
            'min_time' => min($times),
            'total_time' => round(array_sum($times), 2),
            'connections_made' => $this->connectionCount
        ];
    }

    /**
     * Limpia las métricas acumuladas
     */
    public function clearMetrics(): void
    {
        $this->queryMetrics = [];
        $this->log->writeLog("{$this->tx} [metrics] Métricas limpiadas\n");
    }
}
