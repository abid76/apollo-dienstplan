<?php

namespace App\Repository;

use App\Core\Database;
use PDO;

class EmployeeRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM employee ORDER BY last_name, first_name');
        return $stmt->fetchAll();
    }

    /**
     * Wie findAll(), ergänzt um allowed_weekdays (Array von 0–6) pro Mitarbeiter.
     */
    public function findAllWithAllowedWeekdays(): array
    {
        $employees = $this->findAll();
        $stmt = $this->db->query('SELECT employee_id, weekday FROM employee_allowed_weekday ORDER BY employee_id, weekday');
        $rows = $stmt->fetchAll();
        $byEmployee = [];
        foreach ($rows as $row) {
            $id = (int)$row['employee_id'];
            $byEmployee[$id][] = (int)$row['weekday'];
        }
        foreach ($employees as &$emp) {
            $emp['allowed_weekdays'] = $byEmployee[(int)$emp['id']] ?? [];
        }
        unset($emp);
        return $employees;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM employee WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $firstName, string $lastName, int $maxShiftsPerWeek): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO employee (first_name, last_name, max_shifts_per_week) VALUES (:first_name, :last_name, :max)'
        );
        $stmt->execute([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'max' => $maxShiftsPerWeek,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $firstName, string $lastName, int $maxShiftsPerWeek): void
    {
        $stmt = $this->db->prepare(
            'UPDATE employee SET first_name = :first_name, last_name = :last_name, max_shifts_per_week = :max WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'max' => $maxShiftsPerWeek,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM employee WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function getAllowedWeekdays(int $employeeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT weekday FROM employee_allowed_weekday WHERE employee_id = :id ORDER BY weekday'
        );
        $stmt->execute(['id' => $employeeId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'weekday'));
    }

    public function setAllowedWeekdays(int $employeeId, array $weekdays): void
    {
        $this->db->prepare('DELETE FROM employee_allowed_weekday WHERE employee_id = :id')
            ->execute(['id' => $employeeId]);

        if (!$weekdays) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO employee_allowed_weekday (employee_id, weekday) VALUES (:employee_id, :weekday)'
        );
        foreach ($weekdays as $weekday) {
            $stmt->execute([
                'employee_id' => $employeeId,
                'weekday' => (int)$weekday,
            ]);
        }
    }

    public function getAllowedShifts(int $employeeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT shift_id FROM employee_allowed_shift WHERE employee_id = :id ORDER BY shift_id'
        );
        $stmt->execute(['id' => $employeeId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'shift_id'));
    }

    public function setAllowedShifts(int $employeeId, array $shiftIds): void
    {
        $this->db->prepare('DELETE FROM employee_allowed_shift WHERE employee_id = :id')
            ->execute(['id' => $employeeId]);

        if (!$shiftIds) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO employee_allowed_shift (employee_id, shift_id) VALUES (:employee_id, :shift_id)'
        );
        foreach ($shiftIds as $shiftId) {
            $stmt->execute([
                'employee_id' => $employeeId,
                'shift_id' => (int)$shiftId,
            ]);
        }
    }

    public function getRoles(int $employeeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT role_id FROM employee_role WHERE employee_id = :id ORDER BY role_id'
        );
        $stmt->execute(['id' => $employeeId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'role_id'));
    }

    public function setRoles(int $employeeId, array $roleIds): void
    {
        $this->db->prepare('DELETE FROM employee_role WHERE employee_id = :id')
            ->execute(['id' => $employeeId]);

        if (!$roleIds) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO employee_role (employee_id, role_id) VALUES (:employee_id, :role_id)'
        );
        foreach ($roleIds as $roleId) {
            $stmt->execute([
                'employee_id' => $employeeId,
                'role_id' => (int)$roleId,
            ]);
        }
    }
}

