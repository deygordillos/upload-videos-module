<?php

namespace App\Estructure\BLL;

class BaseMethod
{

    protected $log;
    protected $tx;
    protected $data;    
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
        return $this->$name;
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
     * @param object $required Fields required not in body
     * @param object $notAvailables Fields not availables in body
     * @param object $fieldsNotVarType Fields with wrong data type
     */
    protected function validMethodPOST($body = [], $required = [], $notAvailables = [], $fieldsNotVarType = []) {
        $this->set('error', ERROR_CODE_SUCCESS);
        $this->set('errorDescription', ERROR_DESC_SUCCESS);

        if (empty($body)) {
            $this->set('error', ERROR_CODE_BAD_REQUEST);
            $this->set('errorDescription', ERROR_DESC_BAD_REQUEST . '. Debe enviar campos por Body.');
        } else {
            // si hay valores que no cumplen con los tipos de datos
            if ( !empty($fieldsNotVarType) ) {
                $this->set('error', ERROR_CODE_BAD_REQUEST);
                $this->set('errorDescription', ERROR_DESC_BAD_REQUEST . '. [' . implode(', ', $fieldsNotVarType) . '] ');
                $this->log->writeLog("{$this->tx} " . __FUNCTION__ . " :" . print_r($this->get('errorDescription'), true) . "\n");
            }
            // si hay campos requeridos            
            elseif (!empty($required) && $this->isPOST) {
                $this->set('error', ERROR_CODE_BAD_REQUEST);
                $this->set('errorDescription', ERROR_DESC_BAD_REQUEST . '. Campos [' . implode(', ', $required) . '] son requeridos.');
                $this->log->writeLog("{$this->tx} " . __FUNCTION__ . " :" . print_r($this->get('errorDescription'), true) . "\n");
            }
            // Si hay campos no permitidos            
            elseif (!empty($notAvailables)) {
                $this->set('error', ERROR_CODE_BAD_REQUEST);
                $this->set('errorDescription', ERROR_DESC_BAD_REQUEST . '. Campos [' . implode(', ', $notAvailables) . '] no son permitidos.');
                $this->log->writeLog("{$this->tx} " . __FUNCTION__ . " :" . print_r($this->get('errorDescription'), true) . "\n");
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

                    if ( empty( $value ) ) {
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
}
