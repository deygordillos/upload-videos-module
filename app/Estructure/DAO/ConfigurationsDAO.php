<?php

namespace App\Estructure\DAO;

use App\Estructure\BaseMethod;
use App\Utils\DatabaseConnection;

class ConfigurationsDAO extends BaseMethod
{
    /**
     * @var DatabaseConnection Instancia de conexión a la base de datos
     */
    private $db;

    /**
     * Constructor que recibe la conexión de base de datos por inyección de dependencias
     * @param DatabaseConnection $db Instancia de la conexión a la base de datos
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Obtener info de la configuracion por nombre
     * @param string $name Nombre unico de configuración
     * @return array Configuración encontrada
     */
    public function getConfigByName($name = '')
    {
        $this->setError(ERROR_CODE_SUCCESS);
        $this->setErrorDescription(ERROR_DESC_SUCCESS);
        // Usar DatabaseConnection inyectada
        $this->db->setTx($this->tx);
        $query = "SELECT P.Id, P.Name AS ident, P.Value AS valor, P.TypeForm, P.Group
            FROM `" . SCHEMA_DB . "`.`MAS_PARAMETER` P
            WHERE P.Name = :name";

        $params = [':name' => $name];
        $arrayData = $this->db->getRow($query, $params);
        
        if ($this->db->getError() !== ERROR_CODE_SUCCESS) {
            $this->setError($this->db->getError());
            $this->setErrorDescription($this->db->getErrorDescription());
        }
        return $arrayData;
    }
}
