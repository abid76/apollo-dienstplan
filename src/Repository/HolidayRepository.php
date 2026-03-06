<?php

namespace App\Repository;

use App\Core\Database;
use PDO;

class HolidayRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findAll(): array
    {
        $sql = '
            SELECT h.*, e.name AS employee_name
            FROM holiday h
            INNER JOIN employee e ON e.id = h.employee_id
            ORDER BY h.date_from, h.date_to, e.name
        ';
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $sql = '
            SELECT h.*, e.name AS employee_name
            FROM holiday h
            INNER JOIN employee e ON e.id = h.employee_id
            WHERE h.id = :id
        ';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $employeeId, string $dateFrom, string $dateTo): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO holiday (employee_id, date_from, date_to) VALUES (:employee_id, :date_from, :date_to)'
        );
        $stmt->execute([
            'employee_id' => $employeeId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $employeeId, string $dateFrom, string $dateTo): void
    {
        $stmt = $this->db->prepare(
            'UPDATE holiday SET employee_id = :employee_id, date_from = :date_from, date_to = :date_to WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'employee_id' => $employeeId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM holiday WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Liefert alle Urlaube, die den angegebenen Zeitraum (inklusive) überschneiden.
     */
    public function findByDateRange(string $dateFrom, string $dateTo): array
    {
        $sql = '
            SELECT h.*, e.name AS employee_name
            FROM holiday h
            INNER JOIN employee e ON e.id = h.employee_id
            WHERE h.date_from <= :date_to AND h.date_to >= :date_from
            ORDER BY h.date_from, h.date_to, e.name
        ';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        return $stmt->fetchAll();
    }
}

