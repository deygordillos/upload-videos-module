<?php

namespace App\Estructure\DTO;

/**
 * Data Transfer Object para operaciones de Capacity
 * Centraliza todos los datos necesarios para las consultas de capacidad
 */
class CapacityDTO
{
    // Propiedades para getCapacity
    public ?int $poolId = null;
    public ?string $periodo = null;
    public ?array $data = null;

    // Propiedades para getReservedQuota
    public ?int $categoryId = null;
    public ?string $date = null;

    // Propiedades para setReservedQuota
    public ?int $requestedAmount = null;
    public ?int $minEntreViaje = null;

    // Propiedades para getScheduleBlock
    public ?int $dayofweek = null;

    // Propiedades para getDatesFromOrderToSchedule
    public ?int $id_order = null;

    // Propiedades para logging y transacción
    public $log = null;
    public ?string $tx = null;

    /**
     * Constructor que permite inicializar con un array de datos
     * @param array $data Datos iniciales para el DTO
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Convierte el DTO a array para compatibilidad con el sistema legacy
     * @return array
     */
    public function toArray(): array
    {
        $result = [];
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        foreach ($properties as $property) {
            $value = $property->getValue($this);
            if ($value !== null) {
                $result[$property->getName()] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Crea un DTO desde un array de datos
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): self
    {
        return new static($data);
    }

    /**
     * Valida que los campos requeridos para getCapacity estén presentes
     * @return bool
     */
    public function isValidForGetCapacity(): bool
    {
        return $this->poolId !== null;
    }

    /**
     * Valida que los campos requeridos para getReservedQuota estén presentes
     * @return bool
     */
    public function isValidForGetReservedQuota(): bool
    {
        return $this->categoryId !== null && 
               $this->date !== null && 
               $this->poolId !== null && 
               $this->periodo !== null;
    }

    /**
     * Valida que los campos requeridos para setReservedQuota estén presentes
     * @return bool
     */
    public function isValidForSetReservedQuota(): bool
    {
        return $this->categoryId !== null && 
               $this->date !== null && 
               $this->poolId !== null && 
               $this->requestedAmount !== null && 
               $this->minEntreViaje !== null && 
               $this->periodo !== null;
    }

    /**
     * Valida que los campos requeridos para getScheduleBlock estén presentes
     * @return bool
     */
    public function isValidForGetScheduleBlock(): bool
    {
        return $this->dayofweek !== null && $this->date !== null;
    }

    /**
     * Valida que los campos requeridos para getDatesFromOrderToSchedule estén presentes
     * @return bool
     */
    public function isValidForGetDatesFromOrderToSchedule(): bool
    {
        return $this->id_order !== null;
    }

    // Getters y Setters
    public function getPoolId(): ?int
    {
        return $this->poolId;
    }

    public function setPoolId(?int $poolId): self
    {
        $this->poolId = $poolId;
        return $this;
    }

    public function getPeriodo(): ?string
    {
        return $this->periodo;
    }

    public function setPeriodo(?string $periodo): self
    {
        $this->periodo = $periodo;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function setCategoryId(?int $categoryId): self
    {
        $this->categoryId = $categoryId;
        return $this;
    }

    public function getDate(): ?string
    {
        return $this->date;
    }

    public function setDate(?string $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getRequestedAmount(): ?int
    {
        return $this->requestedAmount;
    }

    public function setRequestedAmount(?int $requestedAmount): self
    {
        $this->requestedAmount = $requestedAmount;
        return $this;
    }

    public function getMinEntreViaje(): ?int
    {
        return $this->minEntreViaje;
    }

    public function setMinEntreViaje(?int $minEntreViaje): self
    {
        $this->minEntreViaje = $minEntreViaje;
        return $this;
    }

    public function getDayofweek(): ?int
    {
        return $this->dayofweek;
    }

    public function setDayofweek(?int $dayofweek): self
    {
        $this->dayofweek = $dayofweek;
        return $this;
    }

    public function getIdOrder(): ?int
    {
        return $this->id_order;
    }

    public function setIdOrder(?int $id_order): self
    {
        $this->id_order = $id_order;
        return $this;
    }

    public function getLog()
    {
        return $this->log;
    }

    public function setLog($log): self
    {
        $this->log = $log;
        return $this;
    }

    public function getTx(): ?string
    {
        return $this->tx;
    }

    public function setTx(?string $tx): self
    {
        $this->tx = $tx;
        return $this;
    }
}