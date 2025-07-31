<?php

namespace App;

require_once __DIR__ . '/Car.php';

class CarRepository
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function save(Car $car): void
    {
        $sql = 'INSERT INTO cars (make, model) VALUES (:make, :model)';
        $statement = $this->db->prepare($sql);
        $statement->execute([
            ':make' => $car->getMake(),
            ':model' => $car->getModel(),
        ]);

        $car->setId((int) $this->db->lastInsertId());
    }

    public function all(): array
    {
        $sql = 'SELECT * FROM cars ORDER BY id DESC';
        $statement = $this->db->query($sql);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $cars = [];
        foreach ($rows as $row) {
            $car = new Car($row['make'], $row['model']);
            $car->setId((int) $row['id']);
            $cars[] = $car;
        }

        return $cars;
    }

    public function find(int $id): ?Car
    {
        $sql = 'SELECT * FROM cars WHERE id = :id';
        $statement = $this->db->prepare($sql);
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $car = new Car($row['make'], $row['model']);
        $car->setId((int) $row['id']);
        return $car;
    }
}

