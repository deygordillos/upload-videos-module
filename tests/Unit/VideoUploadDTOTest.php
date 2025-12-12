<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\DTO\VideoUploadDTO;

/**
 * Unit tests for VideoUploadDTO
 */
class VideoUploadDTOTest extends TestCase
{
    public function testValidVideoUploadDTO(): void
    {
        $dto = new VideoUploadDTO(
            projectId: 'project_1',
            videoIdentifier: 'video_001',
            originalFilename: 'test_video.mp4',
            tmpFilePath: '/tmp/phpXXXXXX',
            fileSize: 1024000,
            mimeType: 'video/mp4'
        );

        $this->assertEquals('project_1', $dto->projectId);
        $this->assertEquals('video_001', $dto->videoIdentifier);
        $this->assertEquals('test_video.mp4', $dto->originalFilename);
        $this->assertEquals(1024000, $dto->fileSize);
        $this->assertEquals('video/mp4', $dto->mimeType);
    }

    public function testVideoUploadDTOWithNullIdentifier(): void
    {
        // videoIdentifier should be optional (null allowed)
        $dto = new VideoUploadDTO(
            projectId: 'project_1',
            videoIdentifier: null,
            originalFilename: 'test_video.mp4',
            tmpFilePath: '/tmp/phpXXXXXX',
            fileSize: 1024000,
            mimeType: 'video/mp4'
        );

        $this->assertEquals('project_1', $dto->projectId);
        $this->assertNull($dto->videoIdentifier);
        $this->assertEquals('test_video.mp4', $dto->originalFilename);
        $this->assertEquals(1024000, $dto->fileSize);
        $this->assertEquals('video/mp4', $dto->mimeType);
    }

    public function testInvalidProjectIdThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid project ID format');

        new VideoUploadDTO(
            projectId: 'invalid project!',
            videoIdentifier: null,
            originalFilename: 'test.mp4',
            tmpFilePath: '/tmp/test',
            fileSize: 1024,
            mimeType: 'video/mp4'
        );
    }

    public function testFileSizeTooLargeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File size must be between 1 byte and 500MB');

        new VideoUploadDTO(
            projectId: 'project_1',
            videoIdentifier: null,
            originalFilename: 'test.mp4',
            tmpFilePath: '/tmp/test',
            fileSize: 600000000, // 600MB
            mimeType: 'video/mp4'
        );
    }

    public function testInvalidMimeTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported video MIME type');

        new VideoUploadDTO(
            projectId: 'project_1',
            videoIdentifier: null,
            originalFilename: 'test.txt',
            tmpFilePath: '/tmp/test',
            fileSize: 1024,
            mimeType: 'text/plain'
        );
    }

    public function testFromUploadedFile(): void
    {
        $file = [
            'name' => 'test_video.mp4',
            'tmp_name' => '/tmp/phpXXXXXX',
            'size' => 1024000,
            'type' => 'video/mp4',
            'error' => 0
        ];

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test';

        $dto = VideoUploadDTO::fromUploadedFile(
            $file,
            'project_1',
            'video_001',
            ['key' => 'value']
        );

        $this->assertEquals('project_1', $dto->projectId);
        $this->assertEquals('video_001', $dto->videoIdentifier);
        $this->assertEquals('test_video.mp4', $dto->originalFilename);
        $this->assertEquals('127.0.0.1', $dto->uploadIp);
        $this->assertEquals(['key' => 'value'], $dto->metadata);
    }

    public function testFromUploadedFileWithoutVideoIdentifier(): void
    {
        $file = [
            'name' => 'test_video.mp4',
            'tmp_name' => '/tmp/phpXXXXXX',
            'size' => 1024000,
            'type' => 'video/mp4',
            'error' => 0
        ];

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test';

        // videoIdentifier is optional, backend should generate it
        $dto = VideoUploadDTO::fromUploadedFile(
            $file,
            'project_1',
            null,
            ['key' => 'value']
        );

        $this->assertEquals('project_1', $dto->projectId);
        $this->assertNull($dto->videoIdentifier);
        $this->assertEquals('test_video.mp4', $dto->originalFilename);
        $this->assertEquals('127.0.0.1', $dto->uploadIp);
        $this->assertEquals(['key' => 'value'], $dto->metadata);
    }
}
