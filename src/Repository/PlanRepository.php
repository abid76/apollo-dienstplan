<?php

namespace App\Repository;

use App\Core\Database;
use PDO;

class PlanRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Alle Pläne, neueste zuerst (nach Startdatum absteigend).
     */
    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM plan ORDER BY start_date DESC, id DESC');
        return $stmt->fetchAll();
    }

    public function createPlan(string $startDate, int $weeks): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO plan (start_date, weeks) VALUES (:start_date, :weeks)'
        );
        $stmt->execute([
            'start_date' => $startDate,
            'weeks' => $weeks,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function addEntry(
        int $planId,
        string $date,
        int $shiftId,
        int $employeeId,
        int $roleId
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO plan_entry (plan_id, date, shift_id, employee_id, role_id)
             VALUES (:plan_id, :date, :shift_id, :employee_id, :role_id)'
        );
        $stmt->execute([
            'plan_id' => $planId,
            'date' => $date,
            'shift_id' => $shiftId,
            'employee_id' => $employeeId,
            'role_id' => $roleId,
        ]);
    }

    public function getPlan(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plan WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Plan mit dem angegebenen Startdatum (z. B. Vorwoche). Bei mehreren Treffern der neueste.
     */
    public function findByStartDate(string $startDate): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM plan WHERE start_date = :start_date ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['start_date' => $startDate]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM plan WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function getEntriesWithDetails(int $planId): array
    {
        $sql = 'SELECT pe.*, s.name AS shift_name, s.weekday, s.time_from, s.time_to,
                       e.name AS employee_name,
                       r.name AS role_name, r.shortcode
                FROM plan_entry pe
                JOIN shift s ON pe.shift_id = s.id
                JOIN employee e ON pe.employee_id = e.id
                JOIN role r ON pe.role_id = r.id
                WHERE pe.plan_id = :plan_id
                ORDER BY pe.date, s.time_from, e.name';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['plan_id' => $planId]);
        return $stmt->fetchAll();
    }

    /**
     * Löscht alle Plan-Einträge für einen gegebenen Plan, ein Datum und einen Mitarbeiter.
     */
    public function deleteEntriesForDateAndEmployee(int $planId, string $date, int $employeeId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM plan_entry WHERE plan_id = :plan_id AND date = :date AND employee_id = :employee_id'
        );
        $stmt->execute([
            'plan_id' => $planId,
            'date' => $date,
            'employee_id' => $employeeId,
        ]);
    }
}

