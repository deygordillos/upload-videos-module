<?php

namespace App\Estructure\BLL;

use App\Estructure\BaseMethod;
use App\Estructure\DAO\CapacityDAO;
use App\Estructure\DAO\ConfigurationsDAO;
use App\Estructure\DTO\CapacityDTO;
use App\Utils\DayLog;

class CapacityBLL extends BaseMethod
{
    private $DAO;
    public $request; // request api

    public function __construct()
    {
        $this->DAO = new CapacityDAO();
    }

    /**
     * Funcion principal para la consulta de capacidad
     * 
     * @param object $body
     */
    public function getCapacity($body)
    {
        $this->log = new DayLog(API_HOME_PATH, 'API_' . __CLASS__ . '_' . __FUNCTION__);
        $this->tx = substr(uniqid(), 5);
        $this->DAO->set('log', $this->log);
        $this->DAO->set('tx', $this->tx);

        $this->camposRequired = [
            'id_pool' => ['type' => 'integer'],
            'fechas' => ['type' => 'string'],
            'cantidad' => ['type' => 'integer']
        ];
        $this->camposAvailables = array_keys($this->camposRequired);
        $this->camposAvailables[] = 'id_order';
        $this->camposAvailables[] = 'periodo';

        // Valido campos request
        $body = $this->validRequestFields($body);
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Request: " . str_replace(array("\n", "\r", "\t"), array('', '', ''), print_r($body, true)) . "\n");
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " required: " . print_r($this->required, true) . "\n");

        // Valido el request POST
        $this->validMethodPOST($body, $this->required, $this->notAvailables, $this->fieldsNotVarType);

        $capacityResult = [];

        // Si la validación del request está OK
        if ($this->get('error') === ERROR_CODE_SUCCESS) {
            $dates = $body->fechas;
            $arrayDates = explode(',', $dates);

            // Crear DTO para getCapacity
            $capacityDTO = new CapacityDTO([
                'poolId' => (int) $body->id_pool,
                'id_order' => (isset($body->id_order) ? (int) $body->id_order : 0),
                'periodo' => isset($body->periodo) ? trim(addslashes($body->periodo)) : '',
                'data' => $body,
                'log' => $this->log,
                'tx' => $this->tx
            ]);

            $arrayCategory = $this->DAO->getCapacity($capacityDTO);

            $arrayFechaCategory = [];
            if ($this->DAO->get('error') == ERROR_CODE_SUCCESS) {

                $this->log->writeLog("$this->tx cant minutos orden:(" . $body->cantidad . ")\n");

                //////////////////////////////
                // Obtengo la configuración de horas de viaje entre ordenes
                $configDAO = new ConfigurationsDAO();
                $configDAO->set('tx', $this->tx);
                $configDAO->set('log', $this->log);
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
                        'id_order' => $capacityDTO->getIdOrder(),
                        'log' => $this->log,
                        'tx' => $this->tx
                    ]);
                    $arrayDatesOrder = $this->DAO->getDatesFromOrderToSchedule($orderDTO);
                    $this->log->writeLog("$this->tx arrayDatesOrder: " . print_r($arrayDatesOrder, true) . "\n");
                }

                foreach ($arrayDates as $date) {
                    $this->log->writeLog("$this->tx :::::::::::\n");
                    $dayofweek = date('w', strtotime($date));
                    
                    $scheduleDTO = new CapacityDTO([
                        'dayofweek' => $dayofweek,
                        'date' => $date,
                        'log' => $this->log,
                        'tx' => $this->tx
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
                                    'periodo' => $capacityDTO->getPeriodo(),
                                    'log' => $this->log,
                                    'tx' => $this->tx
                                ]);
                                
                                $reserved = $this->DAO->getReservedQuota($reservedQuotaDTO); // obtiene la quota reservada para la fecha, categoria y pool especificados
                                if ($this->DAO->get('error') == ERROR_CODE_SUCCESS) {
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
                $this->set('error', $this->DAO->get('error'));
                $this->set('errorDescription', $this->DAO->get('errorDescription'));
            }
        }

        // Generar respuesta JSON usando el método reutilizable
        $json = $this->generateJsonResponse($capacityResult);

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Response: " . print_r(json_encode($json), true) . "\n");
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Fin \n\n");

        return $json;
    }

    /**
     * Funcion para agendar una determinada capacidad
     * 
     * @param object $body
     */
    public function schedule($body)
    {
        $this->log = new DayLog(API_HOME_PATH, 'API_' . __CLASS__ . '_' . __FUNCTION__);
        $this->tx = substr(uniqid(), 5);
        $this->DAO->set('log', $this->log);
        $this->DAO->set('tx', $this->tx);

        $this->camposRequired = [
            'fecha' => ['type' => 'string'],
            'periodo' => ['type' => 'string'],
            'cantidad' => ['type' => 'integer'],
            'id_pool' => ['type' => 'integer']
        ];
        $this->camposAvailables = array_keys($this->camposRequired);

        // Valido campos request
        $body = $this->validRequestFields($body);
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " " . str_replace(array("\n", "\r", "\t"), array('', '', ''), print_r($body, true)) . "\n");
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " required: " . print_r($this->required, true) . "\n");

        // Valido el request POST
        $this->validMethodPOST($body, $this->required, $this->notAvailables, $this->fieldsNotVarType);

        // Si la validación del request está OK
        if ($this->get('error') === ERROR_CODE_SUCCESS) {
            $dayofweek = date('w', strtotime($body->fecha)); // dia de la semana de la fecha ingresada
            $this->log->writeLog("$this->tx fecha:{$body->fecha} dayofweek:{$dayofweek}\n");

            // Crear DTO para getCapacity
            $capacityDTO = new CapacityDTO([
                'poolId' => (int) $body->id_pool,
                'periodo' => trim(addslashes($body->periodo)),
                'date' => $body->fecha,
                'data' => $body,
                'log' => $this->log,
                'tx' => $this->tx
            ]);

            $arrayCategory = $this->DAO->getCapacity($capacityDTO);
            if ($this->DAO->get('error') == ERROR_CODE_SUCCESS) {

                $this->log->writeLog("$this->tx arrayCategory count:(" . count($arrayCategory) . ")\n");
                if (count($arrayCategory) == 0) {
                    $this->set('error', ERROR_CODE_NOT_FOUND);
                    $this->set('errorDescription', ERROR_DESC_NOT_FOUND);
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
                                'periodo' => $capacityDTO->getPeriodo(),
                                'log' => $this->log,
                                'tx' => $this->tx
                            ]);
                            
                            $currentReserved = $this->DAO->getReservedQuota($reservedQuotaDTO);
                            if ($this->DAO->get('error') == ERROR_CODE_SUCCESS) {
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
                        $configDAO = new ConfigurationsDAO();
                        $configDAO->set('tx',$this->tx);
                        $configDAO->set('log', $this->log);
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
                                    'requestedAmount' => $minOrder,
                                    'minEntreViaje' => $minEntreViaje,
                                    'periodo' => $capacityDTO->getPeriodo(),
                                    'log' => $this->log,
                                    'tx' => $this->tx
                                ]);
                                
                                $this->DAO->setReservedQuota($setReservedDTO);
                                $this->set('error', $this->DAO->get('error'));
                                $this->set('errorDescription', $this->DAO->get('errorDescription'));
                                $this->log->writeLog("$this->tx setReservedQuota:" . $this->get('error') . "\n");
                            }

                        } else {
                            $this->set('error', ERROR_CODE_BAD_REQUEST);
                            $this->set('errorDescription', 'No hay capacidad suficiente disponible');
                            $this->log->writeLog("$this->tx No hay capacidad para categoria especificada\n");
                        }

                        $this->log->writeLog("$this->tx reservado:(" . $reserved . ")\n");
                        $this->log->writeLog("$this->tx disponible:(" . $available . ")\n");
                    }
                }
            } else {
                $this->set('error', $this->DAO->get('error'));
                $this->set('errorDescription', $this->DAO->get('errorDescription'));
                $this->log->writeLog("$this->tx " . __FUNCTION__ . " ERROR: " . $this->DAO->get('error') . " " . $this->DAO->get('errorDescription') . "\n");
            }
        }

        // Generar respuesta JSON usando el método reutilizable
        $json = $this->generateJsonResponse();

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Response: " . print_r(json_encode($json), true) . "\n");
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Fin \n\n");

        return $json;
    }
}
