<?php

namespace App\Repository;

use App\Core\Database;
use PDO;

class AbsenceRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findAll(): array
    {
        $sql = '
            SELECT a.*, e.name AS employee_name,
                   s.name AS shift_name,
                   s.time_from AS shift_time_from,
                   s.time_to AS shift_time_to
            FROM absence a
            INNER JOIN employee e ON e.id = a.employee_id
            LEFT JOIN shift s ON s.id = a.shift_id
            ORDER BY a.date, e.name
        ';
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $sql = '
            SELECT a.*, e.name AS employee_name,
                   s.name AS shift_name,
                   s.time_from AS shift_time_from,
                   s.time_to AS shift_time_to
            FROM absence a
            INNER JOIN employee e ON e.id = a.employee_id
            LEFT JOIN shift s ON s.id = a.shift_id
            WHERE a.id = :id
        ';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function existsForEmployeeDate(int $employeeId, string $date, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->db->prepare(
                'SELECT id FROM absence WHERE employee_id = :employee_id AND date = :date AND id <> :exclude_id LIMIT 1'
            );
            $stmt->execute([
                'employee_id' => $employeeId,
                'date' => $date,
                'exclude_id' => $excludeId,
            ]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT id FROM absence WHERE employee_id = :employee_id AND date = :date LIMIT 1'
            );
            $stmt->execute([
                'employee_id' => $employeeId,
                'date' => $date,
            ]);
        }

        return (bool)$stmt->fetchColumn();
    }

    public function create(
        int $employeeId,
        string $date,
        ?int $shiftId = null
    ): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO absence (employee_id, date, shift_id)
             VALUES (:employee_id, :date, :shift_id)'
        );
        $stmt->execute([
            'employee_id' => $employeeId,
            'date' => $date,
            'shift_id' => $shiftId,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(
        int $id,
        int $employeeId,
        string $date,
        ?int $shiftId = null
    ): void
    {
        $stmt = $this->db->prepare(
            'UPDATE absence
             SET employee_id = :employee_id,
                 date = :date,
                 shift_id = :shift_id
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'employee_id' => $employeeId,
            'date' => $date,
            'shift_id' => $shiftId,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM absence WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Liefert alle Ausgleichtage im angegebenen Zeitraum (inklusive).
     */
    public function findByDateRange(string $dateFrom, string $dateTo): array
    {
        $sql = '
            SELECT a.*, e.name AS employee_name,
                   s.name AS shift_name,
                   s.time_from AS shift_time_from,
                   s.time_to AS shift_time_to
            FROM absence a
            INNER JOIN employee e ON e.id = a.employee_id
            LEFT JOIN shift s ON s.id = a.shift_id
            WHERE a.date BETWEEN :date_from AND :date_to
            ORDER BY a.date, e.name
        ';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        return $stmt->fetchAll();
    }
}

