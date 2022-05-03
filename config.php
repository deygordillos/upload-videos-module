<?php
date_default_timezone_set('America/Santiago');
error_reporting(E_ALL & ~E_DEPRECATED);

define('AMBIENTE','v1');
define('API_HOME_PATH', dirname(__FILE__) . '/'); 
define('BASE_HOME_PATH', API_HOME_PATH);
define('LOG_PATH', API_HOME_PATH . 'log/');

ini_set("log_errors", 1);
ini_set("error_log", LOG_PATH . "/php-error-".date("Ymd").".log");

/**
 * Links
 */
//define('URL_WS_MEU_PERU', 'https://pe-ws-meucust.simpledatacorp.com/TDC.php?wsdl'); 
define('URL_WEB_PRINCIPAL', 'https://consent.simpledatacorp.com');
define('URL_WEB_SIMPLEDATA', 'https://www.simpledata.solutions');
define('PREFIX_FONO_PAIS', 56);
defined('USER_WHATSAPP_API')  OR define('USER_WHATSAPP_API', 'cristian.coccia@simpledatacorp.com'); // username user whastapp
defined('PASS_WHATSAPP_API')  OR define('PASS_WHATSAPP_API', "#sqn2'UB)nT;?AwZ"); // clave user whatsapp
defined('URL_WHATSAPP_API')  OR define('URL_WHATSAPP_API', 'https://macrobots.app/api/v1/'); // endpoint whatsapp
defined('URL_WHATSAPP_API_SDC')  OR define('URL_WHATSAPP_API_SDC', 'http://10.100.20.240:80/whatsapp/'); // endpoint whatsapp
defined('URL_WHATSAPP_API_BCI')  OR define('URL_WHATSAPP_API_BCI', 'http://10.100.20.240:8081/whatsapp/'); // endpoint whatsapp BCI
defined('URL_WHATSAPP_API_STREAMING_CL')  OR define('URL_WHATSAPP_API_STREAMING_CL', 'http://10.100.20.241:8081/whatsapp/'); // endpoint whatsapp BCI
defined('URL_WHATSAPP_API_PERU')  OR define('URL_WHATSAPP_API_PERU', 'http://10.100.20.241:8083/whatsapp/'); // endpoint whatsapp BCI
defined('URL_DFEED')  OR define('URL_DFEED', 'https://dfeed-cl-qa-ws.simpledatacorp.com/TDC.php'); // endpoint soap dfeed qas
defined('URL_DFEED_PRD')  OR define('URL_DFEED_PROD', 'http://in-dfeedprdapp/dfeed/soap/cl/v0.00/TDC.php'); // endpoint soap dfeed prod
defined('URL_API_BOTMAKER')  OR define('URL_API_BOTMAKER', 'https://go.botmaker.com/api/v1.0'); // endpoint api botmaker
defined('PHONE_BOTMAKER_FROM')  OR define('PHONE_BOTMAKER_FROM', '56937521154'); // numero telefono desde

/**
 * Credenciales de acceso a la base de datos.
 */
define('USER_DB', 'dfeedprod');
define('PASS_DB', '#sdcdfeedprod#');
define('HOST_DB', 'in-dfeed-bbdd');
define('PORT_DB', 3306);
define('SCHEMA_DB', 'API_WHATSAPP_SDC');

/**
 * Credenciales mail
 */
define('MAIL_FROM', 'toolbox.sd@simpledatacorp.com');
define('MAIL_FROM_NAME', 'Consent');

/**
 * Constantes comunes de validacion a todas las clases
 */
define('ERROR_CODE_OK', '0');
define('ERROR_DESC_OK', 'OK');
define('ERROR_CODE_NOK', '1');
define('ERROR_DESC_NOK', 'Error not specified');
define('ERROR_CODE_FUNCTION_PARAMETERS', '100');
define('ERROR_DESC_FUNCTION_PARAMETERS', 'Invalid parameters.');
define('ERROR_CODE_INVALID_ACTION', '101');
define('ERROR_DESC_INVALID_ACTION', 'Invalid action.');
define('ERROR_CODE_NOT_IMPLEMENTED', '102');
define('ERROR_DESC_NOT_IMPLEMENTED', 'Not implemented yet');
define('ERROR_CODE_INVALID_RESPONSE', '103');
define('ERROR_DESC_INVALID_RESPONSE', 'Invalid response');
define('ERROR_CODE_JSON_MALFORMED', '2000');
define('ERROR_DESC_JSON_MALFORMED', 'JSON malformed.');
define('ERROR_CODE_INTERNAL', '300');
define('ERROR_DESC_INTERNAL', 'Error, please contact the administrator.');
define('ERROR_CODE_UNAVAILABLE_WORKFLOW', '501');
define('ERROR_DESC_UNAVAILABLE_WORKFLOW', 'Workflow unavaiable.');


// HTTP STATUS CODE
define('ERROR_CODE_SUCCESS', 200);
define('ERROR_DESC_SUCCESS', 'Operation success');
define('ERROR_CODE_BAD_REQUEST', 400);
define('ERROR_DESC_BAD_REQUEST', 'Bad Request');
define('ERROR_CODE_UNAUTHORIZED', 401);
define('ERROR_DESC_UNAUTHORIZED', 'Unauthorized');
define('ERROR_MESSAGE_UNAUTHORIZED', 'You must to authenticate for use this API.');
define('ERROR_CODE_NOT_FOUND', 404);
define('ERROR_DESC_NOT_FOUND', 'Not Found');
define('ERROR_CODE_INTERNAL_SERVER', 500);
define('ERROR_DESC_INTERNAL_SERVER', 'Interval Server Error');
