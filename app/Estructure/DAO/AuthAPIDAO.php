<?php

require_once dirname(__FILE__) . '/../config.php';
require_once UTILS_HOME_PATH . '/DBConnector.php';
require_once API_HOME_PATH . '/DTO/AuthAPIDTO.php';

/**
 * Clase perteneciente a la capa de acceso de datos (Data Access Object) que provee los metodos para la autenticacion de credenciales de la API.
 *
 * @category  API
 * @package   API/DAO
 * @example 
 * <pre>
 * </pre>
 * @version   0.01
 * @since     2016-04-25
 * @author hherrera
 */
class AuthAPIDAO {

    /**
     * Constantes propias de la clase
     */
    const SCHEMA_API = 'tbx_tel_cl_API_AUTH';
    const STATE_API_KEY_ACTIVE = 'ACTIVA';
    const STATE_API_KEY_EXPIRED = 'EXPIRADA';
    const STATE_USER_ACTIVE = 'ACTIVA';
    const ERROR_CODE_NOT_EXISTS_API_KEY_VALID = '100';
    const ERROR_DESC_NOT_EXISTS_API_KEY_VALID = 'There is not api key active por user/ip. Please login for generate a api key valid.';
    const ERROR_CODE_CANNOT_SAVE_API_KEY = '101';
    const ERROR_DESC_CANNOT_SAVE_API_KEY = 'Cannot save api key for user/ip.';
    const ERROR_CODE_NOT_EXISTS_API_KEY = '102';
    const ERROR_DESC_NOT_EXISTS_API_KEY = 'ApiKey not exists.';
    const ERROR_CODE_NOT_EXISTS_REGISTER_USER_IP = '200';
    const ERROR_DESC_NOT_EXISTS_REGISTER_USER_IP = 'User/Ip unregistered or desactivated.';

    private $error;
    private $errorDescription;
    public static $stateActiva = self::STATE_API_KEY_ACTIVE;

    public function __construct() {
        ;
    }

    public function __destruct() {
        ;
    }

    /**
     * Metodo que inserta en BD la api key generada (activa) desde la capa de logica de negocio para 
     * cierto usuario habilitado de una correspndiente IP. 
     * 
     * @param \AuthAPIDTO $authAPIDTO Paremtros User, Ip y ApiKey deben venir seteados en el objeto.
     */
    public function insertApiKey($authAPIDTO) {

        if (!isset($authAPIDTO) || is_null($authAPIDTO->getUser()) || is_null($authAPIDTO->getIp()) || is_null($authAPIDTO->getApiKey()) || $authAPIDTO->getUser() == '' || $authAPIDTO->getIp() == '' || $authAPIDTO->getApiKey() == '') {
            $this->setError(ERROR_CODE_FUNCTION_PARAMETERS);
            $this->setErrorDescription(ERROR_DESC_FUNCTION_PARAMETERS);
        } else {
            $db = new DBConnector(USER_DB, PASS_DB, HOST_DB);
            $db->openConnection();

            if ($db->getError()) {
                $this->setError($db->getError());
                $this->setErrorDescription($db->getErrorDescription());
            } else {
                $query = 'INSERT INTO ' . self::SCHEMA_API . '.API_KEY (IdUser, ApiKey, State, CreationTimestamp)';
                $query .= ' SELECT user.Id IdUser, \'' . $authAPIDTO->getApiKey() . '\' ApiKey, \'' . self::STATE_API_KEY_ACTIVE . '\' State, NOW() CreationTimestamp';
                $query .= ' FROM ' . self::SCHEMA_API . '.USER user ';
                $query .= ' WHERE user.User = \'' . $authAPIDTO->getUser() . '\' AND user.Ip = \'' . $authAPIDTO->getIp() . '\'';
                $query .= ' AND user.State = \'' . self::STATE_USER_ACTIVE . '\'';

                $arrayData = $db->executeStmt($query);
                if ($db->getError()) {
                    $this->setError($db->getError());
                    $this->setErrorDescription($db->getErrorDescription());
                } else {
                    if ($db->getAffectedRows() > 0) {
                        $this->setError(ERROR_CODE_OK);
                        $this->setErrorDescription(ERROR_DESC_OK);
                    } else {
                        $this->setError(self::ERROR_CODE_NOT_EXISTS_REGISTER_USER_IP);
                        $this->setErrorDescription(self::ERROR_DESC_NOT_EXISTS_REGISTER_USER_IP);
                    }
                }
                $db->closeConnection();
            }
        }
    }

    /**
     * Metodo dar por finalizada API_KEY generadas con X delta de tiempo
     * 
     * @param \AuthAPIDTO $authAPIDTO Paremtro ApiKey en el objeto.
     */
    public function setExpirationApiKey($authAPIDTO) {

        if (!isset($authAPIDTO) || is_null($authAPIDTO->getApiKey()) || $authAPIDTO->getApiKey() == '') {
            $this->setError(ERROR_CODE_FUNCTION_PARAMETERS);
            $this->setErrorDescription(ERROR_DESC_FUNCTION_PARAMETERS);
        } else {
            $db = new DBConnector(USER_DB, PASS_DB, HOST_DB);
            $db->openConnection();

            if (!$db->getError()) {
                $query = 'UPDATE ' . self::SCHEMA_API . '.API_KEY ';
                $query .= ' SET State = IF((UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(CreationTimestamp)) > ' . SEC_EXPIRATE_API_KEY . ' ,\'' . self::STATE_API_KEY_EXPIRED . '\', \'' . self::STATE_API_KEY_ACTIVE . '\')';
                $query .= ' WHERE ApiKey = \'' . $authAPIDTO->getApiKey() . '\'';

                $arrayData = $db->executeStmt($query);
                if (!$db->getError()) {
                    if ($db->getAffectedRows() > 0) {
                        $this->setError(ERROR_CODE_OK);
                        $this->setErrorDescription(ERROR_DESC_OK);
                    } else {
                        $this->setError(self::ERROR_CODE_NOT_EXISTS_REGISTER_USER_IP);
                        $this->setErrorDescription(self::ERROR_DESC_NOT_EXISTS_REGISTER_USER_IP);
                    }
                } else {
                    $this->setError($db->getError());
                    $this->setErrorDescription($db->getErrorDescription());
                }
                $db->closeConnection();
            } else {
                $this->setError($db->getError());
                $this->setErrorDescription($db->getErrorDescription());
            }
        }
    }

    /**
     * Metodo que extrae la api key generada (activa) para cierto usuario habilitado de una correspndiente IP. El resultado almacena
     * dentro del atributo apiKey de la instancia DTO pasada como parametro a la funcion.
     * 
     * @param \AuthAPIDTO $authAPIDTO Parametros User e Ip deben venir seteados en el objeto.
     */
    public function getApiKeyByUserIp($authAPIDTO) {

        if (!isset($authAPIDTO) || is_null($authAPIDTO->getUser()) || is_null($authAPIDTO->getIp()) || $authAPIDTO->getUser() == '' || $authAPIDTO->getIp() == '') {
            $this->setError(ERROR_CODE_FUNCTION_PARAMETERS);
            $this->setErrorDescription(ERROR_DESC_FUNCTION_PARAMETERS);
        } else {
            $db = new DBConnector(USER_DB, PASS_DB, HOST_DB);
            $db->openConnection();

            if (!$db->getError()) {
                $query = 'SELECT api.ApiKey';
                $query .= ' FROM ' . self::SCHEMA_API . '.API_KEY api';
                $query .= ' INNER JOIN ' . self::SCHEMA_API . '.USER user ON user.Id = api.IdUser';
                $query .= ' WHERE user.User = \'' . $authAPIDTO->getUser() . '\' AND user.Ip = \'' . $authAPIDTO->getIp() . '\'';
                $query .= ' AND api.State = \'' . self::STATE_API_KEY_ACTIVE . '\'';

                $arrayData = $db->executeStmtResult($query);
                if (!$db->getError()) {
                    if ($db->getNumRows() > 0) {
                        $authAPIDTO->setApiKey($arrayData[0]['ApiKey']);

                        $this->setError(ERROR_CODE_OK);
                        $this->setErrorDescription(ERROR_DESC_OK);
                    } else {
                        $this->setError(self::ERROR_CODE_NOT_EXISTS_API_KEY_VALID);
                        $this->setErrorDescription(self::ERROR_DESC_NOT_EXISTS_API_KEY_VALID);
                    }
                } else {
                    $this->setError($db->getError());
                    $this->setErrorDescription($db->getErrorDescription());
                }
                $db->closeConnection();
            } else {
                $this->setError($db->getError());
                $this->setErrorDescription($db->getErrorDescription());
            }
        }
    }

    /**
     * Metodo obtener el estado de cierta ApiKey.
     * 
     * @param \AuthAPIDTO $authAPIDTO Paremtro ApiKey en el objeto.
     */
    public function getStateApiKey($authAPIDTO) {

        if (!isset($authAPIDTO) || is_null($authAPIDTO->getApiKey()) || $authAPIDTO->getApiKey() == '') {
            $this->setError(ERROR_CODE_FUNCTION_PARAMETERS);
            $this->setErrorDescription(ERROR_DESC_FUNCTION_PARAMETERS);
        } else {
            $db = new DBConnector(USER_DB, PASS_DB, HOST_DB);
            $db->openConnection();

            if (!$db->getError()) {
                $query = 'SELECT api.State';
                $query .= ' FROM ' . self::SCHEMA_API . '.API_KEY api';
                $query .= ' WHERE api.ApiKey = \'' . $authAPIDTO->getApiKey() . '\'';

                $arrayData = $db->executeStmtResult($query);
                if (!$db->getError()) {
                    if ($db->getNumRows() > 0) {
                        $authAPIDTO->setState($arrayData[0]['State']);

                        $this->setError(ERROR_CODE_OK);
                        $this->setErrorDescription(ERROR_DESC_OK);
                    } else {
                        $this->setError(self::ERROR_CODE_NOT_EXISTS_API_KEY);
                        $this->setErrorDescription(self::ERROR_DESC_NOT_EXISTS_API_KEY);
                    }
                } else {
                    $this->setError($db->getError());
                    $this->setErrorDescription($db->getErrorDescription());
                }
                $db->closeConnection();
            } else {
                $this->setError($db->getError());
                $this->setErrorDescription($db->getErrorDescription());
            }
        }
    }

    /**
     * Metodo que extrae la password encriptada y almacenada en BD para cierto usuario habilitado de una correspndiente IP. El resultado lo almacena
     * dentro del atributo passHash de la instancia DTO pasada como parametro a la funcion.
     * 
     * @param \AuthAPIDTO $authAPIDTO Parametros User e Ip deben venir seteados en el objeto.
     */
    public function getPassHashByUserIp($authAPIDTO) {

        if (!isset($authAPIDTO) || is_null($authAPIDTO->getUser()) || is_null($authAPIDTO->getIp()) || $authAPIDTO->getUser() == '' || $authAPIDTO->getIp() == '') {
            $this->setError(ERROR_CODE_FUNCTION_PARAMETERS);
            $this->setErrorDescription(ERROR_DESC_FUNCTION_PARAMETERS);
        } else {
            $db = new DBConnector(USER_DB, PASS_DB, HOST_DB);
            $db->openConnection();

            if (!$db->getError()) {
                $query = 'SELECT user.PassHash';
                $query .= ' FROM ' . self::SCHEMA_API . '.USER user';
                $query .= ' WHERE user.User = \'' . $authAPIDTO->getUser() . '\' AND user.Ip = \'' . $authAPIDTO->getIp() . '\'';
                $query .= ' AND user.State = \'' . self::STATE_USER_ACTIVE . '\'';

                $arrayData = $db->executeStmtResult($query);
                if (!$db->getError()) {
                    if ($db->getNumRows() > 0) {
                        $authAPIDTO->setPassHash($arrayData[0]['PassHash']);

                        $this->setError(ERROR_CODE_OK);
                        $this->setErrorDescription(ERROR_DESC_OK);
                    } else {
                        $this->setError(self::ERROR_CODE_NOT_EXISTS_REGISTER_USER_IP);
                        $this->setErrorDescription(self::ERROR_DESC_NOT_EXISTS_REGISTER_USER_IP);
                    }
                } else {
                    $this->setError($db->getError());
                    $this->setErrorDescription($db->getErrorDescription());
                }
                $db->closeConnection();
            } else {
                $this->setError($db->getError());
                $this->setErrorDescription($db->getErrorDescription());
            }
        }
    }

    public function getError() {
        return $this->error;
    }

    public function getErrorDescription() {
        return $this->errorDescription;
    }

    private function setError($error) {
        $this->error = $error;
    }

    private function setErrorDescription($errorDescription) {
        $this->errorDescription = $errorDescription;
    }

    public function getExpiredValue() {
        return self::STATE_API_KEY_EXPIRED;
    }

    public function getActiveValue() {
        return self::STATE_API_KEY_ACTIVE;
    }

}
