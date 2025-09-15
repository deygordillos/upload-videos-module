<?php
namespace App\Estructure\DAO;

use App\Estructure\BaseMethod;
use App\Utils\SDGConnectPDO;

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

        $this->log->writeLog("$this->tx " . USER_DB . "\n");
        $this->log->writeLog("$this->tx " . PASS_DB . "\n");
        $this->log->writeLog("$this->tx " . HOST_DB . "\n");
        $this->log->writeLog("$this->tx " . PORT_DB . "\n");
        $this->log->writeLog("$this->tx " . SCHEMA_DB . "\n");
        $this->log->writeLog("$this->tx connecting...\n");
        $mysqli = new \mysqli(HOST_DB, USER_DB, PASS_DB, SCHEMA_DB);
        if ($mysqli->connect_error) {
            $this->log->writeLog("$this->tx ERROR: " . $mysqli->connect_error . "\n");
            $this->set('error', ERROR_CODE_INTERNAL_SERVER);
            $this->set('errorDescription', ERROR_DESC_INTERNAL_SERVER);
        } else {
            $this->log->writeLog("$this->tx connected\n");
            $poolId  = $this->get('poolId');
            $periodo = $this->get('periodo');
            $wherePeriodo = (!empty($periodo)) ? " AND perid.`Id` = {$periodo} " : "";

            $query =
                "SELECT 
                        cc.Id AS 'categoryId',
                        cc.NameCategory AS 'categoryName',
                        cal.DayOfWeek,
                        GROUP_CONCAT(s.id SEPARATOR ',') AS idsSkills,
                        SUM(TIME_TO_SEC(SUBTIME(cal.EndTime, cal.StartTime))/60) AS 'quota'
                FROM ".SCHEMA_DB.".ROUTING_POOL pool
                JOIN ".SCHEMA_DB.".ROUTING_POOL poolchild ON poolchild.parentId = pool.idPool
                JOIN ".SCHEMA_DB.".REL_POOL_CAPACITY pc
                        ON pc.IdPool = poolchild.idPool
                JOIN ".SCHEMA_DB.".TRAZER_MAS_CATEGORIA_CAPACIDAD cc
                        ON cc.id = pc.IdCapacity
                JOIN ".SCHEMA_DB.".REL_CATEGORY_CAPACITY_SKILL rcc
                        ON rcc.idCategoryCapacity = cc.Id
                JOIN ".SCHEMA_DB.".TRAZER_MAS_SKILL s
                        ON s.id = rcc.idSkill
                JOIN ".SCHEMA_DB.".REL_CATEGORY_CAPACITY_PERIOD cc_perid
                        ON cc_perid.idCategoryCapacity = cc.id
                JOIN ".SCHEMA_DB.".TRAZER_MAS_PERIODOS perid
                        ON perid.id = cc_perid.idPeriod ".$wherePeriodo."
                JOIN ".SCHEMA_DB.".TRAZER_REL_DAY_OF_CALENDAR cal
                        ON cal.`IdCalendar` = pool.idCalendar AND perid.`startTime` BETWEEN cal.`StartTime` AND cal.`EndTime`
                        AND perid.`endTime` BETWEEN cal.`StartTime` AND cal.`EndTime`
                WHERE poolchild.idPool = $poolId
                GROUP BY 
                        categoryId,
                        cal.DayOfWeek

                    ";

            $this->log->writeLog("$this->tx " . $query . "\n");

            if (!$result = $mysqli->query($query)) {
                $this->log->writeLog("$this->tx QUERY ERROR: " . $mysqli->error . "\n");
                $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                $this->set('errorDescription', ERROR_DESC_INTERNAL_SERVER);
            } else {
                $this->set('rows', $result->num_rows);

                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
                        array_push($arrayData, $row);
                    }
                }

                $this->set('arrayData', $arrayData);
                $this->log->writeLog("$this->tx rowCount:" . $result->num_rows . "\n");
            }
            $mysqli->close();
        }
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " end\n");
    }

    public function getReservedQuota()
    {
        $this->log->writeLog("$this->tx " . __FUNCTION__ . "\n");

        $this->set('error', ERROR_CODE_SUCCESS);
        $this->set('errorDescription', ERROR_DESC_SUCCESS);

        $mysqli = new \mysqli(HOST_DB, USER_DB, PASS_DB, SCHEMA_DB);
        if ($mysqli->connect_error) {
            $this->log->writeLog("$this->tx ERROR: " . $mysqli->connect_error . "\n");
            $this->set('error', ERROR_CODE_INTERNAL_SERVER);
            $this->set('errorDescription', ERROR_DESC_INTERNAL_SERVER);
        } else {
            $this->log->writeLog("$this->tx connected\n");
            $categoryId = $this->get('categoryId');
            $date = $this->get('date');
            $data = $this->get('data');
            $poolId = $this->get('poolId');
            $periodo = $this->get('periodo');

            $query = "SELECT IF(SUM(reservedQuota + minEntreViaje) IS NOT NULL, 
            SUM(reservedQuota + minEntreViaje) , 0) AS 'reserved'
            FROM ".SCHEMA_DB.".QUOTA_RESERVED
            WHERE capacityCategoryId = $categoryId 
                AND reservedDate = '$date'
                AND poolId = $poolId
                AND periodo = '$periodo'";
            $this->log->writeLog("$this->tx query:($query)\n");
            if (!($result = $mysqli->query($query))) {
                $this->log->writeLog("$this->tx QUERY ERROR: " . $mysqli->error . "\n");
                $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                $this->set('errorDescription', ERROR_DESC_INTERNAL_SERVER);
            } else {
                $row = $result->fetch_assoc();
                $reserved = isset($row['reserved']) ? $row['reserved'] : 0;
                $this->log->writeLog("$this->tx reserved:($reserved)\n");
                $this->set('reserved', $reserved);
            }
            $mysqli->close();
        }
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " end\n");
    }

    public function setReservedQuota()
    {
        $this->log->writeLog("$this->tx " . __FUNCTION__ . "\n");

        $this->set('error', ERROR_CODE_SUCCESS);
        $this->set('errorDescription', ERROR_DESC_SUCCESS);

        $mysqli = new \mysqli(HOST_DB, USER_DB, PASS_DB, SCHEMA_DB);
        if ($mysqli->connect_error) {
            $this->log->writeLog("$this->tx ERROR: " . $mysqli->connect_error . "\n");
            $this->set('error', ERROR_CODE_INTERNAL_SERVER);
            $this->set('errorDescription', ERROR_DESC_INTERNAL_SERVER);
        } else {
            $this->log->writeLog("$this->tx connected\n");
            $categoryId = $this->get('categoryId');
            $date = $this->get('date');
            $poolId = $this->get('poolId');
            $reserve = $this->get('requestedAmount');
            $minEntreViaje = $this->get('minEntreViaje');
            $periodo = $this->get('periodo');
            $query = "INSERT INTO ".SCHEMA_DB.".QUOTA_RESERVED
                          (reservedDate, capacityCategoryId, reservedQuota, minEntreViaje, poolId, periodo)
                          VALUES('$date', $categoryId, $reserve, $minEntreViaje, $poolId, '$periodo')";
            if (!$mysqli->query($query)) {
                $this->log->writeLog("$this->tx QUERY ERROR: " . $mysqli->error . "\n");
                $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                $this->set('errorDescription', ERROR_DESC_INTERNAL_SERVER);
            }
            $mysqli->close();
        }
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " end\n");
    }

    public function getScheduleBlock()
    {
        $this->log->writeLog("$this->tx " . __FUNCTION__ . "\n");
        $this->set('error', ERROR_CODE_SUCCESS);
        $this->set('errorDescription', ERROR_DESC_SUCCESS);
        $mysqli = new \mysqli(HOST_DB, USER_DB, PASS_DB, SCHEMA_DB);
        if ($mysqli->connect_error) {
            $this->log->writeLog("$this->tx ERROR: " . $mysqli->connect_error . "\n");
            $this->set('error', ERROR_CODE_INTERNAL_SERVER);
            $this->set('errorDescription', ERROR_DESC_INTERNAL_SERVER);
        } else {
            $dayofweek   = $this->get('dayofweek');
            $dayDate  = $this->get('date');
         
            $query = "SELECT COUNT(*) AS tieneBlock
            FROM ".SCHEMA_DB.".`SCHEDULE_BLOCK` sb
            WHERE sb.`statusBlock` = 1
            AND 
            (
                (sb.`blockType` = 1 AND sb.`blockValue` = '".$dayofweek."')
                OR
                (sb.`blockType` = 2 AND sb.`blockValue` = '".$dayDate."')
            )";
            $this->log->writeLog("$this->tx query:($query)\n");
            if (!($result = $mysqli->query($query))) {
                $this->log->writeLog("$this->tx QUERY ERROR: " . $mysqli->error . "\n");
                $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                $this->set('errorDescription', ERROR_DESC_INTERNAL_SERVER);
            } else {
                $row = $result->fetch_assoc();
                $tieneBlock = isset($row['tieneBlock']) ? $row['tieneBlock'] : 0;
                $this->log->writeLog("$this->tx tieneBlock:($tieneBlock)\n");
                $this->set('tieneBlock', $tieneBlock);
            }
            $mysqli->close();
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
        $dates = [];
        $mysqli = new \mysqli(HOST_DB, USER_DB, PASS_DB, SCHEMA_DB);
        if ($mysqli->connect_error) {
            $this->log->writeLog("$this->tx ERROR: " . $mysqli->connect_error . "\n");
            $this->set('error', ERROR_CODE_INTERNAL_SERVER);
            $this->set('errorDescription', ERROR_DESC_INTERNAL_SERVER);
        } else {
            $id_order   = $this->get('id_order');         
            $query = "SELECT
            DISTINCT cd.`dateSelect`
            FROM `".SCHEMA_DB."`.`CARGAS_ORDERS` co
            JOIN `".SCHEMA_DB."`.`CARGAS_AUTOAGENDA` ca ON ca.`idCarga` = co.`idCarga`
            JOIN `".SCHEMA_DB."`.`CARGA_DATES` cd ON cd.`idCarga` = ca.`idCarga`
            WHERE co.`idOrder` = $id_order;";
            $this->log->writeLog("$this->tx query:($query)\n");
            if (!($result = $mysqli->query($query))) {
                $this->log->writeLog("$this->tx QUERY ERROR: " . $mysqli->error . "\n");
                $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                $this->set('errorDescription', ERROR_DESC_INTERNAL_SERVER);
            } else {
                
                while($row = $result->fetch_assoc()) {
                    $dates[] = $row['dateSelect'];
                }
                $this->set('dateSelect', $dates);
            }
            $mysqli->close();
        }
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " end\n");
    }
}
