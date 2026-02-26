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
        $sql = 'SELECT r.*, s.name AS shift_name, s.weekday, s.time_from, s.time_to, ro.name AS role_name, ro.shortcode,
                (SELECT GROUP_CONCAT(sw.weekday ORDER BY sw.weekday) FROM shift_weekday sw WHERE sw.shift_id = r.shift_id) AS shift_weekdays_concat
                FROM rule r
                JOIN shift s ON r.shift_id = s.id
                JOIN role ro ON r.role_id = ro.id
                ORDER BY s.weekday, s.time_from, ro.name';
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $concat = $row['shift_weekdays_concat'] ?? null;
            if ($concat !== null && $concat !== '') {
                $row['shift_weekdays'] = array_map('intval', explode(',', $concat));
            } else {
                $row['shift_weekdays'] = [isset($row['weekday']) ? (int) $row['weekday'] : 0];
            }
            unset($row['shift_weekdays_concat']);
        }
        return $rows;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM rule WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $shiftId, int $roleId, int $requiredCount, bool $requiredCountExact = false): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO rule (shift_id, role_id, required_count, required_count_exact) VALUES (:shift_id, :role_id, :required_count, :required_count_exact)'
        );
        $stmt->execute([
            'shift_id' => $shiftId,
            'role_id' => $roleId,
            'required_count' => $requiredCount,
            'required_count_exact' => $requiredCountExact ? 1 : 0,
        ]);
    }

    public function update(int $id, int $shiftId, int $roleId, int $requiredCount, bool $requiredCountExact = false): void
    {
        $stmt = $this->db->prepare(
            'UPDATE rule SET shift_id = :shift_id, role_id = :role_id, required_count = :required_count, required_count_exact = :required_count_exact WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'shift_id' => $shiftId,
            'role_id' => $roleId,
            'required_count' => $requiredCount,
            'required_count_exact' => $requiredCountExact ? 1 : 0,
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

