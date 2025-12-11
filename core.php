<?php
/**
 * Api - Capacity
 * Slim 4 - Composer
 * CreatedAt: 15-09-2025 
 * @version 1.0 
 * @author Dey Gordillo <dey.gordillo@simpledatacorp.com>
 */

use App\Routes\VideoRoutes;
use App\Handlers\HttpErrorHandler;
use App\Handlers\ShutdownHandler;
use App\Middleware\VideoAuthMiddleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Routing\RouteCollectorProxy;

require_once dirname(__FILE__) . '/vendor/autoload.php';

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (\Throwable $th) {
    echo "No .env file found";
}
require_once dirname(__FILE__) . '/config.php';

// Set that to your needs
$displayErrorDetails = APP_DEBUG === 'true' ? true : false;

$app = AppFactory::create();

// Detectar basePath automáticamente o usar el configurado en .env
if (isset($_ENV['APP_PATH']) && $_ENV['APP_PATH'] !== '' && $_ENV['APP_PATH'] !== '/') {
    $app->setBasePath($_ENV['APP_PATH']);
} else {
    // Detectar automáticamente desde SCRIPT_NAME
    // Si SCRIPT_NAME es /liv/komatsu/cl/v0.00.dev/core.php
    // entonces basePath debe ser /liv/komatsu/cl/v0.00.dev
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = dirname($scriptName);
    if ($basePath !== '/' && $basePath !== '\\' && $basePath !== '.') {
        $app->setBasePath($basePath);
    }
}

$callableResolver = $app->getCallableResolver();
$responseFactory = $app->getResponseFactory();

$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();

$errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);
$shutdownHandler = new ShutdownHandler($request, $errorHandler, $displayErrorDetails);
register_shutdown_function($shutdownHandler);


// Carga el middleWare de autentización
//$app->add(AuthMiddleware::class);

// This middleware will append the response header Access-Control-Allow-Methods with all allowed methods
$app->add(function (Request $request, RequestHandlerInterface $handler): Response {
    //$routeContext = RouteContext::fromRequest($request);
    //$routingResults = $routeContext->getRoutingResults();
   
    $response = $handler->handle($request);

    $response = $response->withHeader('Access-Control-Allow-Origin', '*');
    $response = $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE');
    $response = $response->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept');

    // Optional: Allow Ajax CORS requests with Authorization header
    // $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
    $return = $response->withHeader(
        'Content-type',
        'application/json; charset=utf-8'
    );
    return $return;
});


// Parse json, form data and xml
$app->addBodyParsingMiddleware();

// Add Routing Middleware
$app->addRoutingMiddleware();
// Add Error Handling Middleware
$errorMiddleware = $app->addErrorMiddleware(true, false, false); // Force true for debugging
// Temporarily disabled to see actual errors
// $errorMiddleware->setDefaultErrorHandler($errorHandler);

// ------------------------------------
//           Rutas
// ------------------------------------

$app->get('/test', function (Request $request, Response $response, $args) {
    $response
        ->getBody()
        ->write( json_encode(['testing' => 'OK']) );
    return $response;
});

// Video Upload API Routes (con autenticación)
$app->group('/v1/videos', function (RouteCollectorProxy $group) {
    VideoRoutes::register($group);
})->add(new VideoAuthMiddleware());


try {
    $app->run();
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        "code" => 400, 
        "description" => sprintf('Bad Request: %s', $exception->getMessage())
    ]);
}
