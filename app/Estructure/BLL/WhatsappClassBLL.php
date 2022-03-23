<?php

namespace App\Estructure\BLL;

use App\Estructure\BLL\BaseMethod;
use App\Estructure\DAO\WhatsappClassDAO;
use App\Utils\DayLog;

class WhatsappClassBLL extends BaseMethod
{
    public $request; // request api
    public function __construct()
    {
        $this->DAO = new WhatsappClassDAO();
    }

    /**
     * Actualizar el token bearer de Whatsapp Macrobot
     */
    public function updateTokenWhatsapp()
    {
        ini_set('log_errors', true);
        ini_set('error_log', API_HOME_PATH . '/log/API_' . __CLASS__ . '_' . __FUNCTION__ . '-' . date('Ymd') . '.log');
        $this->log = new DayLog(API_HOME_PATH, 'API_' . __CLASS__ . '_' . __FUNCTION__);
        $this->tx = substr(uniqid(), 5);
        $this->log->writeLog("$this->tx Init " . get_class() . "." . __FUNCTION__ . " ::: \n");

        $this->DAO->set('log', $this->log);
        $this->DAO->set('tx',  $this->tx);

        // Enviando notificacion a Whatstapp
        $dataToken = $this->DAO->getTokenMacrobotWhatsapp();
        if ($this->DAO->get('error') != ERROR_CODE_SUCCESS) {
            $this->set('error', $this->DAO->get('error'));
            $this->set('errorDescription', $this->DAO->get('errorDescription'));
        } else {
            $tokenNew = $dataToken->token;
            // Inactivo el token actual
            $this->DAO->inactivateCurrentToken();
            if ($this->DAO->get('error') != ERROR_CODE_SUCCESS) {
                $this->set('error', $this->DAO->get('error'));
                $this->set('errorDescription', $this->DAO->get('errorDescription'));
            } else {
                $this->DAO->addNewToken($tokenNew);
                if ($this->DAO->get('error') != ERROR_CODE_SUCCESS) {
                    $this->set('error', $this->DAO->get('error'));
                    $this->set('errorDescription', $this->DAO->get('errorDescription'));
                } else {
                    $this->set('error', ERROR_CODE_SUCCESS);
                    $this->set('errorDescription', ERROR_DESC_SUCCESS);
                }
            }
        }


        $json = new \stdClass();
        $json->status           = new \stdClass();
        $json->status->code     = $this->get('error');
        $json->status->message  = $this->get('errorDescription');

        if ($this->get('error') === ERROR_CODE_SUCCESS && $this->get('data')) {
            $json->data = $this->get('data');
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Return: " . print_r(json_encode($json), true) . " \n");
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Fin \n\n");
        return $json;
    }

    public function sendMessageWhastapp($body)
    {
        ini_set('log_errors', true);
        ini_set('error_log', API_HOME_PATH . '/log/API_' . __CLASS__ . '_' . __FUNCTION__ . '-' . date('Ymd') . '.log');
        $this->log = new DayLog(API_HOME_PATH, 'API_' . __CLASS__ . '_' . __FUNCTION__);
        $this->tx = substr(uniqid(), 5);
        $this->DAO->set('log', $this->log);
        $this->DAO->set('tx',  $this->tx);

        $this->camposRequired = [
            'whatsappNumber'      => ['type' => 'integer'],
            'messageToSend'       => ['type' => 'string']
        ];
        $this->camposAvailables   = array_keys($this->camposRequired);
        $this->camposAvailables[] = 'wsBy';
        
        // Valido campos request
        $body = $this->validRequestFields($body);
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Request: " . print_r($body, true) . "\n");
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " required: " . print_r($this->required, true) . "\n");
        // Valido el request POST
        $this->validMethodPOST($body, $this->required, $this->notAvailables, $this->fieldsNotVarType);
        // Si la validación del request está OK
        if ($this->get('error') === ERROR_CODE_SUCCESS) {
            $this->validateWhatsappRequest($body);
            if ($this->get('error') === ERROR_CODE_SUCCESS) {
                // Enviando notificacion a Whatstapp
                $this->log->writeLog("$this->tx Enviando notificacion a Whatstapp \n");
                $this->DAO->sendMessageWhastapp($body);
                if ($this->DAO->get('error') != ERROR_CODE_SUCCESS) {
                    $this->set('error', $this->DAO->get('error'));
                    $this->set('errorDescription', $this->DAO->get('errorDescription'));
                } else {
                    $this->set('error', ERROR_CODE_SUCCESS);
                    $this->set('errorDescription', 'Mensaje enviado correctamente.');
                }
            }
        }

        $json = new \stdClass();
        $json->status           = new \stdClass();
        $json->status->code     = $this->get('error');
        $json->status->message  = $this->get('errorDescription');

        if ($this->get('error') === ERROR_CODE_SUCCESS && $this->get('data')) {
            $json->data = $this->get('data');
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Return: " . print_r(json_encode($json), true) . " \n");
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Fin \n\n");
        return $json;
    }

    /**
     * Validar campos del request de whastapp
     */
    private function validateWhatsappRequest($body)
    {        
        if (isset($body->whatsappNumber) && !preg_match('/^[0-9]{11,13}$/', $body->whatsappNumber)) {
            // Formato del fono inválido
            $this->set('error', ERROR_CODE_BAD_REQUEST);
            $this->set('errorDescription', "Formato de -whatsappNumber- debe ser [56123456789]");
            return FALSE;
        }

        $this->set('error', ERROR_CODE_SUCCESS);
        $this->set('errorDescription', ERROR_DESC_SUCCESS);
        return TRUE;
    }


    public function saveMessageWhastapp($body)
    {
        ini_set('log_errors', true);
        ini_set('error_log', API_HOME_PATH . '/log/API_' . __CLASS__ . '_' . __FUNCTION__ . '-' . date('Ymd') . '.log');
        $this->log = new DayLog(API_HOME_PATH, 'API_' . __CLASS__ . '_' . __FUNCTION__);
        $this->tx = substr(uniqid(), 5);
        $this->DAO->set('log', $this->log);
        $this->DAO->set('tx',  $this->tx);

        $this->camposRequired = [
            'whatsappNumber'      => ['type' => 'string'],
            'message'       => ['type' => 'string']
        ];
        $this->camposAvailables = array_keys($this->camposRequired);
        // Valido campos request
        $body = $this->validRequestFields($body);
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Request: " . print_r($body, true) . "\n");
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " required: " . print_r($this->required, true) . "\n");
        // Valido el request POST
        $this->validMethodPOST($body, $this->required, $this->notAvailables, $this->fieldsNotVarType);
        // Si la validación del request está OK
        if ($this->get('error') === ERROR_CODE_SUCCESS) {
            $this->validateWhatsappRequest($body);
            if ($this->get('error') === ERROR_CODE_SUCCESS) {
                // Enviando notificacion a Whatstapp
                $this->log->writeLog("$this->tx Enviando notificacion a Whatstapp \n");
                $this->DAO->saveMessageWhastapp($body);
                if ($this->DAO->get('error') != ERROR_CODE_SUCCESS) {
                    $this->set('error', $this->DAO->get('error'));
                    $this->set('errorDescription', $this->DAO->get('errorDescription'));
                } else {
                    // Ejecuto agendamiento según respuesta del cliente en conversación abierta
                    $this->DAO->execAgendamientoDFEEDByWhatsapp($body);

                    // A producción
                    $this->DAO->execAgendamientoDFEEDByWhatsappProduction($body);

                    $this->set('error', ERROR_CODE_SUCCESS);
                    $this->set('errorDescription', 'Mensaje recibido correctamente.');
                }
            }
        }

        $json = new \stdClass();
        $json->status           = new \stdClass();
        $json->status->code     = $this->get('error');
        $json->status->message  = $this->get('errorDescription');

        if ($this->get('error') === ERROR_CODE_SUCCESS && $this->get('data')) {
            $json->data = $this->get('data');
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Return: " . print_r(json_encode($json), true) . " \n");
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Fin \n\n");
        return $json;
    }
}
