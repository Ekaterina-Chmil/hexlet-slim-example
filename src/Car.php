<?php

class Car
{
    private ?int $id = null;
    private string $make;
    private string $model;

    public function __construct(string $make = '', string $model = '')
    {
        $this->make = $make;
        $this->model = $model;
    }

    public static function fromArray(array $data): self
    {
        return new self($data[0], $data[1]);
    }

    public function getMake(): string
    {
        return $this->make;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function exists(): bool
    {
        return $this->id !== null;
    }
}

