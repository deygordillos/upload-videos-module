<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * DTO for video response
 *
 * @version 1.0.0
 * @author SimpleData Corp
 */
final class VideoResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $projectId,
        public readonly string $videoIdentifier,
        public readonly string $originalFilename,
        public readonly string $filePath,
        public readonly int $fileSize,
        public readonly string $mimeType,
        public readonly ?int $duration = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?array $metadata = null,
        public readonly string $status = 'pending',
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {
    }

    /**
     * Convert to array for JSON response
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->projectId,
            'video_identifier' => $this->videoIdentifier,
            'original_filename' => $this->originalFilename,
            'file_path' => $this->filePath,
            'file_size' => $this->fileSize,
            'mime_type' => $this->mimeType,
            'duration' => $this->duration,
            'width' => $this->width,
            'height' => $this->height,
            'metadata' => $this->metadata,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * Create from database row
     * @param array $row
     * @return self
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            projectId: $row['project_id'],
            videoIdentifier: $row['video_identifier'],
            originalFilename: $row['original_filename'],
            filePath: $row['file_path'],
            fileSize: (int)$row['file_size'],
            mimeType: $row['mime_type'],
            duration: isset($row['duration']) ? (int)$row['duration'] : null,
            width: isset($row['width']) ? (int)$row['width'] : null,
            height: isset($row['height']) ? (int)$row['height'] : null,
            metadata: isset($row['metadata']) ? json_decode($row['metadata'], true) : null,
            status: $row['status'] ?? 'pending',
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null
        );
    }
}
