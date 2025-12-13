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
        public readonly string $description,
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
                'description' => $this->description,
            ],
            'data' => $this->data,
        ];
    }

    /**
     * Create success response
     * @param mixed $data
     * @param string $description
     * @param int $code
     * @return self
     */
    public static function success(mixed $data = null, string $description = 'Success', int $code = 200): self
    {
        return new self($code, $description, $data);
    }

    /**
     * Create error response
     * @param string $description
     * @param int $code
     * @param mixed $data
     * @return self
     */
    public static function error(string $description, int $code = 400, mixed $data = null): self
    {
        return new self($code, $description, $data);
    }
}
