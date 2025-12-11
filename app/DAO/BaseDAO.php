<?php

declare(strict_types=1);

namespace App\DAO;

use App\BaseComponent;

/**
 * Clase base para DAOs
 * Proporciona manejo de errores y métodos comunes para consultas
 */
abstract class BaseDAO extends BaseComponent
{
    /**
     * Ejecuta SELECT y propaga errores automáticamente
     */
    protected function executeSelect(string $query, array $params = []): array
    {
        try {
            $result = $this->db->executeStmtResultAssoc($query, $params);
            
            // Propagar errores de DBConnector automáticamente
            if ($this->db->getError() !== ERROR_CODE_OK) {
                $this->setError($this->db->getError());
                $this->setErrorDescription($this->db->getErrorDescription());
                return [];
            }
            
            // Solo establecer error si no hay resultados Y no hay error de BD
            if (empty($result)) {
                $this->setError(ERROR_CODE_NO_FOUND_RECORD);
                $this->setErrorDescription(ERROR_DESC_NO_FOUND_RECORD);
            } else {
                $this->setError(ERROR_CODE_OK);
                $this->setErrorDescription(ERROR_DESC_OK);
            }
            
            return $result;
            
        } catch (\Throwable $e) {
            $this->setError(ERROR_CODE_500);
            $this->setErrorDescription("Error en consulta: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Ejecuta statement y propaga errores automáticamente
     * Para INSERTs retorna el lastInsertId, para otros statements retorna true/false
     */
    protected function executeStatement(string $query, array $params = []): bool|int
    {
        try {
            $result = $this->db->executeStmt($query, $params);
            
            // Propagar errores de DBConnector automáticamente
            if ($this->db->getError() !== ERROR_CODE_OK) {
                $this->setError($this->db->getError());
                $this->setErrorDescription($this->db->getErrorDescription());
                return false;
            }
            
            // Éxito automático
            $this->setError(ERROR_CODE_OK);
            $this->setErrorDescription(ERROR_DESC_OK);
            
            return $result; // bool para UPDATE/DELETE, int para INSERT
            
        } catch (\Throwable $e) {
            $this->setError(ERROR_CODE_500);
            $this->setErrorDescription("Error en statement: " . $e->getMessage());
            return false;
        }
    }
}