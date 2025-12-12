<?php

declare(strict_types=1);

namespace App\BLL;

use App\DAO\VideoDAO;
use App\DTO\VideoUploadDTO;
use App\DTO\VideoResponseDTO;
use App\DTO\ApiResponseDTO;
use Libraries\DBConnectorPDO;
use Libraries\DayLog;

/**
 * Video Business Logic Layer
 * Handles video upload, validation, storage and metadata management
 * 
 * @version 1.0.0
 * @author SimpleData Corp
 */
class VideoBLL extends \App\BaseClass
{
    private VideoDAO $dao;
    private string $uploadBasePath;
    private const MAX_FILE_SIZE = 524288000; // 500MB
    private const ALLOWED_EXTENSIONS = ['mp4', 'mov', 'avi', 'wmv', 'webm', 'mkv'];

    public function __construct(DBConnectorPDO $db, string $uploadBasePath = './uploads')
    {
        parent::__construct($db);
        $this->dao = new VideoDAO($db);
        $this->uploadBasePath = rtrim($uploadBasePath, '/\\');
        
        // Create base upload directory if it doesn't exist
        if (!is_dir($this->uploadBasePath)) {
            mkdir($this->uploadBasePath, 0755, true);
        }
    }

    /**
     * Upload and store a video
     * @param VideoUploadDTO $videoDTO
     * @return ApiResponseDTO
     */
    public function uploadVideo(VideoUploadDTO $videoDTO): ApiResponseDTO
    {
        try {
            $this->log->writeLog("{$this->tx} [video_bll] Starting video upload process\n");
            
            // Validate video file
            $this->validateVideoFile($videoDTO);
            
            // Check for duplicate
            $existing = $this->dao->findByProjectAndIdentifier(
                $videoDTO->projectId, 
                $videoDTO->videoIdentifier
            );
            
            if ($existing !== null) {
                $this->log->writeLog("{$this->tx} [video_bll] Duplicate video found: {$videoDTO->videoIdentifier}\n");
                return ApiResponseDTO::error(
                    'Video with this identifier already exists for this project',
                    409,
                    ['existing_video_id' => $existing->id]
                );
            }
            
            // Generate storage path
            $storagePath = $this->generateStoragePath(
                $videoDTO->projectId,
                $videoDTO->videoIdentifier,
                $videoDTO->originalFilename
            );
            
            // Create directory structure
            $this->createDirectoryStructure(dirname($storagePath));
            
            // Move uploaded file to final destination
            if (!$this->moveUploadedFile($videoDTO->tmpFilePath, $storagePath)) {
                $this->log->writeLog("{$this->tx} [video_bll_error] Failed to move uploaded file\n");
                return ApiResponseDTO::error('Failed to store video file', 500);
            }
            
            // Get relative path for database storage
            $relativePath = $this->getRelativePath($storagePath);
            
            // Insert record in database
            $videoId = $this->dao->insert($videoDTO, $relativePath);
            
            if ($videoId === null) {
                // Rollback: delete uploaded file
                @unlink($storagePath);
                $this->log->writeLog("{$this->tx} [video_bll_error] Failed to insert video record\n");
                return ApiResponseDTO::error('Failed to store video metadata', 500);
            }
            
            // Retrieve complete video record
            $video = $this->dao->findById($videoId);
            
            if ($video === null) {
                return ApiResponseDTO::error('Video uploaded but unable to retrieve record', 500);
            }
            
            $this->log->writeLog("{$this->tx} [video_bll] Video uploaded successfully: ID={$videoId}\n");
            
            return ApiResponseDTO::success(
                $video->toArray(),
                'Video uploaded successfully',
                201
            );
            
        } catch (\InvalidArgumentException $e) {
            $this->log->writeLog("{$this->tx} [video_bll_error] Validation error: " . $e->getMessage() . "\n");
            return ApiResponseDTO::error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            $this->log->writeLog("{$this->tx} [video_bll_error] Runtime error: " . $e->getMessage() . "\n");
            return ApiResponseDTO::error('Internal server error', 500);
        } catch (\Throwable $e) {
            $this->log->writeLog("{$this->tx} [video_bll_error] Unexpected error: " . $e->getMessage() . "\n");
            return ApiResponseDTO::error('An unexpected error occurred', 500);
        }
    }

    /**
     * Get video by ID
     * @param int $id
     * @return ApiResponseDTO
     */
    public function getVideoById(int $id): ApiResponseDTO
    {
        try {
            $video = $this->dao->findById($id);
            
            if ($video === null) {
                return ApiResponseDTO::error('Video not found', 404);
            }
            
            return ApiResponseDTO::success($video->toArray());
            
        } catch (\Throwable $e) {
            $this->log->writeLog("{$this->tx} [video_bll_error] Get video error: " . $e->getMessage() . "\n");
            return ApiResponseDTO::error('Failed to retrieve video', 500);
        }
    }

    /**
     * Get videos by project
     * @param string $projectId
     * @param int $page
     * @param int $perPage
     * @return ApiResponseDTO
     */
    public function getVideosByProject(string $projectId, int $page = 1, int $perPage = 50): ApiResponseDTO
    {
        try {
            // Validate project ID
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $projectId)) {
                return ApiResponseDTO::error('Invalid project ID format', 400);
            }
            
            // Validate pagination parameters
            $page = max(1, $page);
            $perPage = min(100, max(1, $perPage));
            $offset = ($page - 1) * $perPage;
            
            $videos = $this->dao->findByProject($projectId, $perPage, $offset);
            
            $videoData = array_map(fn($video) => $video->toArray(), $videos);
            
            return ApiResponseDTO::success([
                'videos' => $videoData,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'count' => count($videoData)
                ]
            ]);
            
        } catch (\Throwable $e) {
            $this->log->writeLog("{$this->tx} [video_bll_error] Get videos error: " . $e->getMessage() . "\n");
            return ApiResponseDTO::error('Failed to retrieve videos', 500);
        }
    }

    /**
     * Delete video
     * @param int $id
     * @return ApiResponseDTO
     */
    public function deleteVideo(int $id): ApiResponseDTO
    {
        try {
            $video = $this->dao->findById($id);
            
            if ($video === null) {
                return ApiResponseDTO::error('Video not found', 404);
            }
            
            // Soft delete in database
            $result = $this->dao->softDelete($id);
            
            if (!$result) {
                return ApiResponseDTO::error('Failed to delete video', 500);
            }
            
            // Optionally delete physical file (commented out for safety)
            // $fullPath = $this->uploadBasePath . '/' . $video->filePath;
            // @unlink($fullPath);
            
            $this->log->writeLog("{$this->tx} [video_bll] Video deleted successfully: ID={$id}\n");
            
            return ApiResponseDTO::success(null, 'Video deleted successfully');
            
        } catch (\Throwable $e) {
            $this->log->writeLog("{$this->tx} [video_bll_error] Delete video error: " . $e->getMessage() . "\n");
            return ApiResponseDTO::error('Failed to delete video', 500);
        }
    }

    /**
     * Validate video file
     * @param VideoUploadDTO $videoDTO
     * @throws \InvalidArgumentException
     */
    private function validateVideoFile(VideoUploadDTO $videoDTO): void
    {
        // Check if temporary file exists
        if (!file_exists($videoDTO->tmpFilePath)) {
            throw new \InvalidArgumentException('Upload file not found');
        }

        // Validate file size
        if ($videoDTO->fileSize > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('File size exceeds maximum allowed (500MB)');
        }

        // Validate file extension
        $extension = strtolower(pathinfo($videoDTO->originalFilename, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('File extension not allowed. Allowed: ' . implode(', ', self::ALLOWED_EXTENSIONS));
        }

        // Validate MIME type (additional check)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMimeType = finfo_file($finfo, $videoDTO->tmpFilePath);
        finfo_close($finfo);

        if (!str_starts_with($detectedMimeType, 'video/')) {
            throw new \InvalidArgumentException('File is not a valid video file');
        }
    }

    /**
     * Generate storage path: uploads/project/year/month/day/identifier/filename
     * @param string $projectId
     * @param string $videoIdentifier
     * @param string $filename
     * @return string
     */
    private function generateStoragePath(string $projectId, string $videoIdentifier, string $filename): string
    {
        $date = new \DateTime();
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');
        
        // Sanitize filename
        $safeFilename = $this->sanitizeFilename($filename);
        
        // Build path: project/year/month/day/identifier/filename
        return sprintf(
            '%s/%s/%s/%s/%s/%s/%s',
            $this->uploadBasePath,
            $projectId,
            $year,
            $month,
            $day,
            $videoIdentifier,
            $safeFilename
        );
    }

    /**
     * Sanitize filename to prevent path traversal
     * @param string $filename
     * @return string
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove any path components
        $filename = basename($filename);
        
        // Remove any non-alphanumeric characters except dots, hyphens, and underscores
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'video_' . uniqid() . '.mp4';
        }
        
        return $filename;
    }

    /**
     * Create directory structure with proper permissions
     * @param string $directory
     * @throws \RuntimeException
     */
    private function createDirectoryStructure(string $directory): void
    {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new \RuntimeException('Failed to create directory structure');
            }
        }
    }

    /**
     * Move uploaded file to destination
     * @param string $source
     * @param string $destination
     * @return bool
     */
    private function moveUploadedFile(string $source, string $destination): bool
    {
        // Use move_uploaded_file if the source is an uploaded file
        if (is_uploaded_file($source)) {
            return move_uploaded_file($source, $destination);
        }
        
        // Fallback to rename for testing purposes
        return rename($source, $destination);
    }

    /**
     * Get relative path from base upload path
     * @param string $fullPath
     * @return string
     */
    private function getRelativePath(string $fullPath): string
    {
        $basePath = realpath($this->uploadBasePath);
        $filePath = realpath($fullPath);
        
        if ($filePath && $basePath && str_starts_with($filePath, $basePath)) {
            return str_replace($basePath . DIRECTORY_SEPARATOR, '', $filePath);
        }
        
        return $fullPath;
    }
}
