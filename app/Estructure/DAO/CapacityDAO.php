<?php
namespace App\Estructure\DAO;

use App\Estructure\BaseMethod;
use App\Utils\DatabaseConnection;

class CapacityDAO extends BaseMethod
{
    private $table = 'API_AUTH_WHATSAPP';
    private $tableBM = 'API_BOTMAKER';
    private $tableMessages = 'WHATSAPP_ANSWERS_MESSAGES';

    /**
     * Metodo que entrega capacidad de categoria para un pool
     * 
     * @return void
     */
    public function getCapacity()
    {
        $this->log->writeLog("$this->tx " . __FUNCTION__ . "\n");

        $this->set('error', ERROR_CODE_SUCCESS);
        $this->set('errorDescription', ERROR_DESC_SUCCESS);

        $data = $this->get('data');
        $arrayData = [];

        // Usar DatabaseConnection Singleton
        $db = DatabaseConnection::getInstance();
        $db->setTx($this->tx);

        $poolId = $this->get('poolId');
        $periodo = $this->get('periodo');
        $wherePeriodo = (!empty($periodo)) ? " AND perid.`Id` = :periodo " : "";

        $query =
            "SELECT 
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
    }

    public function getReservedQuota()
    {
        $this->log->writeLog("$this->tx " . __FUNCTION__ . "\n");

        $this->set('error', ERROR_CODE_SUCCESS);
        $this->set('errorDescription', ERROR_DESC_SUCCESS);

        // Usar DatabaseConnection Singleton
        $db = DatabaseConnection::getInstance();
        $db->setTx($this->tx);

        $categoryId = $this->get('categoryId');
        $date = $this->get('date');
        $poolId = $this->get('poolId');
        $periodo = $this->get('periodo');

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

        if ($db->getError() === ERROR_CODE_SUCCESS) {
            $reserved = isset($row['reserved']) ? $row['reserved'] : 0;
            $this->log->writeLog("$this->tx reserved:($reserved)\n");
            $this->set('reserved', $reserved);
        } else {
            $this->set('error', $db->getError());
            $this->set('errorDescription', $db->getErrorDescription());
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " end\n");
    }

    public function setReservedQuota()
    {
        $this->log->writeLog("$this->tx " . __FUNCTION__ . "\n");

        $this->set('error', ERROR_CODE_SUCCESS);
        $this->set('errorDescription', ERROR_DESC_SUCCESS);

        // Usar DatabaseConnection Singleton
        $db = DatabaseConnection::getInstance();
        $db->setTx($this->tx);

        $categoryId = $this->get('categoryId');
        $date = $this->get('date');
        $poolId = $this->get('poolId');
        $reserve = $this->get('requestedAmount');
        $minEntreViaje = $this->get('minEntreViaje');
        $periodo = $this->get('periodo');

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

    public function getScheduleBlock()
    {
        $this->log->writeLog("$this->tx " . __FUNCTION__ . "\n");
        $this->set('error', ERROR_CODE_SUCCESS);
        $this->set('errorDescription', ERROR_DESC_SUCCESS);

        // Usar DatabaseConnection Singleton
        $db = DatabaseConnection::getInstance();
        $db->setTx($this->tx);

        $dayofweek = $this->get('dayofweek');
        $dayDate = $this->get('date');

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

        if ($db->getError() === ERROR_CODE_SUCCESS) {
            $tieneBlock = $row['tieneBlock'] ?? 0;
            $this->log->writeLog("$this->tx tieneBlock:($tieneBlock)\n");
            $this->set('tieneBlock', $tieneBlock);
        } else {
            $this->set('error', $db->getError());
            $this->set('errorDescription', $db->getErrorDescription());
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " end\n");
    }

    /**
     * Se obtienen las fechas la carga de las ordenes desde mantenedor de autoagendamiento
     */
    public function getDatesFromOrderToSchedule()
    {
        $this->log->writeLog("$this->tx " . __FUNCTION__ . "\n");
        $this->set('error', ERROR_CODE_SUCCESS);
        $this->set('errorDescription', ERROR_DESC_SUCCESS);

        // Usar DatabaseConnection Singleton
        $db = DatabaseConnection::getInstance();
        $db->setTx($this->tx);

        $id_order = $this->get('id_order');
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
    }
}
