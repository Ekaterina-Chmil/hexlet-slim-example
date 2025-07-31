<?php

namespace App;

class UserRepository
{
    private $users;

    public function __construct()
    {
        // Имитация базы данных
        $this->users = [
            1 => ['id' => 1, 'name' => 'Катя', 'email' => 'katya@example.com'],
            2 => ['id' => 2, 'name' => 'Максим', 'email' => 'max@example.com'],
        ];
    }

    public function find($id)
    {
        return $this->users[$id] ?? null;
    }

    public function save($user)
    {
        $this->users[$user['id']] = $user;
    }
}

