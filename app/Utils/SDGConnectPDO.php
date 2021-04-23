<?php

namespace App\Utils;

class SDGConnectPDO
{

    private $usuario;
    private $password;
    private $link;
    private $result;
    private $intentos;
    private $pdo;
    private $state_connection;
    private $prepare;
    private $error;
    private $errorDesc;
    private $log;
    private $tx;
    private $query = ''; // query to execute
    private $params = [];
    private $affectedRows;

    public function __construct($user, $pass, $db = null, $host, $port = 3306)
    {
        $options = [
            // PDO::ATTR_EMULATE_PREPARES   => false, // turn off emulation mode for "real" prepared statements
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION, //turn on errors in the form of exceptions
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, //make the default fetch be an associative array
        ];
        $this->result = 0;
        try {
            $dsn = "mysql:host={$host}" . ($db != null ? ";dbname={$db}" : "") . ";port={$port};charset=utf8";
            $this->pdo = new \PDO($dsn, $user, $pass, $options);
            $this->state_connection = true;
        } catch (\Exception $e) {
            $this->state_connection = false;
        }
    }

    public function getConnected()
    {
        return $this->state_connection;
    }

    public function newQuery($sql)
    {
        $this->prepare = $this->pdo->prepare($sql);
    }

    public function paramsQuery($name_param, $value_param)
    {
        $this->prepare->bindParam($name_param, $value_param);
    }

    public function paramsQueryType($name_param, $value_param, $type_param)
    {
        $this->prepare->bindParam($name_param, $value_param, $type_param);
    }

    public function count()
    {
        return $this->prepare->columnCount();
    }
    
    public function execQuery()
    {
        return $this->prepare->execute();
    }

    public function resultSet()
    {
        return $this->prepare->fetchAll();
    }

    public function getPDO()
    {
        return $this->pdo;
    }

    public function beginQuery()
    {
        $this->pdo->beginTransaction();
    }

    public function commitQuery()
    {
        $this->pdo->commit();
    }

    public function rollBackQuery()
    {
        $this->pdo->rollBack();
    }

    public function set($name,  $value ) {
        $this->$name = $value;
    } 

    public function get($name) {
        return $this->$name;
    }

    public function getError()
    {
        return array(
            "Code" => $this->pdo->errorCode(),
            "Description" => $this->pdo->errorInfo()
        );
    }

    public function close()
    {
        $this->pdo = null;
        $this->state_connection = false;
    }

    public function setAffectedRows($rows = 0) {
        $this->affectedRows = $rows;
    }

    public function getAffectedRows() {
        return $this->affectedRows;
    }

    public function getLastId()
    {
        return $this->pdo->lastInsertId();
    }

    public function setLog($log) {
        $this->log = $log;
    }

    public function setTx($tx) {
        $this->tx = $tx;
    }
    
    public function setQuery($query = '') {
        $this->query = $query;
    }

    public function getQuery() {
        return $this->query;
    }

    public function setParams( $params = [] ) {
        $this->params = $params;
    }

    public function getRow() {
        $row = [];
        if ($this->getConnected()) {
            try {
                $this->beginQuery();
                $select = $this->getQuery();
                if ( $this->log  && $this->tx ) { 
                    $this->log->writeLog("{$this->tx} [query]: " . print_r($select, true) . " \n");

                }
                $this->newQuery($select);

                if ( !empty($this->params) ) {
                    foreach( (array)$this->params as $param => $value ) {
                        $this->paramsQuery($param, $value);
                    }                
                }
                
                $this->execQuery();
                $resp = $this->resultSet();
                $count = count($resp);
                if ( $this->log  && $this->tx ) { 
                    $this->log->writeLog("{$this->tx} [response (".$count." rows)]: \n");
                }
                if ($count > 0) {
                    if ( $this->log  && $this->tx ) { 
                        $this->log->writeLog("{$this->tx} Existe registro \n");
                    }
                    $row = $resp[0];
                    $this->set('error', ERROR_CODE_SUCCESS);
                    $this->set('errorDescription', 'Existe registro');
                } else {
                    if ( $this->log  && $this->tx ) { 
                        $this->log->writeLog("{$this->tx} No existe registro. \n");
                    }
                    $this->set('error', ERROR_CODE_NOT_FOUND);
                    $this->set('errorDescription', 'No existe registro.');
                }
            } catch (\PDOException $e) {
                $error = $e->getMessage();
                if ( $this->log  && $this->tx ) { 
                    $this->log->writeLog("{$this->tx} Error query: " . print_r($error, true) . " ".__FUNCTION__." ".__LINE__." \n");
                }
                $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                $this->set('errorDescription', 'Error en ejecución de query.');
                $this->rollBackQuery();
            }
            $this->close();
        } else {
            $this->set('error', ERROR_CODE_INTERNAL_SERVER);
            $this->set('errorDescription', 'Error en conexión a base de datos.');

            if ( $this->log  && $this->tx ) { 
                $this->log->writeLog("{$this->tx} Error conexion BD: " . print_r($this->pdo->errorCode(), true) . " : " . print_r($this->pdo->errorInfo(), true) . "\n");
            }
        }
        return $row;
    }

    public function getData() {
        $data = [];
        if ($this->getConnected()) {
            try {
                $this->beginQuery();
                $select = $this->getQuery();
                if ( $this->log  && $this->tx ) { 
                    $this->log->writeLog("{$this->tx} [query]: " . print_r($select, true) . " \n");
                }
                $this->newQuery($select);

                if ( !empty($this->params) ) {
                    foreach( (array)$this->params as $param => $value ) {
                        $this->paramsQuery($param, $value);
                        if ( $this->log  && $this->tx ) { 
                            $this->log->writeLog("{$this->tx} [param]: " . print_r($param, true) . " = " . print_r($value, true) . " \n");
                        }
                    }
                }
                
                $this->execQuery();
                $resp = $this->resultSet();
                $count = count($resp);
                if ( $this->log  && $this->tx ) { 
                    $this->log->writeLog("{$this->tx} [response (".$count." rows)]: \n");
                }
                if ($count > 0) {
                    if ( $this->log  && $this->tx ) { 
                        $this->log->writeLog("{$this->tx} Existe registro \n");
                    }
                    $data = $resp;
                    $this->set('error', ERROR_CODE_SUCCESS);
                    $this->set('errorDescription', 'Existe registro');
                } else {
                    if ( $this->log  && $this->tx ) { 
                        $this->log->writeLog("{$this->tx} No existe registro. \n");
                    }
                    $this->set('error', ERROR_CODE_NOT_FOUND);
                    $this->set('errorDescription', 'No existe registro.');
                }
            } catch (\PDOException $e) {
                $error = $e->getMessage();
                if ( $this->log  && $this->tx ) { 
                    $this->log->writeLog("{$this->tx} Error query: " . print_r($error, true) . " ".__FUNCTION__." ".__LINE__." \n");
                }
                $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                $this->set('errorDescription', 'Error en ejecución de query.');
                $this->rollBackQuery();
            }
            $this->close();
        } else {
            $this->set('error', ERROR_CODE_INTERNAL_SERVER);
            $this->set('errorDescription', 'Error en conexión a base de datos.');

            if ( $this->log  && $this->tx ) { 
                $this->log->writeLog("{$this->tx} Error conexion BD: " . print_r($this->pdo->errorCode(), true) . " : " . print_r($this->pdo->errorInfo(), true) . "\n");
            }
        }
        return $data;
    }

    public function addRow() {
        $idRow = 0;
        if ($this->getConnected()) {
            try {
                $this->beginQuery();
                $select = $this->getQuery();
                if ( $this->log  && $this->tx ) { 
                    $this->log->writeLog("{$this->tx} [query]: " . print_r($select, true) . " \n");
                }
                $this->newQuery($select);

                if ( !empty($this->params) ) {
                    foreach( (array)$this->params as $param => $value ) {
                        $this->paramsQuery($param, $value);
                        if ( $this->log  && $this->tx ) { 
                            $this->log->writeLog("{$this->tx} [param]: " . print_r($param, true) . " = " . print_r($value, true) . " \n");
                        }
                    }
                }
                
                $this->execQuery();
                $this->setAffectedRows( $this->prepare->rowCount() );
                $idRow = $this->getLastId();
                $this->commitQuery();
                
                if ($idRow > 0) {
                    if ( $this->log  && $this->tx ) { 
                        $this->log->writeLog("{$this->tx} Existe registro \n");
                    }
                    $this->set('error', ERROR_CODE_SUCCESS);
                    $this->set('errorDescription', 'Guardado exitosamente');
                } else {
                    if ( $this->log  && $this->tx ) { 
                        $this->log->writeLog("{$this->tx} No se pudo guardar el registro. \n");
                    }
                    $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                    $this->set('errorDescription', 'No se pudo guardar el registro.');
                }
            } catch (\PDOException $e) {
                $error = $e->getMessage();
                if ( $this->log  && $this->tx ) { 
                    $this->log->writeLog("{$this->tx} Error query: " . print_r($error, true) . " ".__FUNCTION__." ".__LINE__." \n");
                }
                $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                $this->set('errorDescription', 'Error en ejecución de query.');
                $this->rollBackQuery();
            }
            $this->close();
        } else {
            $this->set('error', ERROR_CODE_INTERNAL_SERVER);
            $this->set('errorDescription', 'Error en conexión a base de datos.');

            if ( $this->log  && $this->tx ) { 
                $this->log->writeLog("{$this->tx} Error conexion BD: " . print_r($this->pdo->errorCode(), true) . " : " . print_r($this->pdo->errorInfo(), true) . "\n");
            }
        }
        return $idRow;
    }

    public function modifyRow() {
        $modificado = false;
        if ($this->getConnected()) {
            try {
                $this->beginQuery();
                $select = $this->getQuery();
                if ( $this->log  && $this->tx ) { 
                    $this->log->writeLog("{$this->tx} [query]: " . print_r($select, true) . " \n");
                }
                $this->newQuery($select);

                if ( !empty($this->params) ) {
                    foreach( (array)$this->params as $param => $value ) {
                        $this->paramsQuery($param, $value);
                        if ( $this->log  && $this->tx ) { 
                            $this->log->writeLog("{$this->tx} [param]: " . print_r($param, true) . " = " . print_r($value, true) . " \n");
                        }
                    }
                }
                
                $modificado = true;
                $this->execQuery();
                $this->setAffectedRows( $this->prepare->rowCount() );
                $this->commitQuery();
                $this->set('error', ERROR_CODE_SUCCESS);
                $this->set('errorDescription', 'Modificación exitosa');
            } catch (\PDOException $e) {
                $error = $e->getMessage();
                if ( $this->log  && $this->tx ) { 
                    $this->log->writeLog("{$this->tx} Error query: " . print_r($error, true) . " ".__FUNCTION__." ".__LINE__." \n");
                }
                $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                $this->set('errorDescription', 'Error en ejecución de query.');
                $this->rollBackQuery();
            }
            $this->close();
        } else {
            $this->set('error', ERROR_CODE_INTERNAL_SERVER);
            $this->set('errorDescription', 'Error en conexión a base de datos.');

            if ( $this->log  && $this->tx ) { 
                $this->log->writeLog("{$this->tx} Error conexion BD: " . print_r($this->pdo->errorCode(), true) . " : " . print_r($this->pdo->errorInfo(), true) . "\n");
            }
        }
        return $modificado;
    }
}
