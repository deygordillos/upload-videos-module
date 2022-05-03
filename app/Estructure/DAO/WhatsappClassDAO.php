<?php
namespace App\Estructure\DAO;

use App\Estructure\BLL\BaseMethod;
use App\Utils\SDGConnectPDO;
use stdClass;

class WhatsappClassDAO extends BaseMethod
{
    private $table = 'API_AUTH_WHATSAPP';
    private $tableBM = 'API_BOTMAKER';
    private $tableMessages = 'WHATSAPP_ANSWERS_MESSAGES';

    public function getTokenMacrobotWhatsapp()
    {
        $ch = curl_init();
        $request = new \stdClass();
        $request->email    = USER_WHATSAPP_API;
        $request->password = PASS_WHATSAPP_API;

        curl_setopt_array($ch, array(
            CURLOPT_URL => URL_WHATSAPP_API . "auth/login",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($request),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));

        $response = curl_exec($ch);
        $this->log->writeLog("$this->tx response: " . print_r($response, true) . " \n");
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $responseDecode = null;
        if ($errno) {
            $this->set('error', ERROR_CODE_INTERNAL_SERVER);
            $this->set('errorDescription',  $errno . ' ' . $error);
        } else {
            $responseDecode = json_decode($response);
            if ((int)$http_status === 200) {
                $this->set('error', ERROR_CODE_SUCCESS);
                $this->set('errorDescription', ERROR_DESC_SUCCESS);
            } elseif ((int)$http_status === 500) {
                $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                $this->set('errorDescription', $responseDecode->status);
            } else {
                $this->set('error', $http_status);
                $this->set('errorDescription', $responseDecode->msg);
            }
        }

        $this->log->writeLog("$this->tx fin " . get_class() . " " . __FUNCTION__ . "\n");
        return $responseDecode;
    }


    /**
     * Inactiva el token activo de macrobot
     */
    public function inactivateCurrentToken() {
        $this->log->writeLog("{$this->tx} Init " . __FUNCTION__ . "\n");
        $sdgpdo = new SDGConnectPDO(USER_DB, PASS_DB, SCHEMA_DB, HOST_DB, PORT_DB);
        $sdgpdo->setLog($this->log);
        $sdgpdo->setTx($this->tx);
        $sql = "UPDATE `{$this->table}` au
        SET au.status = '0'
        WHERE au.status = '1';";
        $sdgpdo->setQuery($sql);
        $sdgpdo->setParams([]);
        $modicated = $sdgpdo->modifyRow();
        $this->set('error', $sdgpdo->get('error'));
        $this->set('errorDescription', $sdgpdo->get('errorDescription'));
        $this->log->writeLog("{$this->tx} End " . __FUNCTION__ . "\n");
        return $modicated;
    }

    /**
     * Añade un token nuevo de macrobot
     */
    public function addNewToken($token = '') {
        
        $this->log->writeLog("{$this->tx} Init " . __FUNCTION__ . "\n");
        $sdgpdo = new SDGConnectPDO(USER_DB, PASS_DB, SCHEMA_DB, HOST_DB, PORT_DB);
        $sdgpdo->setLog($this->log);
        $sdgpdo->setTx($this->tx);
        $createdAt = date("Y-m-d H:i:s");
        
        $query = "INSERT INTO `{$this->table}`
        (`token`, `createdAt`, `status`)
        VALUES  (:token, :createdAt, :currentStatus) ;";

        $sdgpdo->setQuery($query);
        $sdgpdo->setParams([
            ":token" => $token,
            ":createdAt" => $createdAt,
            ":currentStatus" => '1'
        ]);
        $newId = $sdgpdo->addRow();

        $this->set('error', $sdgpdo->get('error'));
        $this->set('errorDescription', $sdgpdo->get('errorDescription'));
        return $newId;
    }

    /**
     * Obtiene token bearer actual de macrobot
     */
    public function getTokenWhatsapp() {
        $sdgpdo = new SDGConnectPDO(USER_DB, PASS_DB, SCHEMA_DB, HOST_DB, PORT_DB);
        $sdgpdo->setLog($this->log);
        $sdgpdo->setTx($this->tx);
        $sdgpdo->setQuery("SELECT token 
        FROM {$this->table} au
        WHERE au.status = :currentStatus 
        LIMIT 1;");
        $sdgpdo->setParams([
            ":currentStatus" => '1'
        ]);
        $data = $sdgpdo->getRow();
        $this->set('error', $sdgpdo->get('error') );
        $this->set('errorDescription', $sdgpdo->get('errorDescription') );
        return $data['token'] ?? '';
    }

    /**
     * Obtiene access token bearer actual de botMaker
     */
    public function getTokenWhatsappBotMaker() {
        $sdgpdo = new SDGConnectPDO(USER_DB, PASS_DB, SCHEMA_DB, HOST_DB, PORT_DB);
        $sdgpdo->setLog($this->log);
        $sdgpdo->setTx($this->tx);
        $sdgpdo->setQuery("SELECT accessToken 
        FROM {$this->tableBM} apibm
        WHERE apibm.statusToken = :currentStatus 
        LIMIT 1;");
        $sdgpdo->setParams([
            ":currentStatus" => '1'
        ]);
        $data = $sdgpdo->getRow();
        $this->set('error', $sdgpdo->get('error') );
        $this->set('errorDescription', $sdgpdo->get('errorDescription') );
        return $data['accessToken'] ?? '';
    }

    
    public function sendMessageWhastapp($body)
    {

        $ch = curl_init();
        $request = new \stdClass();
        $request->to   = (string)$body->whatsappNumber ?? '';
        $request->body = $body->messageToSend ?? '';

        $token = $this->getTokenWhatsapp();
        $url =  URL_WHATSAPP_API . 'messages';
        $authHeader = 'Bearer ' . $token;
        $wsBy          = $body->wsBy ?? '';
        if(empty($wsBy)) {
            $wsBy = 'sdc';
        }
        if ($wsBy == 'sdc') {
            $this->log->writeLog("$this->tx " . __FUNCTION__ . " Envío mensaje por WhatsappSDC \n");
            $url =  URL_WHATSAPP_API_SDC . 'send';
        }else if ($wsBy == 'sdc_bci') {
            $this->log->writeLog("$this->tx " . __FUNCTION__ . " Envío mensaje por WhatsappSDC para BCI \n");
            $url =  URL_WHATSAPP_API_BCI . 'send';
        }else if ($wsBy == 'sdc_stream_cl') {
            $this->log->writeLog("$this->tx " . __FUNCTION__ . " Envío mensaje por WhatsappSDC para Chile Streaming y mensaje inmediato \n");
            $url =  URL_WHATSAPP_API_STREAMING_CL . 'send';
        }else if ($wsBy == 'sdc_peru') {
            $this->log->writeLog("$this->tx " . __FUNCTION__ . " Envío mensaje por WhatsappSDC para Peru \n");
            $url =  URL_WHATSAPP_API_PERU . 'send';
            $url = 'http://200.10.111.77:15672/api/exchanges/%2Fwha/whatsappin.peru.envio.request/publish';
            $request = new stdClass;
            $request->properties = new stdClass;
            $request->properties->content_type = 'application/json';
            $request->properties->reply_to = 'reply-to';
            $request->routing_key = '10.100.20.241';
            $request->payload = new stdClass;
            $request->payload->to   = (int)$body->whatsappNumber ?? '';
            $request->payload->body = (string)$body->messageToSend ?? '';
            $request->payload = json_encode($request->payload);
            $request->payload_encoding = 'string';
            $authHeader = 'Basic YWRtaW46anU3eWh0ZzV0cnQ=';
        }
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " " . $url . " \n");
        $postVars = json_encode($request);
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " postVars: " . print_r(str_replace('\/','/', $postVars), true) . " \n");
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postVars,
            CURLOPT_HTTPHEADER => array(
                "Authorization: " . $authHeader,
                "Content-Type: application/json"
            ),
        ));

        $response = curl_exec($ch);        
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->log->writeLog("$this->tx response($http_status): " . print_r($response, true) . " \n");
        curl_close($ch);
        $responseDecode = null;
        if ($errno) {
            $this->set('error', $errno);
            $this->set('errorDescription', $error);
        } else {
            $responseDecode = json_decode($response);
            if (json_last_error() === JSON_ERROR_NONE) {
                if ((int)$http_status === 200) {
                    $this->set('error', ERROR_CODE_SUCCESS);
                    $this->set('errorDescription', ERROR_DESC_SUCCESS);
                } elseif ((int)$http_status === 500) {
                    $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                    $this->set('errorDescription', $responseDecode->Description ?? '');
                } else {
                    $this->set('error', $http_status);
                    $this->set('errorDescription', $responseDecode->Description ?? '');
                }
            } else {
                $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                $this->set('errorDescription', 'Formato no esperado');
            }
            
        }

        $this->log->writeLog("$this->tx fin " . get_class() . " " . __FUNCTION__ . "\n");
        return $responseDecode;
    }

    /**
     * Guarda la respuesta
     */
    public function saveMessageWhastapp($body) {
        
        $this->log->writeLog("{$this->tx} Init " . __FUNCTION__ . "\n");
        $sdgpdo = new SDGConnectPDO(USER_DB, PASS_DB, SCHEMA_DB, HOST_DB, PORT_DB);
        $sdgpdo->setLog($this->log);
        $sdgpdo->setTx($this->tx);
        $createdAt = date("Y-m-d H:i:s");
        
        $query = "INSERT INTO `{$this->tableMessages}`
        (`whatsappNumberClient`,`message`, `createdAt`)
        VALUES  (:whatsappNumberClient,:messages, :createdAt) ;";

        $sdgpdo->setQuery($query);
        $sdgpdo->setParams([
            ":whatsappNumberClient" => $body->whatsappNumber,
            ":messages" => $body->message,
            ":createdAt" => $createdAt
        ]);
        $newId = $sdgpdo->addRow();

        $this->set('error', $sdgpdo->get('error'));
        $this->set('errorDescription', $sdgpdo->get('errorDescription'));
        return $newId;
    }

    public function execAgendamientoDFEEDByWhatsapp($body) {
        $this->log->writeLog("{$this->tx} Init " . __FUNCTION__ . "\n");
        try {
            $endpoint = URL_DFEED;
            $operation = "selectSchedulerByWhatsaap";

            $curl = "curl -X POST {$endpoint} \
            -H 'Content-Type: text/xml;charset=\"utf-8\"' \
            -H 'Accept: text/xml' \
            -H 'Cache-Control: no-cache' \
            -H 'SOAPAction: \"urn:Demo#OutputRequest\"' \
            -d '<soapenv:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:Demo\"> \
                <soapenv:Header/> \
                <soapenv:Body> \
                    <urn:OutputRequest soapenv:encodingStyle=\"http://schemas.xmlsoap.org/soap/encoding/\"> \
                        <OperationRequest xsi:type=\"urn:OperationRequest\" xmlns:urn=\"urn:Configurationwsdl\"> \
                            <Header xsi:type=\"urn:HeaderRequest\"> \
                                <Operation xsi:type=\"xsd:string\">{$operation}</Operation> \
                            </Header> \
                            <Data xsi:type=\"urn:DataRequest\"> \
                                <Property xsi:type=\"urn:Property\"> \
                                    <Name xsi:type=\"xsd:string\">customerPhone</Name> \
                                    <Value xsi:type=\"xsd:string\">".$body->whatsappNumber."</Value> \
                                </Property> \
                                <Property xsi:type=\"urn:Property\"> \
                                    <Name xsi:type=\"xsd:string\">selectedCont</Name> \
                                    <Value xsi:type=\"xsd:string\">".trim($body->message)."</Value> \
                                </Property> \
                            </Data> \
                        </OperationRequest> \
                    </urn:OutputRequest> \
                </soapenv:Body> \
                </soapenv:Envelope>' ";

            $this->log->writeLog("$this->tx curl: " . print_r($curl, true) . " \n");
            exec($curl . " > /dev/null 2>&1 &");
        } catch (\Throwable $th) {
            //throw $th;
        }
        $this->log->writeLog("{$this->tx} End " . __FUNCTION__ . "\n");
    }

    public function execAgendamientoDFEEDByWhatsappProduction($body) {
        $this->log->writeLog("{$this->tx} Init " . __FUNCTION__ . "\n");
        try {
            $endpoint = URL_DFEED_PROD;
            $operation = "selectSchedulerByWhatsaap";

            $curl = "curl -X POST {$endpoint} \
            -H 'Content-Type: text/xml;charset=\"utf-8\"' \
            -H 'Accept: text/xml' \
            -H 'Cache-Control: no-cache' \
            -H 'SOAPAction: \"urn:Demo#OutputRequest\"' \
            -d '<soapenv:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:Demo\"> \
                <soapenv:Header/> \
                <soapenv:Body> \
                    <urn:OutputRequest soapenv:encodingStyle=\"http://schemas.xmlsoap.org/soap/encoding/\"> \
                        <OperationRequest xsi:type=\"urn:OperationRequest\" xmlns:urn=\"urn:Configurationwsdl\"> \
                            <Header xsi:type=\"urn:HeaderRequest\"> \
                                <Operation xsi:type=\"xsd:string\">{$operation}</Operation> \
                            </Header> \
                            <Data xsi:type=\"urn:DataRequest\"> \
                                <Property xsi:type=\"urn:Property\"> \
                                    <Name xsi:type=\"xsd:string\">customerPhone</Name> \
                                    <Value xsi:type=\"xsd:string\">".$body->whatsappNumber."</Value> \
                                </Property> \
                                <Property xsi:type=\"urn:Property\"> \
                                    <Name xsi:type=\"xsd:string\">selectedCont</Name> \
                                    <Value xsi:type=\"xsd:string\">".trim($body->message)."</Value> \
                                </Property> \
                            </Data> \
                        </OperationRequest> \
                    </urn:OutputRequest> \
                </soapenv:Body> \
                </soapenv:Envelope>' ";

            $this->log->writeLog("$this->tx curl: " . print_r($curl, true) . " \n");
            exec($curl . " > /dev/null 2>&1 &");
        } catch (\Throwable $th) {
            //throw $th;
        }
        $this->log->writeLog("{$this->tx} End " . __FUNCTION__ . "\n");
    }

    public function sendMessageWhastappSDC($body)
    {

        $ch = curl_init();
        $request = new \stdClass();
        $request->to   = (string)$body->whatsappNumber ?? '';
        $request->body = $body->messageToSend ?? '';

        $token = $this->getTokenWhatsapp();
        $url =  URL_WHATSAPP_API_SDC . 'send';
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " " . $url . " \n");
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " token: " . print_r($token, true) . " \n");
        $postVars = json_encode($request);
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " postVars: " . print_r($postVars, true) . " \n");
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postVars,
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . $token,
                "Content-Type: application/json"
            ),
        ));

        $response = curl_exec($ch);        
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->log->writeLog("$this->tx response($http_status): " . print_r($response, true) . " \n");
        curl_close($ch);
        $responseDecode = null;
        if ($errno) {
            $this->set('error', $errno);
            $this->set('errorDescription', $error);
        } else {
            $responseDecode = json_decode($response);
            if (json_last_error() === JSON_ERROR_NONE) {
                if ((int)$http_status === 200) {
                    $this->set('error', ERROR_CODE_SUCCESS);
                    $this->set('errorDescription', ERROR_DESC_SUCCESS);
                } elseif ((int)$http_status === 500) {
                    $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                    $this->set('errorDescription', $responseDecode->status);
                } else {
                    $this->set('error', $http_status);
                    $this->set('errorDescription', $responseDecode->msg);
                }
            } else {
                $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                $this->set('errorDescription', 'Formato no esperado');
            }
            
        }

        $this->log->writeLog("$this->tx fin " . get_class() . " " . __FUNCTION__ . "\n");
        return $responseDecode;
    }

    public function sendTemplateMessageBotMaker($body)
    {
        $request = new \stdClass();
        $request->chatPlatform   = 'whatsapp';
        $request->chatChannelNumber = PHONE_BOTMAKER_FROM;
        $request->platformContactId = $body->customerPhone ?? '';
        $request->ruleNameOrId      = $body->templateBM ?? '';
        $request->params = new \stdClass();
        $request->params->customerName    = $body->customerName ?? '';
        $request->params->customerAddress = $body->customerAddress ?? '';
        $request->params->schedule1       = $body->schedule1 ?? '';
        $request->params->schedule2       = $body->schedule2 ?? '';
        $request->params->dateScheduled      = $body->dateScheduled ?? '';
        $request->params->timeIniScheduled   = $body->timeIniScheduled ?? '';
        $request->params->timeEndScheduled   = $body->timeEndScheduled ?? '';
        $request->params->timeArrival        = $body->timeArrival ?? '';

        $accessToken = $this->getTokenWhatsappBotMaker();
        $url =  URL_API_BOTMAKER . '/intent/v2';
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " " . $url . " \n");
        $postVars = json_encode($request);
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " accessToken: " . print_r($accessToken, true) . " \n");
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " postVars: " . print_r($postVars, true) . " \n");
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postVars,
            CURLOPT_HTTPHEADER => array(
                "access-token: " . trim($accessToken),
                "Content-Type: application/json",
                "Accept: application/json"
            ),
        ));

        $response = curl_exec($ch);        
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->log->writeLog("$this->tx response($http_status): " . print_r($response, true) . " \n");
        curl_close($ch);
        $responseDecode = [];
        if ($errno) {
            $this->set('error', $errno);
            $this->set('errorDescription', $error);
        } else {
            $responseDecode = json_decode($response);
            if (json_last_error() === JSON_ERROR_NONE) {
                if ((int)$http_status === 200) {
                    $this->set('error', ERROR_CODE_SUCCESS);
                    $this->set('errorDescription', ERROR_DESC_SUCCESS);
                } else {
                    $this->set('error', $http_status);
                    $this->set('errorDescription', 'Mensaje no pudo ser entregado');
                }
            } else {
                $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                $this->set('errorDescription', 'Formato no esperado');
            }
            
        }

        $this->log->writeLog("$this->tx fin " . get_class() . " " . __FUNCTION__ . "\n");
        return $responseDecode;
    }
}
