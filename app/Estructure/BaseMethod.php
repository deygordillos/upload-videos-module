<?php

namespace App\Estructure;

class BaseMethod
{

    protected $log;
    protected $tx;
    protected $dbConnection;
    protected $data;    
    protected $error;
    protected $errorDescription;
    protected $camposRequired = []; // campos requeridos
    protected $camposAvailables = []; // campos no permitidos    
    protected $required = []; // campos requeridos (para retorno de error)
    protected $notAvailables = []; // campos no permitidos (para retorno de error)
    protected $fieldsNotVarType = []; // campos que no cumplen con el tipo de dato
    protected $username; // usuario que usa la api
    protected $isPOST = TRUE;
    
    public function set($name, $value)
    {
        $this->$name = $value;
    }

    public function get($name)
    {
        return $this->$name ?? '';
    }

    /**
     * Establece el objeto de log
     * @param mixed $log Instancia del log
     */
    public function setLog($log): void
    {
        $this->log = $log;
    }

    /**
     * Establece el transaction ID
     * @param string $tx Transaction ID
     */
    public function setTx(string $tx): void
    {
        $this->tx = $tx;
    }

    /**
     * Establece la conexión a la base de datos
     * @param mixed $dbConnection Instancia de la conexión a BD
     */
    public function setDbConnection($dbConnection): void
    {
        $this->dbConnection = $dbConnection;
    }

    /**
     * Obtiene el código de error
     * @return int|string Código de error
     */
    public function getError()
    {
        return $this->error ?? '';
    }

    /**
     * Obtiene la descripción del error
     * @return string Descripción del error
     */
    public function getErrorDescription(): string
    {
        return $this->errorDescription ?? '';
    }

    /**
     * Establece el código de error
     * @param int|string $error Código de error
     */
    public function setError($error): void
    {
        $this->error = $error;
    }

    /**
     * Establece la descripción del error
     * @param string $errorDescription Descripción del error
     */
    public function setErrorDescription(string $errorDescription): void
    {
        $this->errorDescription = $errorDescription;
    }

    protected function getToken()
    {

        $token = hash("md2", (string)microtime());

        return $token;
    }

    /**
     * Comprueba si el rut ingresado es valido
     *
     * @param $rut string
     * @return true o false
     */
    protected function validarRut($rut)
    {
        if (!preg_replace('/(\d[0-9]{1,9})(-)([k,K0-9])/', '', $rut)) {
            $rut = preg_replace('/[^k0-9]/i', '', $rut);
            $dv = substr($rut, -1);
            $numero = substr($rut, 0, strlen($rut) - 1);
            $i = 2;
            $suma = 0;
            foreach (array_reverse(str_split($numero)) as $v) {
                if ($i == 8)
                    $i = 2;

                $suma += $v * $i;
                ++$i;
            }

            $dvr = 11 - ($suma % 11);

            if ($dvr == 11)
                $dvr = 0;
            if ($dvr == 10)
                $dvr = 'K';

            if ($dvr == strtoupper($dv))
                return true;
            else
                return false;
        }

        return false;
    }

    /**
     * Validate Method Post (response)
     * @param object $body Request Body
     * @param array $required Fields required not in body
     * @param array $notAvailables Fields not availables in body
     * @param array $fieldsNotVarType Fields with wrong data type
     */
    protected function validMethodPOST($body = [], $required = [], $notAvailables = [], $fieldsNotVarType = []) {
        $this->error = ERROR_CODE_SUCCESS;
        $this->errorDescription = ERROR_DESC_SUCCESS;

        if (empty($body)) {
            $this->error = ERROR_CODE_BAD_REQUEST;
            $this->errorDescription = ERROR_DESC_BAD_REQUEST . '. Debe enviar campos por Body.';
        } else {
            // si hay valores que no cumplen con los tipos de datos
            if ( !empty($fieldsNotVarType) ) {
                $this->error = ERROR_CODE_BAD_REQUEST;
                $this->errorDescription = ERROR_DESC_BAD_REQUEST . '. [' . implode(', ', $fieldsNotVarType) . '] ';
                $this->log->writeLog("{$this->tx} " . __FUNCTION__ . " :" . print_r($this->errorDescription, true) . "\n");
            }
            // si hay campos requeridos            
            elseif (!empty($required) && $this->isPOST) {
                $this->error = ERROR_CODE_BAD_REQUEST;
                $this->errorDescription = ERROR_DESC_BAD_REQUEST . '. Campos [' . implode(', ', $required) . '] son requeridos.';
                $this->log->writeLog("{$this->tx} " . __FUNCTION__ . " :" . print_r($this->errorDescription, true) . "\n");
            }
            // Si hay campos no permitidos            
            elseif (!empty($notAvailables)) {
                $this->error = ERROR_CODE_BAD_REQUEST;
                $this->errorDescription = ERROR_DESC_BAD_REQUEST . '. Campos [' . implode(', ', $notAvailables) . '] no son permitidos.';
                $this->log->writeLog("{$this->tx} " . __FUNCTION__ . " :" . print_r($this->errorDescription, true) . "\n");
            }
        }
    }

    /**
     * Validate request fields.
     * If these are requered, availables or data type y correct
     * @param object $body Request Body
     * @return object $body Request Body reseted
     */
    protected function validRequestFields($body)
    {
        $camposRequeridosToDelete =  array_keys($this->camposRequired);

        $this->required = []; // campos requeridos
        $this->notAvailables = []; // campos no permitidos
        // Recorro los campos para verificar que sean los requeridos y sean los permitidos
        foreach ((object) $body as $property => $value) {
            if ( in_array($property, array_keys($this->camposRequired) )) {
                $keyProperty = array_search($property, array_keys($this->camposRequired) );
                
                $varTypeRequest = gettype($value);
                $varType = $this->camposRequired[ $property ]['type'] ?? ''; // tipo de dato esperado

                if ($varTypeRequest !== $varType) {
                    $this->fieldsNotVarType[] = $property . ' is not ' . $varType;
                } else {

                    $value = ($varType === 'string') 
                        ? trim($value)
                        : ( ( $varType === 'integer' )
                            ? (int)$value 
                            : ( ($varType === 'double' ) 
                                ? round( (float)$value, 8, PHP_ROUND_HALF_UP)
                                : $value ) );

                    if ( $value == "" || strlen($value) == 0 ) {
                        $this->required[] = $property;
                    } else {
                        $body->{$property} = $value;
                    }

                    unset($camposRequeridosToDelete[$keyProperty]);
                }
            }

            if (!in_array($property, $this->camposAvailables) ) {
                $this->notAvailables[] = $property;
            }
        }

        if (!empty($camposRequeridosToDelete)) {
            $this->required = array_merge( $this->required, $camposRequeridosToDelete);
        }

        return $body;
    }

    protected function encriptMd5($pass = '') {
        return md5( $pass );
    }

    /**
     * Genera una respuesta JSON estándar con header y return
     * 
     * @param mixed $data Datos a incluir en la respuesta (opcional)
     * @param string $operation Tipo de operación (por defecto 'request')
     * @return \stdClass Objeto JSON con estructura estándar
     */
    protected function setBuildResponse($data = null, string $operation = 'request'): \stdClass 
    {
        $json = new \stdClass();
        
        // Header estándar
        $json->Header = new \stdClass();
        $json->Header->Datetime = date('Y-m-d H:i:s');
        $json->Header->Operation = $operation;

        // Return con código y descripción de error
        $json->Return = new \stdClass();
        $json->Return->Code = $this->error;
        $json->Return->Description = $this->errorDescription;

        $json->Links = new \stdClass;
        $httpProtocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $urlSelf = $httpProtocol . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] ?? '';
        $json->Links->Self = $urlSelf;

        // Agregar datos solo si existen y no hay error
        if ($data !== null && !empty($data)) {
            $json->Data = $data;
        }

        return $json;
    }
}
