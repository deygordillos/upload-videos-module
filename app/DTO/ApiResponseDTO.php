<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Standard API Response DTO
 * 
 * @version 1.0.0
 * @author SimpleData Corp
 */
final class ApiResponseDTO
{
    public function __construct(
        public readonly int $code,
        public readonly string $message,
        public readonly mixed $data = null
    ) {
    }

    /**
     * Convert to array for JSON response
     * @return array
     */
    public function toArray(): array
    {
        return [
            'status' => [
                'code' => $this->code,
                'message' => $this->message,
            ],
            'data' => $this->data,
        ];
    }

    /**
     * Create success response
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return self
     */
    public static function success(mixed $data = null, string $message = 'Success', int $code = 200): self
    {
        return new self($code, $message, $data);
    }

    /**
     * Create error response
     * @param string $message
     * @param int $code
     * @param mixed $data
     * @return self
     */
    public static function error(string $message, int $code = 400, mixed $data = null): self
    {
        return new self($code, $message, $data);
    }
}
