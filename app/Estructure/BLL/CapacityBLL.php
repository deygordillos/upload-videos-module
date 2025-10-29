<?php

namespace App\Estructure\BLL;

use App\Estructure\BaseMethod;
use App\Estructure\DAO\CapacityDAO;
use App\Estructure\DAO\ConfigurationsDAO;
use App\Estructure\DTO\CapacityDTO;
use App\Utils\DayLog;
use App\Utils\DatabaseConnection;

class CapacityBLL extends BaseMethod
{
    private $DAO;
    private $db;
    public $request; // request api

    public function __construct()
    {
        // Crear instancia de DatabaseConnection para inyección de dependencias
        $this->db = new DatabaseConnection();
        $this->DAO = new CapacityDAO($this->db);
        $this->log = new DayLog(API_HOME_PATH, 'API_CAPACITY');
        $this->tx = substr(uniqid(), 5);

        $this->DAO->setLog($this->log);
        $this->DAO->setTx($this->tx);
    }

    /**
     * Destructor que automáticamente cierra la conexión DB
     * Se ejecuta cuando el objeto BLL se destruye al final del request
     */
    public function __destruct()
    {
        if ($this->db) {
            $this->db->closeConnection();
        }
    }

    /**
     * Funcion principal para la consulta de capacidad
     * 
     * @param object $body
     */
    public function getCapacity($body)
    {
        try {
            $this->camposRequired = [
                'id_pool' => ['type' => 'integer'],
                'fechas' => ['type' => 'string'],
                'cantidad' => ['type' => 'integer']
            ];
            $this->camposAvailables = array_merge(array_keys($this->camposRequired), [
                'id_order',
                'periodo',
                'calcula_duracion',
                'calcula_habilidades',
                'propiedad_custom'
            ]);

            // Valido campos request
            $body = $this->validRequestFields($body);
            $this->log->writeLog("$this->tx " . __FUNCTION__ . " request: " . print_r(json_encode($body), true) . "\n");

            // Valido el request POST
            $this->validMethodPOST($body, $this->required, $this->notAvailables, $this->fieldsNotVarType);

            $capacityResult = [];

            // Si la validación del request está OK
            if ($this->error === ERROR_CODE_SUCCESS) {
                $dates = $body->fechas;
                $arrayDates = explode(',', $dates);

                // Crear DTO para getCapacity
                $capacityDTO = new CapacityDTO([
                    'poolId' => (int) $body->id_pool,
                    'id_order' => (isset($body->id_order) ? (int) $body->id_order : 0),
                    'periodo' => isset($body->periodo) ? trim(addslashes($body->periodo)) : '',
                    'data' => (array) $body // Convertir objeto a array
                ]);

                $arrayCategory = $this->DAO->getCapacity($capacityDTO);

                $arrayFechaCategory = [];
                if ($this->DAO->getError() == ERROR_CODE_SUCCESS) {

                    $this->log->writeLog("$this->tx cant minutos orden:(" . $body->cantidad . ")\n");

                    //////////////////////////////
                    // Obtengo la configuración de horas de viaje entre ordenes
                    $configDAO = new ConfigurationsDAO($this->db);
                    $configDAO->setTx($this->tx);
                    $configDAO->setLog($this->log);
                    $dataMinEntreViaje = $configDAO->getConfigByName('MIN_VIAJE_CLIENTE_ENTRE_ORDEN');
                    $minEntreViaje = (int) $dataMinEntreViaje['valor'] ?? 0;
                    $this->log->writeLog("$this->tx minEntreViaje: " . print_r($minEntreViaje, true) . "\n");
                    //////////////////////////////

                    //////////////////////////////
                    // Si se envía id_orden, se evalua las fechas
                    // seleccionadas en el autoagendamiento
                    //////////////////////////////
                    $arrayDatesOrder = [];
                    if (!empty($capacityDTO->getIdOrder())) {
                        $orderDTO = new CapacityDTO([
                            'id_order' => $capacityDTO->getIdOrder()
                        ]);
                        $arrayDatesOrder = $this->DAO->getDatesFromOrderToSchedule($orderDTO);
                        $this->log->writeLog("$this->tx arrayDatesOrder: " . print_r($arrayDatesOrder, true) . "\n");
                    }

                    foreach ($arrayDates as $date) {
                        $this->log->writeLog("$this->tx :::::::::::\n");
                        $dayofweek = date('w', strtotime($date));

                        $scheduleDTO = new CapacityDTO([
                            'dayofweek' => $dayofweek,
                            'date' => $date
                        ]);

                        $tieneBlock = $this->DAO->getScheduleBlock($scheduleDTO);

                        // Si no tiene bloqueo de fecha
                        if ($tieneBlock == 0) {
                            // Si se envía la orden, se evalúa las fechas obtenidas de la carga
                            // Si no es de las seleccionadas, no obtiene capacidad
                            if (!empty($capacityDTO->getIdOrder()) && !empty($arrayDatesOrder)) {
                                if (!in_array($date, $arrayDatesOrder)) {
                                    continue;
                                }
                            }

                            $this->log->writeLog("$this->tx Revisando fecha:($date) dateOfWeek:($dayofweek)\n");
                            foreach ($arrayCategory as $row) {
                                $this->log->writeLog("$this->tx fecha:($date) dayofweek:($dayofweek) DayOfWeekCalendar:({$row['DayOfWeek']})\n");
                                if ($dayofweek == $row['DayOfWeek']) {
                                    $this->log->writeLog("$this->tx " . $row['categoryId'] . " OK Coincide fecha con calendar del tecnico\n");

                                    $reservedQuotaDTO = new CapacityDTO([
                                        'categoryId' => $row['categoryId'],
                                        'date' => $date,
                                        'poolId' => $capacityDTO->getPoolId(),
                                        'periodo' => $capacityDTO->getPeriodo()
                                    ]);

                                    $reserved = $this->DAO->getReservedQuota($reservedQuotaDTO); // obtiene la quota reservada para la fecha, categoria y pool especificados
                                    if ($this->DAO->getError() == ERROR_CODE_SUCCESS) {
                                        $cuota = (float) $row['quota'];
                                        $keyReserva = $row['categoryId'] . $date;
                                        $this->log->writeLog("$this->tx KeyReserva " . $row['categoryId'] . " fecha:($keyReserva) " . print_r($arrayFechaCategory, true) . "\n");
                                        if (!in_array($keyReserva, $arrayFechaCategory)) {
                                            array_push(
                                                $capacityResult,
                                                array(
                                                    'fecha' => $date,
                                                    'categoria' => $row['categoryId'],
                                                    'nombre' => $row['categoryName'],
                                                    'idskills' => $row['idsSkills'],
                                                    'quota' => $cuota,
                                                    'reservada' => $reserved,
                                                    'disponible' => $cuota - $reserved - $minEntreViaje - $body->cantidad
                                                )
                                            );
                                            $arrayFechaCategory[] = $keyReserva;
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $this->error = $this->DAO->getError();
                    $this->errorDescription = $this->DAO->getErrorDescription();
                }
            }

            // Generar respuesta JSON usando el método reutilizable
            $json = $this->setBuildResponse($capacityResult);
            $this->log->writeLog("$this->tx " . __FUNCTION__ . " response: " . print_r(json_encode($json), true) . "\n");
            return $json;

        } catch (\Throwable $e) {
            // Manejar cualquier error no controlado
            $this->log->writeLog("$this->tx " . __FUNCTION__ . " EXCEPTION: " . $e->getMessage() . "\n");
            $this->error = ERROR_CODE_INTERNAL_SERVER;
            $this->errorDescription = "Error interno del servidor";
            return $this->setBuildResponse();
        } finally {
            if ($this->db) {
                $this->db->closeConnection();
            }
        }
    }

    /**
     * Funcion para agendar una determinada capacidad
     * 
     * @param object $body
     */
    public function schedule($body)
    {
        try {
            $this->camposRequired = [
                'fecha' => ['type' => 'string'],
                'periodo' => ['type' => 'integer'],
                'cantidad' => ['type' => 'integer'],
                'id_pool' => ['type' => 'integer'],
            ];
            $this->camposAvailables = array_keys($this->camposRequired);

            // Valido campos request
            $body = $this->validRequestFields($body);
            $this->log->writeLog("$this->tx " . __FUNCTION__ . " request: " . print_r(json_encode($body), true) . "\n");

            // Valido el request POST
            $this->validMethodPOST($body, $this->required, $this->notAvailables, $this->fieldsNotVarType);

            // Si la validación del request está OK
            if ($this->error === ERROR_CODE_SUCCESS) {
                $dayofweek = date('w', strtotime($body->fecha)); // dia de la semana de la fecha ingresada
                $this->log->writeLog("$this->tx fecha:{$body->fecha} dayofweek:{$dayofweek}\n");

                // Crear DTO para getCapacity
                $capacityDTO = new CapacityDTO([
                    'poolId' => (int) $body->id_pool,
                    'periodo' => trim(addslashes($body->periodo)),
                    'date' => $body->fecha,
                    'data' => (array) $body // Convertir objeto a array
                ]);

                $arrayCategory = $this->DAO->getCapacity($capacityDTO);
                if ($this->DAO->getError() == ERROR_CODE_SUCCESS) {

                    $this->log->writeLog("$this->tx arrayCategory count:(" . count($arrayCategory) . ")\n");
                    if (count($arrayCategory) == 0) {
                        $this->error = ERROR_CODE_NOT_FOUND;
                        $this->errorDescription = ERROR_DESC_NOT_FOUND;
                        $this->log->writeLog("$this->tx No hay categoria para el id especificado\n");
                    } else {
                        $quota = 0;
                        $reserved = 0;
                        $sumQuota = 0;
                        $sumReserva = 0;
                        $iCapategory = 0;
                        $this->log->writeLog("$this->tx Buscando la capacidad de la categoria\n");
                        $arrayIdCategories = [];
                        foreach ($arrayCategory as $row) {
                            if ($dayofweek == $row['DayOfWeek']) {
                                $currentQuota = $row['quota'];
                                $sumQuota += (int) $currentQuota;

                                $reservedQuotaDTO = new CapacityDTO([
                                    'categoryId' => $row['categoryId'],
                                    'date' => $body->fecha,
                                    'poolId' => $capacityDTO->getPoolId(),
                                    'periodo' => $capacityDTO->getPeriodo()
                                ]);

                                $currentReserved = $this->DAO->getReservedQuota($reservedQuotaDTO);
                                if ($this->DAO->getError() == ERROR_CODE_SUCCESS) {
                                    $sumReserva += (int) $currentReserved;
                                }

                                $arrayIdCategories[] = $row['categoryId'];
                                $iCapategory++;
                            }
                        }

                        if ($iCapategory > 0) {
                            $quota = ($sumQuota > 0) ? ($sumQuota / $iCapategory) : 0;
                            $reserved = ($sumReserva > 0) ? ($sumReserva / $iCapategory) : 0;

                            $this->log->writeLog("$this->tx quota:(" . $quota . ")\n");
                            $this->log->writeLog("$this->tx cant min. orden:(" . $body->cantidad . ")\n");

                            //////////////////////////////
                            // Obtengo la configuración de horas de viaje entre ordenes
                            $configDAO = new ConfigurationsDAO($this->db);
                            $configDAO->setTx($this->tx);
                            $configDAO->setLog($this->log);
                            $dataMinEntreViaje = $configDAO->getConfigByName('MIN_VIAJE_CLIENTE_ENTRE_ORDEN');
                            $minEntreViaje = (int) $dataMinEntreViaje['valor'] ?? 0;
                            $minEntreViaje = $minEntreViaje / $iCapategory;
                            $this->log->writeLog("$this->tx minEntreViaje: " . print_r($minEntreViaje, true) . "\n");
                            //////////////////////////////

                            $available = $quota - $reserved - $minEntreViaje;
                            if ($available > 0 && $available >= $body->cantidad) {
                                $minOrder = $body->cantidad / $iCapategory;
                                foreach ($arrayIdCategories as $idCategory) {
                                    $setReservedDTO = new CapacityDTO([
                                        'categoryId' => $idCategory,
                                        'date' => $body->fecha,
                                        'poolId' => $capacityDTO->getPoolId(),
                                        'requestedAmount' => $body->cantidad,
                                        'minEntreViaje' => isset($body->minEntreViaje) ? (int) $body->minEntreViaje : 0,
                                        'periodo' => $capacityDTO->getPeriodo()
                                    ]);

                                    $this->DAO->setReservedQuota($setReservedDTO);
                                    $this->error = $this->DAO->getError();
                                    $this->errorDescription = $this->DAO->getErrorDescription();
                                    $this->log->writeLog("$this->tx setReservedQuota:" . $this->error . "\n");
                                }

                            } else {
                                $this->error = ERROR_CODE_BAD_REQUEST;
                                $this->errorDescription = 'No hay capacidad suficiente disponible';
                                $this->log->writeLog("$this->tx No hay capacidad para categoria especificada\n");
                            }

                            $this->log->writeLog("$this->tx reservado:(" . $reserved . ")\n");
                            $this->log->writeLog("$this->tx disponible:(" . $available . ")\n");
                        }
                    }
                } else {
                    $this->error = $this->DAO->getError();
                    $this->errorDescription = $this->DAO->getErrorDescription();
                    $this->log->writeLog("$this->tx " . __FUNCTION__ . " ERROR: " . $this->DAO->getError() . " " . $this->DAO->getErrorDescription() . "\n");
                }
            }

            $json = $this->setBuildResponse();
            $this->log->writeLog("$this->tx " . __FUNCTION__ . " response: " . print_r(json_encode($json), true) . "\n");
            return $json;

        } catch (\Throwable $e) {
            // Manejar cualquier error no controlado
            $this->log->writeLog("$this->tx " . __FUNCTION__ . " EXCEPTION: " . $e->getMessage() . " " . $e->getLine() . "\n");
            $this->error = ERROR_CODE_INTERNAL_SERVER;
            $this->errorDescription = "Error interno del servidor";
            return $this->setBuildResponse();
        } finally {
            // GARANTIZAR cierre de conexión sin importar qué pase
            if ($this->db) {
                $this->db->closeConnection();
            }
        }
    }
}
