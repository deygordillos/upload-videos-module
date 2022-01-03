<?php
namespace App\Estructure\DAO;

use App\Estructure\BLL\BaseMethod;
use App\Utils\SDGConnectPDO;

class WhatsappClassDAO extends BaseMethod
{
    private $table = 'API_AUTH_WHATSAPP';
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

    
    public function sendMessageWhastapp($body)
    {

        $ch = curl_init();
        $request = new \stdClass();
        $request->to   = (string)$body->whatsappNumber ?? '';
        $request->body = $body->messageToSend ?? '';

        $token = $this->getTokenWhatsapp();
        $url =  URL_WHATSAPP_API . 'messages';
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

}
