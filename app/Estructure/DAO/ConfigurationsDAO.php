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
        $this->setError(ERROR_CODE_SUCCESS);
        $this->setErrorDescription(ERROR_DESC_SUCCESS);
        // Usar DatabaseConnection Singleton
        $db = DatabaseConnection::getInstance();
        $db->setTx($this->tx);
        $query = "SELECT P.Id, P.Name AS ident, P.Value AS valor, P.TypeForm, P.Group
            FROM `" . SCHEMA_DB . "`.`MAS_PARAMETER` P
            WHERE P.Name = :name";

        $params = [':name' => $name];
        $arrayData = $db->getRow($query, $params);
        
        if ($db->getError() !== ERROR_CODE_SUCCESS) {
            $this->setError($db->getError());
            $this->setErrorDescription($db->getErrorDescription());
        }
        return $arrayData;
    }
}
