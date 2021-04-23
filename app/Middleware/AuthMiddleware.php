<?php

namespace App\Middleware;

use App\Estructure\BLL\AuthBLL;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

final class AuthMiddleware 
{
    private $isLogged = false;
    private $dataUser;

    public function __invoke(Request $request, RequestHandler $handler): Response
    {

        $data     = null;
        $request  = $request->withAttribute('isLogged', false);
        
        // Verificar que consuman la API por Authorization
        if ($request->hasHeader('Authorization')) {
            $headerValueArray = $request->getHeaderLine('Authorization');
            $explodeAuth = explode(' ', $headerValueArray);
            $authType = $explodeAuth[0] ?? '';

            if (strtolower($authType) === 'basic') {

                try {
                    // Decodifico el Authorization para sacar usuario y clave
                    $userLogin = preg_replace('/[\s]/', '', base64_decode($explodeAuth[1]) ) ?? '';

                    // Si no envían usuario y contraseña
                    if (empty($userLogin) || $userLogin === ':') { 
                        $data = new \stdClass;
                        $data->code = ERROR_CODE_UNAUTHORIZED;
                        $data->message = ERROR_MESSAGE_UNAUTHORIZED;
                    } else {
                        // Saco usuario y clave del Basic Auth

                        $dataAuth = explode(':', $userLogin);
                        $username = $dataAuth[0] ?? '';
                        $password = $dataAuth[1] ?? '';

                        if (empty($username) || empty($password)) {
                            $data = new \stdClass;
                            $data->code = ERROR_CODE_UNAUTHORIZED;
                            $data->message = ERROR_MESSAGE_UNAUTHORIZED;
                        } else {
                            // Check access
                            $req = new \stdClass;
                            $req->username = $username;
                            $req->password = $password;

                            $auth = new AuthBLL();
                            $login = $auth->login($req);

                            if ( $login->status->code != 200 ) {
                                $data = new \stdClass;
                                $data->code = $login->status->code;
                                $data->message = $login->status->message;
                            } else {
                                $request = $request->withAttribute('isLogged', true);
                                // Busco dato del usuario
                                $req = new \stdClass;
                                $req->username = $username;
                                $auth = new AuthBLL();
                                $dataUserLogin = $auth->getDataByUsername($req);
                                if ($dataUserLogin->status->code === ERROR_CODE_SUCCESS) {
                                    $dataUser = $dataUserLogin->data ?? '';
                                    $request  = $request->withAttribute('dataUser', $dataUser);
                                }
                            }
                        }
                    }
                } catch( \Exception $e) {
                    $data = new \stdClass;
                    $data->code = ERROR_CODE_UNAUTHORIZED;
                    $data->message = ERROR_MESSAGE_UNAUTHORIZED;
                } 
                
            } else {
                $data = new \stdClass;
                $data->code = ERROR_CODE_UNAUTHORIZED;
                $data->message = ERROR_MESSAGE_UNAUTHORIZED;
            }
        } else {
            $data = new \stdClass;
            $data->code = ERROR_CODE_UNAUTHORIZED;
            $data->message = ERROR_MESSAGE_UNAUTHORIZED;
        }        

        if ($data != null) {
            $response = new Response();
            $response->getBody()->write( json_encode($data) );
            $newResponse = $response->withStatus($data->code);// No autorizado
            return $newResponse;
        }
        
        $response = $handler->handle($request);
        return $response;
    }

    final function isLogged() {
        return $this->isLogged;
    }

    public function getDataUser() {
        return $this->dataUser;
    }
}
