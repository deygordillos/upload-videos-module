<?php

namespace App\Estructure\BLL;

use App\Estructure\DAO\AuthDAO;
use App\Utils\DayLog;

class AuthBLL extends BaseMethod
{

    public function __construct()
    {
        $this->DAO = new AuthDAO();
    }

    /**
     * Registrar usuarios
     * @param object $body 
     * {
     *    "email": '',
     *    "phone": '',
     *    "name": ''
     * }
     */
    public function registerUser($body = [])
    {
        ini_set('log_errors', true);
        ini_set('error_log', API_HOME_PATH . '/log/' . __FUNCTION__ . '-' . date('Ymd') . '.log');
        $this->log = new DayLog(API_HOME_PATH, __FUNCTION__);
        $this->tx = substr(uniqid(), 5);
        $this->DAO->set('log', $this->log);
        $this->DAO->set('tx',  $this->tx);
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Init \n");
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Request: " . print_r($body, true) . "\n");

        $camposRequired = ['email', 'phone', 'name','dni']; // properties required in request
        $camposAvailables = $camposRequired;

        $required = []; // campos requeridos
        $notAvailables = []; // campos no permitidos
        // Recorro los campos para verificar que sean los requeridos y sean los permitidos
        foreach ((object) $body as $property => $value) {
            if (in_array($property, $camposRequired)) {
                if (empty(trim($value))) {
                    $required[] = $property;
                } else {
                    $this->set($property, addslashes(trim($value)));
                }
            }
            if (!in_array($property, $camposAvailables)) {
                $notAvailables[] = $property;
            }
        }

        // Valido el request POST
        $this->validMethodPOST($body, $required, $notAvailables);

        if ($this->get('error') === ERROR_CODE_SUCCESS) {
            // Realizo el registro
            $body->email   = isset($body->email) ? strtolower($body->email) : '';
            $body->phone   = isset($body->phone) ? (int)preg_replace('/[\D]/', '', $body->phone) : '';
            // Si es un correo
            if (filter_var($body->email, FILTER_VALIDATE_EMAIL)) {

                // entonces verifico si existe el registro            
                $idUser = $this->DAO->getIdUserByEmail($body->email);
                // Si existe el registro
                if ($this->DAO->get('error') === ERROR_CODE_SUCCESS) {
                    // Error, no se puede crear el registro
                    $this->set('error', ERROR_CODE_INTERNAL_SERVER);
                    $this->set('errorDescription', 'Usuario ya se encuentra registrado');
                    //$return->data = new \stdClass();
                    //$return->data->idUser = $idUser;
                } else {
                    // No existe, se crea
                    $token = $this->getToken();
                    $body->token = $token;
                    $idUser = $this->DAO->createUser($body);
                    if ($this->DAO->get('error') == ERROR_CODE_SUCCESS) {
                        $data = new \stdClass();
                        $data->idUser = $idUser;
                        $data->token = $token;

                        $this->set('data', $data);

                        // Envío correo
                        $this->DAO->sendMailRegister($body);
                        if ($this->DAO->get('error') == ERROR_CODE_SUCCESS) {
                            $this->set('error', ERROR_CODE_SUCCESS);
                            $this->set('errorDescription', 'Usuario registrado correctamente');
                        } else {
                            $this->set('error', $this->DAO->get('error'));
                            $this->set('errorDescription', $this->DAO->get('errorDescription'));
                        }
                    }
                }
            } else {
                // No es un correo válido
                $this->set('error', ERROR_CODE_BAD_REQUEST);
                $this->set('errorDescription', 'Correo ' . $body->email . ' inválido');
                $this->log->writeLog("{$this->tx} " . __FUNCTION__ . " :" . print_r($this->get('errorDescription'), true) . "\n");
            }
        }

        $json = new \stdClass();
        $json->errors = new \stdClass();
        $json->errors->status = $this->get('error');
        $json->errors->title = $this->get('errorDescription');

        if ($this->get('error') === ERROR_CODE_SUCCESS) {
            $json->data = $this->get('data');
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Return: " . print_r(json_encode($json), true) . " \n");
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Fin \n\n");
        return $json;
    }


    /**
     * Obtener data del registro de usuarios por TOKEN    
     * @return object Data usuario
     */
    public function getDataUserByToken($token)
    {
        ini_set('log_errors', true);
        ini_set('error_log', API_HOME_PATH . '/log/' . __FUNCTION__ . '-' . date('Ymd') . '.log');
        $this->log = new DayLog(API_HOME_PATH, __FUNCTION__);
        $this->tx = substr(uniqid(), 5);
        $this->DAO->set('log', $this->log);
        $this->DAO->set('tx',  $this->tx);
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Init \n");
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Token: " . print_r($token, true) . "\n");
        $dataToken = [];
        $this->set('error', ERROR_CODE_INTERNAL_SERVER);
        $this->set('errorDescription', ERROR_DESC_INTERNAL_SERVER . ':' . __LINE__);
        if (empty($token)) {
            $this->set('error', ERROR_CODE_BAD_REQUEST);
            $this->set('errorDescription', 'Envíe un token');
        } else {
            $dataToken = $this->DAO->getDataUserByToken($token);
            $this->set('error', $this->DAO->get('error'));
            $this->set('errorDescription', $this->DAO->get('errorDescription'));
        }

        $json = new \stdClass();
        $json->errors = new \stdClass();
        $json->errors->status = $this->get('error');
        $json->errors->title = $this->get('errorDescription');

        if ($this->get('error') === ERROR_CODE_SUCCESS) {
            $json->data = $dataToken;
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Return: " . print_r(json_encode($json), true) . " \n");
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Fin \n\n");
        return $json;
    }


    /**
     * Completar el registro del usuario
     * @param string $token
     * @param object $data
     * @default 0
     * @return object Data usuario
     */
    public function completeRegister($token, $body)
    {
        ini_set('log_errors', true);
        ini_set('error_log', API_HOME_PATH . '/log/' . __FUNCTION__ . '-' . date('Ymd') . '.log');
        $this->log = new DayLog(API_HOME_PATH, __FUNCTION__);
        $this->tx = substr(uniqid(), 5);
        $this->DAO->set('log', $this->log);
        $this->DAO->set('tx',  $this->tx);
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Init \n");
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Request: " . print_r($body, true) . "\n");

        $this->set('error', ERROR_CODE_INTERNAL_SERVER);
        $this->set('errorDescription', ERROR_DESC_INTERNAL_SERVER . ':' . __LINE__);
        if (empty($token)) {
            $this->set('error', ERROR_CODE_BAD_REQUEST);
            $this->set('errorDescription', 'Envíe un token');
        } else {

            $camposRequired = ['pass']; // properties required in request
            $camposAvailables = $camposRequired;

            $required = []; // campos requeridos
            $notAvailables = []; // campos no permitidos
            // Recorro los campos para verificar que sean los requeridos y sean los permitidos
            foreach ((object) $body as $property => $value) {
                if (in_array($property, $camposRequired)) {
                    if (empty(trim($value))) {
                        $required[] = $property;
                    } else {
                        $this->set($property, addslashes(trim($value)));
                    }
                }
                if (!in_array($property, $camposAvailables)) {
                    $notAvailables[] = $property;
                }
            }

            // Valido el request POST
            $this->validMethodPOST($body, $required, $notAvailables);

            if ($this->get('error') === ERROR_CODE_SUCCESS) {
           
                // Verificar que el token exista
                $dataToken = $this->DAO->getDataUserByToken($token);
                if ($this->DAO->get('error') === ERROR_CODE_SUCCESS) {

                    //$body->pass = $this->encriptPass( $this->get('pass') );
                    //$validPass = preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{6,})/', $body->pass); // if pass is valid
                    //if ($validPass) {
                        // Si la clave viene MD5
                        if (preg_match('/^[a-f0-9]{32}$/', $body->pass)) {
                            $this->DAO->completeRegisterWithPass($token, $body);
                            if ($this->DAO->get('error') === ERROR_CODE_SUCCESS) {
                                $this->set('error', $this->DAO->get('error'));
                                $this->set('errorDescription', $this->DAO->get('errorDescription'));
                            }
                        } else {
                            $this->set('error', ERROR_CODE_BAD_REQUEST);
                            $this->set('errorDescription', 'Clave no cuenta con las características necesarias para ser almacenada');
                        }
                        
                    //} else {
                    //    $this->set('error', ERROR_CODE_BAD_REQUEST);
                    //    $this->set('errorDescription', 'Contraseña debe tener las siguientes características: ' .
                    //    'Contener al menos <strong>6</strong> caracteres: mayúscula, minúscula, caracter especial [!@#$%^&*] y número');
                   // }
                    
                } else {
                    $this->set('error', $this->DAO->get('error'));
                    $this->set('errorDescription', $this->DAO->get('errorDescription'));
                }

            }
        }

        $json = new \stdClass();
        $json->errors = new \stdClass();
        $json->errors->status = $this->get('error');
        $json->errors->title = $this->get('errorDescription');

        if ($this->get('error') === ERROR_CODE_SUCCESS) {
            $json->data = $dataToken;
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Return: " . print_r(json_encode($json), true) . " \n");
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Fin \n\n");
        return $json;
    }

    /**
     * Obtener data del registro de usuarios por email y password
     * @return object Data usuario
     */
    public function getDataUserByEmailPassword($body)
    {
        ini_set('log_errors', true);
        ini_set('error_log', API_HOME_PATH . '/log/' . __FUNCTION__ . '-' . date('Ymd') . '.log');
        $this->log = new DayLog(API_HOME_PATH, __FUNCTION__);
        $this->tx = substr(uniqid(), 5);
        $this->DAO->set('log', $this->log);
        $this->DAO->set('tx',  $this->tx);
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Init \n");
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Request: " . print_r($body, true) . "\n");
        $dataUser = [];
        $camposRequired = ['email', 'pass']; // properties required in request
        $camposAvailables = $camposRequired;

        $required = []; // campos requeridos
        $notAvailables = []; // campos no permitidos
        // Recorro los campos para verificar que sean los requeridos y sean los permitidos
        foreach ((object) $body as $property => $value) {
            if (in_array($property, $camposRequired)) {
                if (empty(trim($value))) {
                    $required[] = $property;
                } else {
                    $this->set($property, addslashes(trim($value)));
                }
            }
            if (!in_array($property, $camposAvailables)) {
                $notAvailables[] = $property;
            }
        }

        // Valido el request POST
        $this->validMethodPOST($body, $required, $notAvailables);

        if ($this->get('error') === ERROR_CODE_SUCCESS) {

            $body->email   = isset($body->email) ? strtolower($body->email) : '';
            $body->pass   = isset($body->pass) ? $this->encriptMd5( $body->pass ) : '';
            // Si es un correo
            if (filter_var($body->email, FILTER_VALIDATE_EMAIL)) {

                $dataUser = $this->DAO->getDataUserByEmailPassword($body->email,$body->pass);
                $this->set('error', $this->DAO->get('error'));
                $this->set('errorDescription', $this->DAO->get('errorDescription'));
            } else {
                // No es un correo válido
                $this->set('error', ERROR_CODE_BAD_REQUEST);
                $this->set('errorDescription', 'Correo ' . $body->email . ' inválido');
                $this->log->writeLog("{$this->tx} " . __FUNCTION__ . " :" . print_r($this->get('errorDescription'), true) . "\n");
            }
        }

        $json = new \stdClass();
        $json->errors = new \stdClass();
        $json->errors->status = $this->get('error');
        $json->errors->title = $this->get('errorDescription');

        if ($this->get('error') === ERROR_CODE_SUCCESS) {
            $json->data = $dataUser;
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Return: " . print_r(json_encode($json), true) . " \n");
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Fin \n\n");
        return $json;
    }

    /**
     * Login API user
     * @param object $body 
     * {
     *     "username" => "",
     *     "password" => ""
     * }
     * @return object Data usuario
     */
    public function login($body)
    {
        ini_set('log_errors', true);
        ini_set('error_log', LOG_PATH . '/' . __FUNCTION__ . '-' . date('Ymd') . '.log');
        $this->log = new DayLog(API_HOME_PATH, 'API_' . __FUNCTION__);
        $this->tx = substr(uniqid(), 5);
        $this->DAO->set('log', $this->log);
        $this->DAO->set('tx',  $this->tx);
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Init \n");
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Request: " . print_r($body, true) . "\n");
        
        $this->camposRequired = [
            'username' => ['type' => 'string'], 
            'password' => ['type' => 'string']
        ]; // properties required in request
        $this->camposAvailables = array_keys($this->camposRequired);

        // Valido campos request
        $body = $this->validRequestFields($body);

        // Valido el request POST
        $this->validMethodPOST($body, $this->required, $this->notAvailables);

        if ($this->get('error') === ERROR_CODE_SUCCESS) {
            $dataLogin = (object)$this->DAO->getUserByUsername($body->username);
            if ($this->DAO->get('error') === ERROR_CODE_SUCCESS) {
                if ( $this->isValidPass( $body->password, $dataLogin->password ) ) {
                    $this->log->writeLog("$this->tx " . __FUNCTION__ . " Login valid OK.\n");
                    $this->set('error', ERROR_CODE_SUCCESS);
                    $this->set('errorDescription', ERROR_DESC_SUCCESS);
                } else { 
                    $this->log->writeLog("$this->tx " . __FUNCTION__ . " Password Invalid.\n");
                    $this->set('error', ERROR_CODE_UNAUTHORIZED);
                    $this->set('errorDescription', ERROR_MESSAGE_UNAUTHORIZED);
                }
            } else {
                $this->set('error', ERROR_CODE_UNAUTHORIZED);
                $this->set('errorDescription', ERROR_MESSAGE_UNAUTHORIZED);
            }
        }

        $json = new \stdClass();
        $json->status          = new \stdClass();
        $json->status->code    = $this->get('error');
        $json->status->message = $this->get('errorDescription');

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Return: " . print_r(json_encode($json), true) . " \n");
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Fin \n\n");
        return $json;
    }


    /**
     * Obtener data de usuario API REST por username
     * @param object $body 
     * {
     *     "username" => ""
     * }
     * @return object Data usuario
     */
    public function getDataByUsername($body)
    {
        ini_set('log_errors', true);
        ini_set('error_log', LOG_PATH . '/' . __FUNCTION__ . '-' . date('Ymd') . '.log');
        $this->log = new DayLog(API_HOME_PATH, 'API_' . __FUNCTION__);
        $this->tx = substr(uniqid(), 5);
        $this->DAO->set('log', $this->log);
        $this->DAO->set('tx',  $this->tx);
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Init \n");
        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Request: " . print_r($body, true) . "\n");
        
        $this->camposRequired = [
            'username' => ['type' => 'string']
        ]; // properties required in request
        $this->camposAvailables = $this->camposRequired;

        // Valido campos request
        $body = $this->validRequestFields($body);

        // Valido el request POST
        $this->validMethodPOST($body, $this->required, $this->notAvailables);
        $dataResponse = [];
        if ($this->get('error') === ERROR_CODE_SUCCESS) {
            $dataResponse = (object)$this->DAO->getUserByUsername($body->username);
            if ($this->DAO->get('error') === ERROR_CODE_SUCCESS) {
                $this->log->writeLog("$this->tx " . __FUNCTION__ . " Response OK.\n");
                $this->set('error', ERROR_CODE_SUCCESS);
                $this->set('errorDescription', ERROR_DESC_SUCCESS);
                unset($dataResponse->password);
            } else {
                $this->set('error', ERROR_CODE_UNAUTHORIZED);
                $this->set('errorDescription', ERROR_MESSAGE_UNAUTHORIZED);
            }
        }

        $json = new \stdClass();
        $json->status = new \stdClass();
        $json->status->code = $this->get('error');
        $json->status->message = $this->get('errorDescription');
        if ($this->get('error') === ERROR_CODE_SUCCESS) {
            $json->data = $dataResponse;
        }

        $this->log->writeLog("$this->tx " . __FUNCTION__ . " Return: " . print_r(json_encode($json), true) . " \n");
        $this->log->writeLog("$this->tx " . __CLASS__ . " " . __FUNCTION__ . " Fin \n\n");
        return $json;
    }


    private function encriptPass($pass = '') {
        return password_hash($pass, PASSWORD_BCRYPT);
    }

    private function isValidPass($pass = '', $hash = '') {
        return password_verify($pass, $hash);
    }
    
}
