<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\BLL\VideoBLL;
use Libraries\DBConnectorPDO;

/**
 * Integration tests for VideoBLL with real file upload
 * These tests require a database connection and file system access
 */
class VideoBLLIntegrationTest extends TestCase
{
    private VideoBLL $videoBLL;
    private DBConnectorPDO $db;
    private string $testUploadPath;
    private string $testVideoPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup test database connection (positional parameters)
        $this->db = new DBConnectorPDO(
            $_ENV['BDD_USER'] ?? 'root',
            $_ENV['BDD_PASS'] ?? '',
            $_ENV['BDD_HOST'] ?? 'localhost',
            (int)($_ENV['BDD_PORT'] ?? 3306),
            $_ENV['BDD_SCHEMA'] ?? 'sdc_videos'
        );

        // Verify database connection
        if ($this->db->getError() !== '0') {
            $this->markTestSkipped('Database connection failed: ' . $this->db->getErrorDescription());
        }

        // Verify videos table exists
        $checkTable = $this->db->executeStmtResultAssoc("SHOW TABLES LIKE 'videos'");
        if (empty($checkTable)) {
            $this->markTestSkipped('Videos table does not exist. Run migrations first.');
        }

        // Setup test upload directory
        $this->testUploadPath = BASE_HOME_PATH . 'uploads/test_' . time();
        if (!is_dir($this->testUploadPath)) {
            mkdir($this->testUploadPath, 0777, true);
        }

        $this->videoBLL = new VideoBLL($this->db, $this->testUploadPath);

        // Path to test video file
        $this->testVideoPath = __DIR__ . '/../fixtures/videotest_20251211.mp4';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Cleanup: remove test uploads
        if (is_dir($this->testUploadPath)) {
            $this->removeDirectory($this->testUploadPath);
        }
    }

    public function testUploadRealVideoFile(): void
    {
        // Skip test if video file doesn't exist
        if (!file_exists($this->testVideoPath)) {
            $this->markTestSkipped('Test video file not found: ' . $this->testVideoPath);
        }

        // Create VideoUploadDTO
        $videoDTO = new \App\DTO\VideoUploadDTO(
            projectId: 'PROJECT_CI_TEST',
            videoIdentifier: 'VIDEO_CI_' . time(),
            originalFilename: 'videotest_20251211.mp4',
            tmpFilePath: $this->testVideoPath,
            fileSize: filesize($this->testVideoPath),
            mimeType: 'video/mp4'
        );

        // Execute upload
        $result = $this->videoBLL->uploadVideo($videoDTO);

        // Debug output if test fails
        if ($result->code !== 201) {
            echo "\nDEBUG - Upload failed:\n";
            echo "Code: {$result->code}\n";
            echo "Description: {$result->description}\n";
            if (isset($result->data)) {
                echo "Data: " . json_encode($result->data) . "\n";
            }
        }

        // Assertions
        $this->assertInstanceOf(\App\DTO\ApiResponseDTO::class, $result);
        $this->assertEquals(201, $result->code, 'Upload should return 201 status');
        $this->assertIsArray($result->data);
        $this->assertArrayHasKey('id', $result->data);
        
        $videoId = $result->data['id'];
        $this->assertGreaterThan(0, $videoId);

        // Verify file was moved to correct location
        $this->assertArrayHasKey('file_path', $result->data);
        $filePath = $this->testUploadPath . '/' . $result->data['file_path'];
        $this->assertFileExists($filePath, 'Uploaded video file should exist');

        // Verify file size matches
        $this->assertEquals(
            filesize($this->testVideoPath),
            filesize($filePath),
            'Uploaded file size should match original'
        );
    }

    public function testUploadVideoWithInvalidMimeType(): void
    {
        // Create a temporary text file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'This is not a video');

        // Expect InvalidArgumentException when creating DTO with invalid MIME type
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported video MIME type');

        // Create VideoUploadDTO with invalid MIME type (should throw exception)
        $videoDTO = new \App\DTO\VideoUploadDTO(
            projectId: 'PROJECT_CI_TEST',
            videoIdentifier: 'VIDEO_INVALID',
            originalFilename: 'fake_video.mp4',
            tmpFilePath: $tempFile,
            fileSize: filesize($tempFile),
            mimeType: 'text/plain'
        );

        // Cleanup
        @unlink($tempFile);
    }

    public function testUploadVideoWithMissingRequiredFields(): void
    {
        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'dummy content');

        // Expect InvalidArgumentException when creating DTO with empty identifier
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Video identifier must be between 1 and 100 characters');

        // Create VideoUploadDTO with empty identifier (should throw exception)
        $videoDTO = new \App\DTO\VideoUploadDTO(
            projectId: 'PROJECT_CI_TEST',
            videoIdentifier: '', // Empty identifier should fail validation
            originalFilename: 'test.mp4',
            tmpFilePath: $tempFile,
            fileSize: filesize($tempFile),
            mimeType: 'video/mp4'
        );

        // Cleanup
        @unlink($tempFile);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
