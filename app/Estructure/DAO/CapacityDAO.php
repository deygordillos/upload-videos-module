<?php
namespace App\Estructure\DAO;

use App\Estructure\BaseMethod;
use App\Estructure\DTO\CapacityDTO;
use App\Utils\DatabaseConnection;

class CapacityDAO extends BaseMethod
{
    /**
     * Metodo que entrega capacidad de categoria para un pool
     * 
     * @param CapacityDTO $dto Objeto con los datos necesarios
     * @return array
     */
    public function getCapacity(CapacityDTO $dto): array
    {
        $this->log->writeLog("$this->tx " . __FUNCTION__ . "\n");

        $this->set('error', ERROR_CODE_SUCCESS);
        $this->set('errorDescription', ERROR_DESC_SUCCESS);

        $arrayData = [];

        // Usar DatabaseConnection Singleton
        $db = DatabaseConnection::getInstance();
        $db->setTx($this->tx);

        $poolId = $dto->getPoolId();
        $periodo = $dto->getPeriodo();
        $wherePeriodo = (!empty($periodo)) ? " AND perid.`Id` = :periodo " : "";

        $query = "SELECT 
            cc.Id AS 'categoryId',
            cc.NameCategory AS 'categoryName',
            cal.DayOfWeek,
            GROUP_CONCAT(s.id SEPARATOR ',') AS idsSkills,
            SUM(TIME_TO_SEC(SUBTIME(cal.EndTime, cal.StartTime))/60) AS 'quota'
        FROM " . SCHEMA_DB . ".ROUTING_POOL pool
        JOIN " . SCHEMA_DB . ".ROUTING_POOL poolchild ON poolchild.parentId = pool.idPool
        JOIN " . SCHEMA_DB . ".REL_POOL_CAPACITY pc
                ON pc.IdPool = poolchild.idPool
        JOIN " . SCHEMA_DB . ".TRAZER_MAS_CATEGORIA_CAPACIDAD cc
                ON cc.id = pc.IdCapacity
        JOIN " . SCHEMA_DB . ".REL_CATEGORY_CAPACITY_SKILL rcc
                ON rcc.idCategoryCapacity = cc.Id
        JOIN " . SCHEMA_DB . ".TRAZER_MAS_SKILL s
                ON s.id = rcc.idSkill
        JOIN " . SCHEMA_DB . ".REL_CATEGORY_CAPACITY_PERIOD cc_perid
                ON cc_perid.idCategoryCapacity = cc.id
        JOIN " . SCHEMA_DB . ".TRAZER_MAS_PERIODOS perid
                ON perid.id = cc_perid.idPeriod " . $wherePeriodo . "
        JOIN " . SCHEMA_DB . ".TRAZER_REL_DAY_OF_CALENDAR cal
                ON cal.`IdCalendar` = pool.idCalendar AND perid.`startTime` BETWEEN cal.`StartTime` AND cal.`EndTime`
                AND perid.`endTime` BETWEEN cal.`StartTime` AND cal.`EndTime`
        WHERE poolchild.idPool = :poolId
        GROUP BY 
                categoryId,
                cal.DayOfWeek";

        $params = [':poolId' => $poolId];
        if (!empty($periodo)) {
            $params[':periodo'] = $periodo;
        }
        $arrayData = $db->getData($query, $params);

        if ($db->getError() === ERROR_CODE_SUCCESS) {
            $this->set('arrayData', $arrayData);
            $this->set('rows', count($arrayData));
            $this->log->writeLog("$this->tx rowCount:" . count($arrayData) . "\n");
        } else {
            $this->set('error', $db->getError());
            $this->set('errorDescription', $db->getErrorDescription());
        }
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " end\n");
        
        return $arrayData;
    }

    /**
     * Obtiene la cuota reservada para una categoría en una fecha específica
     * 
     * @param CapacityDTO $dto Objeto con los datos necesarios
     * @return int Cuota reservada
     */
    public function getReservedQuota(CapacityDTO $dto): int
    {
        $this->log->writeLog("$this->tx " . __FUNCTION__ . "\n");

        $this->set('error', ERROR_CODE_SUCCESS);
        $this->set('errorDescription', ERROR_DESC_SUCCESS);

        // Usar DatabaseConnection Singleton
        $db = DatabaseConnection::getInstance();
        $db->setTx($this->tx);

        $categoryId = $dto->getCategoryId();
        $date = $dto->getDate();
        $poolId = $dto->getPoolId();
        $periodo = $dto->getPeriodo();

        $query = "SELECT IF(SUM(reservedQuota + minEntreViaje) IS NOT NULL, 
            SUM(reservedQuota + minEntreViaje) , 0) AS 'reserved'
            FROM " . SCHEMA_DB . ".QUOTA_RESERVED
            WHERE capacityCategoryId = :categoryId 
                AND reservedDate = :date
                AND poolId = :poolId
                AND periodo = :periodo";

        $params = [
            ':categoryId' => $categoryId,
            ':date' => $date,
            ':poolId' => $poolId,
            ':periodo' => $periodo
        ];

        $row = $db->getRow($query, $params);

        $reserved = 0;
        if ($db->getError() === ERROR_CODE_SUCCESS) {
            $reserved = isset($row['reserved']) ? (int)$row['reserved'] : 0;
            $this->log->writeLog("$this->tx reserved:($reserved)\n");
            $this->set('reserved', $reserved);
        } else {
            $this->set('error', $db->getError());
            $this->set('errorDescription', $db->getErrorDescription());
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " end\n");
        return $reserved;
    }

    /**
     * Reserva cuota para una categoría en una fecha específica
     * 
     * @param CapacityDTO $dto Objeto con los datos necesarios
     * @return int ID del registro insertado
     */
    public function setReservedQuota(CapacityDTO $dto): int
    {
        $this->log->writeLog("$this->tx " . __FUNCTION__ . "\n");

        $this->set('error', ERROR_CODE_SUCCESS);
        $this->set('errorDescription', ERROR_DESC_SUCCESS);

        // Usar DatabaseConnection Singleton
        $db = DatabaseConnection::getInstance();
        $db->setTx($this->tx);

        $categoryId = $dto->getCategoryId();
        $date = $dto->getDate();
        $poolId = $dto->getPoolId();
        $reserve = $dto->getRequestedAmount();
        $minEntreViaje = $dto->getMinEntreViaje();
        $periodo = $dto->getPeriodo();

        $query = "INSERT INTO " . SCHEMA_DB . ".QUOTA_RESERVED
                      (reservedDate, capacityCategoryId, reservedQuota, minEntreViaje, poolId, periodo)
                      VALUES(:date, :categoryId, :reserve, :minEntreViaje, :poolId, :periodo)";

        $params = [
            ':date' => $date,
            ':categoryId' => $categoryId,
            ':reserve' => $reserve,
            ':minEntreViaje' => $minEntreViaje,
            ':poolId' => $poolId,
            ':periodo' => $periodo
        ];

        $insertId = $db->addRow($query, $params);

        if ($db->getError() !== ERROR_CODE_SUCCESS) {
            $this->set('error', $db->getError());
            $this->set('errorDescription', $db->getErrorDescription());
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " insertId: $insertId \n");
        return $insertId;
    }

    /**
     * Verifica si hay bloqueadores de programación para una fecha
     * 
     * @param CapacityDTO $dto Objeto con los datos necesarios
     * @return int Número de bloqueadores encontrados
     */
    public function getScheduleBlock(CapacityDTO $dto): int
    {
        $this->log->writeLog("$this->tx " . __FUNCTION__ . "\n");
        $this->set('error', ERROR_CODE_SUCCESS);
        $this->set('errorDescription', ERROR_DESC_SUCCESS);

        // Usar DatabaseConnection Singleton
        $db = DatabaseConnection::getInstance();
        $db->setTx($this->tx);

        $dayofweek = $dto->getDayofweek();
        $dayDate = $dto->getDate();

        $query = "SELECT COUNT(*) AS tieneBlock
            FROM " . SCHEMA_DB . ".`SCHEDULE_BLOCK` sb
            WHERE sb.`statusBlock` = 1
            AND 
            (
                (sb.`blockType` = 1 AND sb.`blockValue` = :dayofweek)
                OR
                (sb.`blockType` = 2 AND sb.`blockValue` = :dayDate)
            )";

        $params = [
            ':dayofweek' => $dayofweek,
            ':dayDate' => $dayDate
        ];

        $row = $db->getRow($query, $params);

        $tieneBlock = 0;
        if ($db->getError() === ERROR_CODE_SUCCESS) {
            $tieneBlock = (int)($row['tieneBlock'] ?? 0);
            $this->log->writeLog("$this->tx tieneBlock:($tieneBlock)\n");
            $this->set('tieneBlock', $tieneBlock);
        } else {
            $this->set('error', $db->getError());
            $this->set('errorDescription', $db->getErrorDescription());
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " end\n");
        return $tieneBlock;
    }

    /**
     * Se obtienen las fechas la carga de las ordenes desde mantenedor de autoagendamiento
     * 
     * @param CapacityDTO $dto Objeto con los datos necesarios
     * @return array Array de fechas
     */
    public function getDatesFromOrderToSchedule(CapacityDTO $dto): array
    {
        $this->log->writeLog("$this->tx " . __FUNCTION__ . "\n");
        $this->set('error', ERROR_CODE_SUCCESS);
        $this->set('errorDescription', ERROR_DESC_SUCCESS);

                // Usar DatabaseConnection Singleton
        $db = DatabaseConnection::getInstance();
        $db->setTx($this->tx);

        $id_order = $dto->getIdOrder();
        $query = "SELECT
            DISTINCT cd.`dateSelect`
            FROM `" . SCHEMA_DB . "`.`CARGAS_ORDERS` co
            JOIN `" . SCHEMA_DB . "`.`CARGAS_AUTOAGENDA` ca ON ca.`idCarga` = co.`idCarga`
            JOIN `" . SCHEMA_DB . "`.`CARGA_DATES` cd ON cd.`idCarga` = ca.`idCarga`
            WHERE co.`idOrder` = :id_order";

        $params = [':id_order' => $id_order];
        $dates = [];

        $result = $db->getData($query, $params);

        if ($db->getError() === ERROR_CODE_SUCCESS) {
            foreach ($result as $row) {
                $dates[] = $row['dateSelect'];
            }
            $this->set('dateSelect', $dates);
        } else {
            $this->set('error', $db->getError());
            $this->set('errorDescription', $db->getErrorDescription());
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " end\n");
        return $dates;
    }
}
