<?php

namespace Libraries;

/* * ***************************************************************** 

 * Copyright (c) VPT S.A. 2001-2008. All Rights Reserved.           

 *  --------------------------------------------------------------  *
 *  Derechos Reservados VPT S.A.                                    *
 *  Prohibida su Reproduccion Total o Parcial por cualquier metodo. *

 *                  INFORMACION CONFIDENCIAL                        *
 *
 * *******************************************************************

 * =====================================================================================
 * -------------------------------------------------------------------------------------
 *  Descripcion:

 *  Utilidad para la escritura de logs.

 * -------------------------------------------------------------------------------------
 *  Log de cambios:

 *  Fecha       Resp.   Detalle
 * -------------------------------------------------------------------------------------
 *  2016-04-25  HH      Creacion del archivo.
 * 
  -------------------------------------------------------------------------------------
  =====================================================================================
 */


/**
 * Clase para escritura de log, se debe utilizar de igual manera que la funcion DayLog de la libreria vpt_lib.
 *
 * @category  UtilsVPT
 * @package   Utils/php
 * @example <br />
 * <pre>$DayLog = new DayLog('/tmp/', MODULE_NAME)
 * $DayLog->WriteLog("Este es un mensaje de prueba... \n");
 * 
 * Escritura en /tmp/log/MODULE_NAME-YYYYMMDD.log</pre>
 * @version   0.01
 * @since     2016-04-25
 * @author hherrera
 */
class DayLog {
    
    /**
     * Constantes propias de la clase
     */
    const SUBDIR_LOG = 'log';
    const MESSAGE_TYPE_INF = 'I';
    const MESSAGE_TYPE_DEBUG = 'D';
    const MESSAGE_TYPE_ERROR = 'E';
    const ERROR_CODE_OK = '000';
    const ERROR_DESC_OK = 'Operation successfully';
    const ERROR_CODE_CONSTRUCTOR_PARAMETERS = -1;
    const ERROR_DESC_CONSTRUCTOR_PARAMETERS = 'Invalid parameters in constructor, it will not write a file for log.';
    const EXTENSION_FILE_LOG = '.log';
    const SEPARATOR_LOG = ' 0000 ';

    private $nError;
    private $szErrorDescription;
    private $szHomepath;
    private $szPathLog;
    private $szFullPathLog;
    private $szVptModuleName;
    private $szFileLog;

    /**
     * Constructor de la clase. Se creará archivo del estilo $szHomePath/$szPathLog/$szVptModuleName-YYYYMMDD.log  
     * 
     * @param string $szHomepath Parametro obligatorio, no puede ser vacio. Corresponde al directorio raiz del componente que utilizara la clase.
     * @param string $szVptModuleName Parametro obligatorio, no puede ser vacio. Corresponde al nombre del proceso que invocara la clase.
     * @param string $szPathLog Parametro opcional. Indica el subdirectorio donde se escribiran el archivo de log.
     */
    public function __construct($szHomepath = NULL, $szVptModuleName = NULL, $szPathLog = self::SUBDIR_LOG) {

        if (!isset($szHomepath) || !isset($szVptModuleName) || $szHomepath == '' || $szVptModuleName == '') {
            $this->setError(self::ERROR_CODE_CONSTRUCTOR_PARAMETERS);
            $this->setErrorDescription(self::ERROR_DESC_CONSTRUCTOR_PARAMETERS);
        } else {
            $this->szHomepath = $szHomepath;
            $this->szPathLog = $szPathLog;
            $this->szVptModuleName = gethostname() . '_' . $szVptModuleName;
            $this->szFullPathLog = $this->szHomepath . '/' . $this->szPathLog . '/' . $this->szVptModuleName . '-' . date('Ymd') . self::EXTENSION_FILE_LOG;
            ini_set('error_log', $this->szFullPathLog );

            $this->setError(self::ERROR_CODE_OK);
            $this->setErrorDescription(self::ERROR_DESC_OK);
        }
    }

    public function __destruct() {
        ;
    }

    /**
     * Metodo para la escritura de log en el archivo establecido en el constructor.<br>
     * 
     * @param string $szMessage Mensaje a escribir.
     * @param string $szTypeMessage Tipo de mensaje, valores posibles: I, D, E. Informativo, Debug y Error respectivamente. 'I' por defecto.
     */
    public function writeLog($szMessage, $szTypeMessage = self::MESSAGE_TYPE_INF) {

        if ($szTypeMessage != self::MESSAGE_TYPE_INF || $szTypeMessage != self::MESSAGE_TYPE_DEBUG || $szTypeMessage != self::MESSAGE_TYPE_ERROR) {
            $szTypeMessage =self:: MESSAGE_TYPE_INF;
        }

        if ($this->getError() == 0) {
            $this->szFileLog = fopen($this->szFullPathLog, 'a+');
            if ($this->szFileLog != FALSE) {
                fwrite($this->szFileLog, date("H:i:s") . '.' . $this->milisec() . ' ' . $szTypeMessage . self::SEPARATOR_LOG . $szMessage);
                fclose($this->szFileLog);
            }
        }
    }

    /**
     * Metodo que extrae los milisegundos del instante en que es invocada.
     * 
     * @return int 
     */
    private  function milisec() {
        $array = explode(" ", microtime());
        $parte_decimal = explode(".", $array[0]);
        return substr($parte_decimal[1], 0, 3);
    }

    public function getError() {
        return $this->nError;
    }

    public function getErrorDescription() {
        return $this->szErrorDescription;
    }

    private function setError($nError) {
        $this->nError = $nError;
    }

    private function setErrorDescription($szErrorDescription) {
        $this->szErrorDescription = $szErrorDescription;
    }

    public function getFullPathLog() {
        return $this->szFullPathLog;
    }
}
