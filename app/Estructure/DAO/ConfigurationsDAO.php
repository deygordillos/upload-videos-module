<?php

namespace App\Estructure\DAO;

use App\Estructure\BaseMethod;
use App\Utils\DatabaseConnection;

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
        
        // Usar DatabaseConnection Singleton
        $db = DatabaseConnection::getInstance();
        $db->setTx($this->tx);
        
        $query = "SELECT P.Id, P.Name AS ident, P.Value AS valor, P.TypeForm, P.Group
            FROM `" . SCHEMA_DB . "`.`MAS_PARAMETER` P
            WHERE P.Name = :name";

        $params = [':name' => $name];
        $arrayData = $db->getRow($query, $params);

        if ($db->getError() === ERROR_CODE_SUCCESS) {
            $this->error = ERROR_CODE_SUCCESS;
            $this->errorDescription = ERROR_DESC_SUCCESS;
        } else if ($db->getError() === ERROR_CODE_NOT_FOUND) {
            $this->error = ERROR_CODE_NOT_FOUND;
            $this->errorDescription = ERROR_DESC_NOT_FOUND;
            $arrayData = [];
        } else {
            $this->error = $db->getError();
            $this->errorDescription = $db->getErrorDescription();
            $arrayData = [];
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " end\n");
        return $arrayData;
    }
}
