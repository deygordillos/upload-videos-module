<?php
date_default_timezone_set('America/Santiago');
error_reporting(E_ALL & ~E_DEPRECATED);

define('APP_DEBUG', $_ENV['APP_DEBUG'] ?? 'false');
define('BASE_HOME_PATH', dirname(__FILE__) . '/'); 
define('LOG_PATH', BASE_HOME_PATH . 'log/');

ini_set("log_errors", 1);
ini_set("error_log", LOG_PATH . "/php-error-".date("Ymd").".log");

/**
 * Links
 */
define('URL_WEB_SIMPLEDATA', 'https://www.simpledatacorp.com');
define('PREFIX_FONO_PAIS', 56);

/**
 * Credenciales de acceso a la base de datos.
 */
define('USER_DB', $_ENV['BDD_USER'] ?? '');
define('PASS_DB', $_ENV['BDD_PASS'] ?? '');
define('HOST_DB', $_ENV['BDD_HOST'] ?? '');
define('PORT_DB', (int)($_ENV['BDD_PORT'] ?? 3306));
define('SCHEMA_DB', $_ENV['BDD_SCHEMA'] ?? '');

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
