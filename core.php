<?php
/**
 * Api - Capacity
 * Slim 4 - Composer
 * CreatedAt: 15-09-2025 
 * @version 1.0 
 * @author Dey Gordillo <dey.gordillo@simpledatacorp.com>
 */

use App\Estructure\BLL\CapacityBLL;
use App\Handlers\HttpErrorHandler;
use App\Handlers\ShutdownHandler;
use App\Utils\DayLog;

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
$displayErrorDetails = true;

$app = AppFactory::create();
$app->setBasePath($_ENV['APP_PATH'] ?? '/');
//$app->setBasePath('/whatsapp/rest/v1');

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
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, false, false);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

// ------------------------------------
//           Rutas
// ------------------------------------

$app->get('/test', function (Request $request, Response $response, $args) {
    $response
        ->getBody()
        ->write( json_encode(['testing' => 'OK']) );
    return $response;
});

$app->group('/core', function (RouteCollectorProxy $group) {
    
    $group->get('/', function ($request, $response, array $args) {

        $body = (object)$request->getQueryParams();
        $body->cantidad = (int)$body->cantidad ?? 0;
        $body->id_pool = (int)$body->id_pool ?? 0;
        $body->periodo = (int)$body->periodo ?? 0;
        $body->id_order = (int)$body->id_order ?? 0;
        // Crear instancia del BLL y pasar los componentes
        $class = new CapacityBLL();
        
        $returnObject = $class->getCapacity($body);
        
        $response
            ->getBody()
            ->write( json_encode($returnObject) );
        $newResponse = $response->withStatus( $returnObject->Return->Code ?? 200 );
        return $newResponse;
    });
    
    $group->post('/schedule', function ($request, $response, array $args) {
        $body = (object)$request->getParsedBody();
        $body->periodo = (int)$body->periodo ?? 0;
        $body->cantidad = (int)$body->cantidad ?? 0;
        $body->id_pool = (int)$body->id_pool ?? 0;
        // Crear instancia del BLL y pasar los componentes
        $class = new CapacityBLL();
        $returnObject = $class->schedule($body);
        
        $response
            ->getBody()
            ->write( json_encode($returnObject) );
        $newResponse = $response->withStatus( $returnObject->Return->Code ?? 200 );
        return $newResponse;
    });
});

try {
    $app->run();
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        "code" => 400, 
        "message" => sprintf('Bad Request: %s', $exception->getMessage())
    ]);
}
