<?php

namespace App\Repository;

use App\Core\Database;
use PDO;

class RuleRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findAllWithDetails(): array
    {
        $sql = 'SELECT r.*, s.name AS shift_name, s.weekday, s.time_from, s.time_to, ro.name AS role_name, ro.shortcode
                FROM rule r
                JOIN shift s ON r.shift_id = s.id
                JOIN role ro ON r.role_id = ro.id
                ORDER BY s.weekday, s.time_from, ro.name';
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM rule WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $shiftId, int $roleId, int $requiredCount): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO rule (shift_id, role_id, required_count) VALUES (:shift_id, :role_id, :required_count)'
        );
        $stmt->execute([
            'shift_id' => $shiftId,
            'role_id' => $roleId,
            'required_count' => $requiredCount,
        ]);
    }

    public function update(int $id, int $shiftId, int $roleId, int $requiredCount): void
    {
        $stmt = $this->db->prepare(
            'UPDATE rule SET shift_id = :shift_id, role_id = :role_id, required_count = :required_count WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'shift_id' => $shiftId,
            'role_id' => $roleId,
            'required_count' => $requiredCount,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM rule WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function findByShift(int $shiftId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM rule WHERE shift_id = :shift_id');
        $stmt->execute(['shift_id' => $shiftId]);
        return $stmt->fetchAll();
    }
}

