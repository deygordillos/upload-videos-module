<?php

declare(strict_types=1);

namespace App\DAO;

use App\DTO\VideoUploadDTO;
use App\DTO\VideoResponseDTO;
use Libraries\DBConnectorPDO;
use PDO;
use PDOException;

/**
 * Video Data Access Object
 * Handles all database operations for videos
 * 
 * @version 1.0.0
 * @author SimpleData Corp
 */
class VideoDAO extends BaseDAO
{

    /**
     * Insert a new video record
     * @param VideoUploadDTO $video
     * @param string $filePath
     * @return int|null Video ID or null on failure
     */
    public function insert(VideoUploadDTO $video, string $filePath): ?int
    {
        $metadataJson = $video->metadata ? json_encode($video->metadata) : null;
        
        $query = "INSERT INTO videos (
                project_id, video_identifier, original_filename, file_path, 
                file_size, mime_type, upload_ip, user_agent, metadata, 
                status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW())";
        
        $params = [
            $video->projectId,
            $video->videoIdentifier,
            $video->originalFilename,
            $filePath,
            $video->fileSize,
            $video->mimeType,
            $video->uploadIp,
            $video->userAgent,
            $metadataJson
        ];
        
        $result = $this->executeStatement($query, $params);
        
        if ($result && is_int($result)) {
            $this->log->writeLog("{$this->tx} [video_dao] Video inserted successfully: ID={$result}\n");
            
            // Log audit trail
            $this->insertAuditLog($result, 'upload', null, $video->uploadIp, [
                'filename' => $video->originalFilename,
                'size' => $video->fileSize
            ]);
            
            return $result;
        }
        
        return null;
    }

    /**
     * Find video by ID
     * @param int $id
     * @return VideoResponseDTO|null
     */
    public function findById(int $id): ?VideoResponseDTO
    {
        $query = "SELECT id, project_id, video_identifier, original_filename, 
                file_path, file_size, mime_type, duration, width, height,
                metadata, status, created_at, updated_at
            FROM videos
            WHERE id = ? AND deleted_at IS NULL";
        
        $result = $this->executeSelect($query, [$id]);
        
        if (!empty($result)) {
            return VideoResponseDTO::fromArray($result[0]);
        }
        
        return null;
    }

    /**
     * Find video by project and identifier
     * @param string $projectId
     * @param string $videoIdentifier
     * @return VideoResponseDTO|null
     */
    public function findByProjectAndIdentifier(string $projectId, string $videoIdentifier): ?VideoResponseDTO
    {
        $query = "SELECT id, project_id, video_identifier, original_filename, 
                file_path, file_size, mime_type, duration, width, height,
                metadata, status, created_at, updated_at
            FROM videos
            WHERE project_id = ? AND video_identifier = ? AND deleted_at IS NULL";
        
        $result = $this->executeSelect($query, [$projectId, $videoIdentifier]);
        
        if (!empty($result)) {
            return VideoResponseDTO::fromArray($result[0]);
        }
        
        return null;
    }

    /**
     * Find all videos by project
     * @param string $projectId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findByProject(string $projectId, int $limit = 50, int $offset = 0): array
    {
        $query = "SELECT id, project_id, video_identifier, original_filename, 
                file_path, file_size, mime_type, duration, width, height,
                metadata, status, created_at, updated_at
            FROM videos
            WHERE project_id = ? AND deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT {$limit} OFFSET {$offset}";
        
        $result = $this->executeSelect($query, [$projectId]);
        
        $videos = [];
        foreach ($result as $row) {
            $videos[] = VideoResponseDTO::fromArray($row);
        }
        
        return $videos;
    }

    /**
     * Update video status
     * @param int $id
     * @param string $status
     * @param string|null $errorMessage
     * @return bool
     */
    public function updateStatus(int $id, string $status, ?string $errorMessage = null): bool
    {
        $query = "UPDATE videos 
            SET status = ?, error_message = ?, updated_at = NOW()
            WHERE id = ?";
        
        $result = $this->executeStatement($query, [$status, $errorMessage, $id]);
        return is_bool($result) ? $result : false;
    }

    /**
     * Soft delete video
     * @param int $id
     * @return bool
     */
    public function softDelete(int $id): bool
    {
        $query = "UPDATE videos 
            SET deleted_at = NOW(), updated_at = NOW()
            WHERE id = ?";
        
        $result = $this->executeStatement($query, [$id]);
        
        if ($result) {
            $this->insertAuditLog($id, 'delete', null, null, null);
        }
        
        return is_bool($result) ? $result : false;
    }

    /**
     * Insert audit log entry
     * @param int $videoId
     * @param string $action
     * @param string|null $userId
     * @param string|null $ipAddress
     * @param array|null $details
     * @return bool
     */
    private function insertAuditLog(
        int $videoId, 
        string $action, 
        ?string $userId = null, 
        ?string $ipAddress = null, 
        ?array $details = null
    ): bool {
        $query = "INSERT INTO video_audit_log (
                video_id, action, user_id, ip_address, details, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())";
        
        $detailsJson = $details ? json_encode($details) : null;
        
        try {
            $result = $this->executeStatement($query, [
                $videoId, $action, $userId, $ipAddress, $detailsJson
            ]);
            return is_int($result) || $result === true;
        } catch (\Throwable $e) {
            // Don't throw on audit log failure, just log it
            $this->log->writeLog("{$this->tx} [video_dao_warning] Audit log insert failed: " . $e->getMessage() . "\n");
            return false;
        }
    }
}
