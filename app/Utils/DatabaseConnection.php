<?php
namespace App\Utils;

use App\Utils\DayLog;

/**
 * Clase Singleton para gestionar una única conexión a la base de datos
 * por request. Utiliza PDO para una gestión de errores y seguridad mejoradas.
 * Incluye métodos utilitarios para operaciones comunes de base de datos.
 * @version 1.1.0
 * @author Dey Gordillo <dey.gordillo@simpledatacorp.com>
 */
class DatabaseConnection 
{
    /**
     * @var DatabaseConnection|null La única instancia de la clase.
     */
    private static $instance = null;

    /**
     * @var \PDO|null La conexión activa a la base de datos.
     */
    private $connection = null;
    
    /**
     * @var DayLog|null Instancia para el registro de eventos de la clase.
     */
    private $log = null;

    /**
     * @var string|null Transaction ID para logging
     */
    private $tx = null;

    /**
     * @var int Código de error de la última operación
     */
    private $error = 0;

    /**
     * @var string Descripción del error de la última operación
     */
    private $errorDescription = '';

    /**
     * @var int Número de filas afectadas en la última operación
     */
    private $affectedRows = 0;
    
    /**
     * El constructor privado previene la creación de instancias externas.
     */
    private function __construct() {
        // Inicializa la instancia de DayLog una única vez
        $this->log = new DayLog(BASE_HOME_PATH, 'DatabaseConnection');
        $this->error = ERROR_CODE_SUCCESS;
        $this->errorDescription = ERROR_DESC_SUCCESS;
    }

    /**
     * El método mágico __clone previene la clonación de la instancia.
     */
    private function __clone() {}

    /**
     * Obtiene la única instancia de la clase Singleton.
     * Si la instancia no existe, la crea.
     * @return DatabaseConnection
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtiene la conexión activa a la base de datos.
     * Si la conexión no existe, la crea utilizando PDO.
     * @return \PDO
     * @throws \Exception Si la conexión a la base de datos falla.
     */
    public function getConnection()
    {
        if ($this->connection === null) {
            try {
                // Log de apertura de conexión a BD con DayLog, sin mostrar credenciales sensibles
                $this->log->writeLog("DatabaseConnection: Se abrió una conexión a la base de datos (PDO) - Esquema: " . SCHEMA_DB . "\n");
                
                $this->connection = new \PDO(
                    "mysql:host=" . HOST_DB . ";dbname=" . SCHEMA_DB,
                    USER_DB, 
                    PASS_DB,
                    array(\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
                );
                $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            } catch (\PDOException $e) {
                // Se lanza una excepción personalizada para un mejor manejo de errores.
                throw new \Exception("Error de conexión a la base de datos: " . $e->getMessage());
            }
        }
        return $this->connection;
    }

    /**
     * Cierra la conexión a la base de datos.
     * Al finalizar un request, se debe llamar a este método.
     */
    public function closeConnection()
    {
        if ($this->connection !== null) {
            $this->connection = null;
            self::$instance = null;
            $this->log->writeLog("DatabaseConnection: Se cerró una conexión a la base de datos (PDO). \n");
        }
    }

    /**
     * Establece el transaction ID para el logging
     * @param string $tx Transaction ID
     */
    public function setTx($tx)
    {
        $this->tx = $tx;
    }

    /**
     * Obtiene el código de error de la última operación
     * @return int
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Obtiene la descripción del error de la última operación
     * @return string
     */
    public function getErrorDescription()
    {
        return $this->errorDescription;
    }

    /**
     * Obtiene el número de filas afectadas en la última operación
     * @return int
     */
    public function getAffectedRows()
    {
        return $this->affectedRows;
    }

    /**
     * Ejecuta una consulta SELECT común para getRow y getData
     * @param string $query La consulta SQL
     * @param array $params Parámetros para la consulta preparada
     * @return array Array con el resultado y metadatos
     */
    private function executeSelectQuery($query, $params = [])
    {
        $startTime = microtime(true);
        $result = [];
        $count = 0;
        
        try {
            $connection = $this->getConnection();
            $this->log->writeLog("{$this->tx} [query]: " . $query . "\n");
            $this->log->writeLog("{$this->tx} [params]: " . print_r(json_encode($params), true) . "\n");
            
            $stmt = $connection->prepare($query);
            
            // Bind parameters if provided
            if (!empty($params)) {
                foreach ($params as $param => $value) {
                    $stmt->bindParam($param, $value);
                }
            }

            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $count = count($result);

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            $this->log->writeLog("{$this->tx} [response]: ({$count} rows) time: {$executionTime}ms\n");

            if ($count > 0) {
                $this->error = ERROR_CODE_SUCCESS;
                $this->errorDescription = 'Existe registro';
            } else {
                $this->error = ERROR_CODE_NOT_FOUND;
                $this->errorDescription = 'No existe registro.';
            }

        } catch (\PDOException $e) {
            $this->error = ERROR_CODE_INTERNAL_SERVER;
            $this->errorDescription = 'Error en ejecución de query.';
            $this->log->writeLog("{$this->tx} [error query]: " . $e->getMessage() . "\n");
        }

        return [
            'result' => $result,
            'count' => $count
        ];
    }

    /**
     * Ejecuta una consulta SELECT y devuelve una sola fila
     * @param string $query La consulta SQL
     * @param array $params Parámetros para la consulta preparada
     * @return array La fila resultante o array vacío si no hay resultados
     */
    public function getRow($query, $params = [])
    {
        $queryResult = $this->executeSelectQuery($query, $params);
        
        if ($queryResult['count'] > 0) {
            return $queryResult['result'][0]; // Retorna solo la primera fila
        }
        
        return []; // Retorna array vacío si no hay resultados
    }

    /**
     * Ejecuta una consulta SELECT y devuelve múltiples filas
     * @param string $query La consulta SQL
     * @param array $params Parámetros para la consulta preparada
     * @return array Array de filas resultantes
     */
    public function getData($query, $params = [])
    {
        $queryResult = $this->executeSelectQuery($query, $params);
        
        if ($queryResult['count'] > 0) {
            return $queryResult['result']; // Retorna todas las filas
        }
        return []; // Retorna array vacío si no hay resultados
    }

    /**
     * Ejecuta una consulta INSERT y devuelve el ID del registro insertado
     * @param string $query La consulta SQL
     * @param array $params Parámetros para la consulta preparada
     * @return int ID del registro insertado o 0 si falló
     */
    public function addRow($query, $params = [])
    {
        $insertId = 0;
        $startTime = microtime(true);
        
        try {
            $connection = $this->getConnection();
            $connection->beginTransaction();
            
            $this->log->writeLog("{$this->tx} [query]: " . $query . "\n");
            $this->log->writeLog("{$this->tx} [params]: " . print_r(json_encode($params), true) . "\n");

            $stmt = $connection->prepare($query);
            
            // Bind parameters if provided
            if (!empty($params)) {
                foreach ($params as $param => $value) {
                    $stmt->bindParam($param, $value);
                }
            }

            $stmt->execute();
            $this->affectedRows = $stmt->rowCount();
            $insertId = $connection->lastInsertId();
            $connection->commit();

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            if ($insertId > 0) {
                $this->error = ERROR_CODE_SUCCESS;
                $this->errorDescription = 'Guardado exitosamente';
                $this->log->writeLog("{$this->tx} [response]: insertId({$insertId}) time: {$executionTime}ms\n");
            } else {
                $this->error = ERROR_CODE_INTERNAL_SERVER;
                $this->errorDescription = 'No se pudo guardar el registro.';
                $this->log->writeLog("{$this->tx} [response]: No se pudo guardar el registro. time: {$executionTime}ms\n");
            }

        } catch (\PDOException $e) {
            $connection->rollBack();
            $this->error = ERROR_CODE_INTERNAL_SERVER;
            $this->errorDescription = 'Error en ejecución de query.';
            $this->log->writeLog("{$this->tx} [error query]: " . $e->getMessage() . "\n");
        }

        return $insertId;
    }

    /**
     * Ejecuta una consulta UPDATE o DELETE
     * @param string $query La consulta SQL
     * @param array $params Parámetros para la consulta preparada
     * @return bool true si la operación fue exitosa
     */
    public function modifyRow($query, $params = [])
    {
        $success = false;
        $startTime = microtime(true);
        
        try {
            $connection = $this->getConnection();
            $connection->beginTransaction();
            
            $this->log->writeLog("{$this->tx} [query]: " . $query . "\n");
            $this->log->writeLog("{$this->tx} [params]: " . print_r(json_encode($params), true) . "\n");

            $stmt = $connection->prepare($query);
            
            // Bind parameters if provided
            if (!empty($params)) {
                foreach ($params as $param => $value) {
                    $stmt->bindParam($param, $value);
                }
            }

            $stmt->execute();
            $this->affectedRows = $stmt->rowCount();
            $connection->commit();
            $success = true;

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            $this->error = ERROR_CODE_SUCCESS;
            $this->errorDescription = 'Modificación exitosa';
            $this->log->writeLog("{$this->tx} [response]: affectedRows({$this->affectedRows}) time: {$executionTime}ms\n");

        } catch (\PDOException $e) {
            $connection->rollBack();
            $this->error = ERROR_CODE_INTERNAL_SERVER;
            $this->errorDescription = 'Error en ejecución de query.';
            $this->log->writeLog("{$this->tx} [error query]: " . $e->getMessage() . "\n");
        }

        return $success;
    }
}