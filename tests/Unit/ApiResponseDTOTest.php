<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\DTO\ApiResponseDTO;

/**
 * Unit tests for ApiResponseDTO
 */
class ApiResponseDTOTest extends TestCase
{
    public function testSuccessResponse(): void
    {
        $data = ['id' => 1, 'name' => 'Test Video'];
        $response = ApiResponseDTO::success($data, 'Operation successful', 200);

        $this->assertEquals(200, $response->code);
        $this->assertEquals('Operation successful', $response->description);
        $this->assertEquals($data, $response->data);

        $array = $response->toArray();
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertEquals(200, $array['status']['code']);
        $this->assertEquals('Operation successful', $array['status']['description']);
        $this->assertEquals($data, $array['data']);
    }

    public function testErrorResponse(): void
    {
        $response = ApiResponseDTO::error('Validation error', 400);

        $this->assertEquals(400, $response->code);
        $this->assertEquals('Validation error', $response->description);
        $this->assertNull($response->data);

        $array = $response->toArray();
        $this->assertEquals(400, $array['status']['code']);
        $this->assertEquals('Validation error', $array['status']['description']);
    }

    public function testErrorResponseWithData(): void
    {
        $errorData = ['field' => 'email', 'error' => 'Invalid format'];
        $response = ApiResponseDTO::error('Validation failed', 422, $errorData);

        $this->assertEquals(422, $response->code);
        $this->assertEquals('Validation failed', $response->description);
        $this->assertEquals($errorData, $response->data);

        $array = $response->toArray();
        $this->assertEquals($errorData, $array['data']);
    }

    public function testDefaultSuccessMessage(): void
    {
        $response = ApiResponseDTO::success(['test' => 'data']);

        $this->assertEquals(200, $response->code);
        $this->assertEquals('Success', $response->description);
    }
}
