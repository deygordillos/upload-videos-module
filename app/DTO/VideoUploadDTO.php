<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * DTO for video upload request
 * 
 * @version 1.0.0
 * @author SimpleData Corp
 */
final class VideoUploadDTO
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $videoIdentifier,
        public readonly string $originalFilename,
        public readonly string $tmpFilePath,
        public readonly int $fileSize,
        public readonly string $mimeType,
        public readonly ?string $uploadIp = null,
        public readonly ?string $userAgent = null,
        public readonly ?array $metadata = null
    ) {
        $this->validate();
    }

    /**
     * Validate DTO data
     * @throws \InvalidArgumentException
     */
    private function validate(): void
    {
        // Validate project ID (alphanumeric, underscore, hyphen only)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $this->projectId)) {
            throw new \InvalidArgumentException('Invalid project ID format');
        }

        // Validate video identifier
        if (empty($this->videoIdentifier) || strlen($this->videoIdentifier) > 100) {
            throw new \InvalidArgumentException('Video identifier must be between 1 and 100 characters');
        }

        // Validate filename
        if (empty($this->originalFilename) || strlen($this->originalFilename) > 255) {
            throw new \InvalidArgumentException('Filename must be between 1 and 255 characters');
        }

        // Validate file size (max 500MB)
        if ($this->fileSize <= 0 || $this->fileSize > 524288000) {
            throw new \InvalidArgumentException('File size must be between 1 byte and 500MB');
        }

        // Validate MIME type
        $allowedMimeTypes = [
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-ms-wmv',
            'video/webm',
            'video/x-matroska'
        ];

        if (!in_array($this->mimeType, $allowedMimeTypes, true)) {
            throw new \InvalidArgumentException('Unsupported video MIME type: ' . $this->mimeType);
        }
    }

    /**
     * Create from upload file array
     * @param array $file $_FILES array element
     * @param string $projectId
     * @param string $videoIdentifier
     * @param array|null $metadata
     * @return self
     */
    public static function fromUploadedFile(
        array $file,
        string $projectId,
        string $videoIdentifier,
        ?array $metadata = null
    ): self {
        return new self(
            projectId: $projectId,
            videoIdentifier: $videoIdentifier,
            originalFilename: basename($file['name']),
            tmpFilePath: $file['tmp_name'],
            fileSize: (int)$file['size'],
            mimeType: $file['type'],
            uploadIp: $_SERVER['REMOTE_ADDR'] ?? null,
            userAgent: $_SERVER['HTTP_USER_AGENT'] ?? null,
            metadata: $metadata
        );
    }
}
