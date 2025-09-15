<?php

namespace App\Estructure\DAO;

use App\Estructure\BaseMethod;

class ConfigurationsDAO extends BaseMethod
{
    /**
     * Obtener info de la configuracion por nombre
     * @param string $name Nombre unico de configuración
     * @return array Configuración encontrada
     */
    public function getConfigByName($name = '')
    {
        $this->log->writeLog("$this->tx " . __FUNCTION__ . "\n");
        
        $mysqli = new \mysqli(HOST_DB, USER_DB, PASS_DB, SCHEMA_DB);
        $arrayData = [];
        
        if ($mysqli->connect_error) {
            $this->log->writeLog("$this->tx ERROR: " . $mysqli->connect_error . "\n");
            $this->set('error', ERROR_CODE_INTERNAL_SERVER);
            $this->set('errorDescription', ERROR_DESC_INTERNAL_SERVER);
        } else {
            $query = "SELECT P.Id, P.Name AS ident, P.Value AS valor, P.TypeForm, P.Group
                FROM `" . SCHEMA_DB . "`.`MAS_PARAMETER` P
                WHERE P.Name = '".$name."'; ";
            $this->log->writeLog("$this->tx SQL: " . print_r($query, true) . " \n");
            
            if (!$result = $mysqli->query($query)) {
                $this->log->writeLog("$this->tx QUERY ERROR: " . $mysqli->error . "\n");
                $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                $this->set('errorDescription', ERROR_DESC_INTERNAL_SERVER);
            } else {
                if ($result->num_rows > 0) {
                    $arrayData = $result->fetch_assoc();
                    $this->set('error', ERROR_CODE_SUCCESS);
                    $this->set('errorDescription', ERROR_DESC_SUCCESS);
                } else {
                    $this->set('error', ERROR_CODE_NOT_FOUND);
                    $this->set('errorDescription', ERROR_DESC_NOT_FOUND);
                }
            }
            $mysqli->close();
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " end\n");
        return $arrayData;
    }
}
