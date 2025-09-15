<?php
namespace App\Utils;

use App\Utils\DayLog;

/**
 * Clase Singleton para gestionar una única conexión a la base de datos
 * por request. Utiliza PDO para una gestión de errores y seguridad mejoradas.
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
     * El constructor privado previene la creación de instancias externas.
     */
    private function __construct() {
        // Inicializa la instancia de DayLog una única vez
        $this->log = new DayLog(BASE_HOME_PATH, 'DatabaseConnection');
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
        $this->connection = null;
        self::$instance = null;

        // Log de cierre de conexión a BD con DayLog
        $this->log->writeLog("DatabaseConnection: Se cerró una conexión a la base de datos (PDO). \n");
    }
}