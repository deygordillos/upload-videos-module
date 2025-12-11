<?php

declare(strict_types=1);

namespace App\Routes;

use App\BLL\VideoBLL;
use App\DTO\VideoUploadDTO;
use App\DTO\ApiResponseDTO;
use App\Middleware\ApiAuthMiddleware;
use Libraries\DBConnectorPDO;
use Libraries\DayLog;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Video Upload Routes for Slim 4
 * 
 * @version 1.0.0
 * @author SimpleData Corp
 */
class VideoRoutes
{
    /**
     * Register video routes
     * @param RouteCollectorProxy $group
     */
    public static function register(RouteCollectorProxy $group): void
    {
        // Health check
        $group->get('/health', function (Request $request, Response $response) {
            $tx = substr(uniqid(), 3);
            $db = new DBConnectorPDO(USER_DB, PASS_DB, HOST_DB, PORT_DB, SCHEMA_DB);
            $db->setTx($tx);
            
            $dbStatus = 'unknown';
            try {
                $db->openConnection();
                $dbStatus = ($db->getError() === DBConnectorPDO::ERROR_CODE_OK) ? 'connected' : 'error';
            } catch (\Throwable $e) {
                $dbStatus = 'error: ' . $e->getMessage();
            }
            
            $result = [
                'status' => [
                    'code' => 200,
                    'description' => 'API is running'
                ],
                'data' => [
                    'service' => 'Video Upload API',
                    'version' => '1.0.0',
                    'database' => $dbStatus,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        });

        // Upload video
        $group->post('/upload', function (Request $request, Response $response) {
            $tx = substr(uniqid(), 3);
            $log = new DayLog(BASE_HOME_PATH, 'VideoUpload');
            
            try {
                // Initialize database connection
                $db = new DBConnectorPDO(USER_DB, PASS_DB, HOST_DB, PORT_DB, SCHEMA_DB);
                $db->setTx($tx);
                $db->openConnection();
                
                // Get uploaded files
                $uploadedFiles = $request->getUploadedFiles();
                $parsedBody = $request->getParsedBody();
                
                // Validate required fields
                if (empty($parsedBody['project_id'])) {
                    $result = ApiResponseDTO::error('project_id is required', 400);
                    $response->getBody()->write(json_encode($result->toArray()));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }

                if (empty($parsedBody['video_identifier'])) {
                    $result = ApiResponseDTO::error('video_identifier is required', 400);
                    $response->getBody()->write(json_encode($result->toArray()));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }

                // Validate file upload
                if (!isset($uploadedFiles['video'])) {
                    $result = ApiResponseDTO::error('No video file provided', 400);
                    $response->getBody()->write(json_encode($result->toArray()));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }

                $videoFile = $uploadedFiles['video'];
                if ($videoFile->getError() !== UPLOAD_ERR_OK) {
                    $result = ApiResponseDTO::error('File upload error: ' . $videoFile->getError(), 400);
                    $response->getBody()->write(json_encode($result->toArray()));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }

                // Parse metadata if provided
                $metadata = null;
                if (!empty($parsedBody['metadata'])) {
                    $metadata = json_decode($parsedBody['metadata'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $result = ApiResponseDTO::error('Invalid metadata JSON', 400);
                        $response->getBody()->write(json_encode($result->toArray()));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                    }
                }

                // Create DTO
                $videoDTO = new VideoUploadDTO(
                    projectId: $parsedBody['project_id'],
                    videoIdentifier: $parsedBody['video_identifier'],
                    originalFilename: $videoFile->getClientFilename(),
                    tmpFilePath: $videoFile->getStream()->getMetadata('uri'),
                    fileSize: $videoFile->getSize(),
                    mimeType: $videoFile->getClientMediaType(),
                    uploadIp: $_SERVER['REMOTE_ADDR'] ?? null,
                    userAgent: $_SERVER['HTTP_USER_AGENT'] ?? null,
                    metadata: $metadata
                );

                // Get upload path from environment
                $uploadPath = $_ENV['UPLOAD_PATH'] ?? './uploads';

                // Process upload
                $videoBLL = new VideoBLL($db, $uploadPath);
                $apiResponse = $videoBLL->uploadVideo($videoDTO);
                
                $response->getBody()->write(json_encode($apiResponse->toArray()));
                return $response->withHeader('Content-Type', 'application/json')->withStatus($apiResponse->code);
                
            } catch (\InvalidArgumentException $e) {
                $log->writeLog("{$tx} [upload_error] Validation error: " . $e->getMessage() . "\n");
                $result = ApiResponseDTO::error($e->getMessage(), 400);
                $response->getBody()->write(json_encode($result->toArray()));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            } catch (\Throwable $e) {
                $log->writeLog("{$tx} [upload_error] Unexpected error: " . $e->getMessage() . "\n");
                $log->writeLog("{$tx} [upload_error] Stack trace: " . $e->getTraceAsString() . "\n");
                // Temporary: show actual error for debugging
                $result = ApiResponseDTO::error('Failed to upload video: ' . $e->getMessage(), 500);
                $response->getBody()->write(json_encode($result->toArray()));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
        });

        // Get video by ID
        $group->get('/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
            $tx = substr(uniqid(), 3);
            $db = new DBConnectorPDO(USER_DB, PASS_DB, HOST_DB, PORT_DB, SCHEMA_DB);
            $db->setTx($tx);
            $db->openConnection();
            
            $videoBLL = new VideoBLL($db);
            $apiResponse = $videoBLL->getVideoById((int)$args['id']);
            
            $response->getBody()->write(json_encode($apiResponse->toArray()));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($apiResponse->code);
        });

        // Get videos by project
        $group->get('/project/{projectId:[a-zA-Z0-9_-]+}', function (Request $request, Response $response, array $args) {
            $tx = substr(uniqid(), 3);
            $db = new DBConnectorPDO(USER_DB, PASS_DB, HOST_DB, PORT_DB, SCHEMA_DB);
            $db->setTx($tx);
            $db->openConnection();
            
            $queryParams = $request->getQueryParams();
            $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
            $perPage = isset($queryParams['per_page']) ? (int)$queryParams['per_page'] : 50;
            
            $videoBLL = new VideoBLL($db);
            $apiResponse = $videoBLL->getVideosByProject($args['projectId'], $page, $perPage);
            
            $response->getBody()->write(json_encode($apiResponse->toArray()));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($apiResponse->code);
        });

        // Delete video
        $group->delete('/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
            $tx = substr(uniqid(), 3);
            $db = new DBConnectorPDO(USER_DB, PASS_DB, HOST_DB, PORT_DB, SCHEMA_DB);
            $db->setTx($tx);
            $db->openConnection();
            
            $videoBLL = new VideoBLL($db);
            $apiResponse = $videoBLL->deleteVideo((int)$args['id']);
            
            $response->getBody()->write(json_encode($apiResponse->toArray()));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($apiResponse->code);
        });
    }
}
