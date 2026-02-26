<?php

namespace App\Repository;

use App\Core\Database;
use PDO;

class RoleRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM role ORDER BY name');
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM role WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name, string $shortcode): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO role (name, shortcode) VALUES (:name, :shortcode)'
        );
        $stmt->execute([
            'name' => $name,
            'shortcode' => $shortcode,
        ]);
    }

    public function update(int $id, string $name, string $shortcode): void
    {
        $stmt = $this->db->prepare(
            'UPDATE role SET name = :name, shortcode = :shortcode WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'shortcode' => $shortcode,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM role WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}

