<?php
/**
 * Api - Smartway
 * Slim 4 - Composer
 * CreatedAt: 2021-03-26
 * UpdatedAt: 2021-03-26 
 * @version 1.0 
 * @author Dey Gordillo <dey.gordillo@simpledatacorp.com>
 */

use App\Estructure\BLL\AuthBLL;
use App\Estructure\BLL\WhatsappClassBLL;
use App\Handlers\HttpErrorHandler;
use App\Handlers\ShutdownHandler;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Routing\RouteCollectorProxy;
//use Slim\Routing\RouteContext;

use Psr\Http\Message\StreamInterface;

require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/vendor/autoload.php';

use App\Middleware\AuthMiddleware;

// Set that to your needs
$displayErrorDetails = true;

$app = AppFactory::create();
//$app->setBasePath('/v1');
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
        ->write( json_encode(['Testing' => 'OK']) );
    return $response;
});

$app->group('/whatsapp', function (RouteCollectorProxy $group) {
    
    $group->put('/token', function ($request, $response, array $args) {
       
        $class = new WhatsappClassBLL();
        $returnObject = $class->updateTokenWhatsapp();
       
        $returnObject->links = new stdClass;
        $httpProtocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $urlSelf = $httpProtocol . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] ?? '';
        $returnObject->links->self = $urlSelf;

        $response
            ->getBody()
            ->write( json_encode($returnObject) );
        $newResponse = $response->withStatus( $returnObject->status->code );
        return $newResponse;
    })->add(AuthMiddleware::class);

    $group->post('/message', function ($request, $response, array $args) {
        //$dataUser = $request->getAttribute('dataUser');
       
        $body = (object)$request->getParsedBody();
        $class = new WhatsappClassBLL();
        $returnObject = $class->sendMessageWhastapp($body);
        
        $returnObject->links = new stdClass;
        $httpProtocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $urlSelf = $httpProtocol . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] ?? '';
        $returnObject->links->self = $urlSelf;

        $response
            ->getBody()
            ->write( json_encode($returnObject) );
        $newResponse = $response->withStatus( $returnObject->status->code );
        return $newResponse;
    })->add(AuthMiddleware::class);

    $group->post('/answer', function ($request, $response, array $args) {
        //$dataUser = $request->getAttribute('dataUser');
       
        $body = (object)$request->getParsedBody();
        $class = new WhatsappClassBLL();
        $returnObject = $class->saveMessageWhastapp($body);
        
        $returnObject->links = new stdClass;
        $httpProtocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $urlSelf = $httpProtocol . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] ?? '';
        $returnObject->links->self = $urlSelf;

        $response
            ->getBody()
            ->write( json_encode($returnObject) );
        $newResponse = $response->withStatus( $returnObject->status->code );
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
