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
        $stmt = $this->db->query('SELECT * FROM employee ORDER BY name');
        return $stmt->fetchAll();
    }

    /**
     * Wie findAll(), ergänzt um allowed_weekdays (Array von 0–6) und role_shortcodes (Array) pro Mitarbeiter.
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
        $roleShortcodes = $this->getRoleShortcodesByEmployee();
        foreach ($employees as &$emp) {
            $emp['allowed_weekdays'] = $byEmployee[(int)$emp['id']] ?? [];
            $emp['role_shortcodes'] = $roleShortcodes[(int)$emp['id']] ?? [];
        }
        unset($emp);
        return $employees;
    }

    /**
     * Liefert pro Mitarbeiter-ID ein Array von Rollen-Kürzeln (sortiert).
     * @return array<int, array<string>>
     */
    public function getRoleShortcodesByEmployee(): array
    {
        $stmt = $this->db->query(
            'SELECT er.employee_id, r.shortcode FROM employee_role er JOIN role r ON r.id = er.role_id ORDER BY er.employee_id, r.shortcode'
        );
        $byEmployee = [];
        foreach ($stmt->fetchAll() as $row) {
            $id = (int)$row['employee_id'];
            $byEmployee[$id][] = $row['shortcode'];
        }
        return $byEmployee;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM employee WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->db->prepare(
                'SELECT id FROM employee WHERE name = :name AND id <> :id LIMIT 1'
            );
            $stmt->execute([
                'name' => $name,
                'id' => $excludeId,
            ]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT id FROM employee WHERE name = :name LIMIT 1'
            );
            $stmt->execute([
                'name' => $name,
            ]);
        }

        return (bool)$stmt->fetchColumn();
    }

    public function create(string $name, int $maxShiftsPerWeek): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO employee (name, max_shifts_per_week) VALUES (:name, :max)'
        );
        $stmt->execute([
            'name' => $name,
            'max' => $maxShiftsPerWeek,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $name, int $maxShiftsPerWeek): void
    {
        $stmt = $this->db->prepare(
            'UPDATE employee SET name = :name, max_shifts_per_week = :max WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
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

    /**
     * Pro Wochentag die erlaubten Schicht-IDs (Einschränkung).
     * Rückgabe: [weekday => [shift_id, ...], ...]. Leeres Array für einen Tag = keine Einschränkung.
     */
    public function getAllowedWeekdayShifts(int $employeeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT weekday, shift_id FROM employee_allowed_weekday_shift WHERE employee_id = :id ORDER BY weekday, shift_id'
        );
        $stmt->execute(['id' => $employeeId]);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $wd = (int)$row['weekday'];
            $result[$wd][] = (int)$row['shift_id'];
        }
        return $result;
    }

    /**
     * @param array<int, array<int>> $weekdayShifts [weekday => [shift_id, ...], ...]. Nur Einträge mit nicht-leerer Liste werden gespeichert.
     */
    public function setAllowedWeekdayShifts(int $employeeId, array $weekdayShifts): void
    {
        $this->db->prepare('DELETE FROM employee_allowed_weekday_shift WHERE employee_id = :id')
            ->execute(['id' => $employeeId]);

        $stmt = $this->db->prepare(
            'INSERT INTO employee_allowed_weekday_shift (employee_id, weekday, shift_id) VALUES (:employee_id, :weekday, :shift_id)'
        );
        foreach ($weekdayShifts as $weekday => $shiftIds) {
            $shiftIds = array_filter(array_map('intval', (array)$shiftIds));
            foreach ($shiftIds as $shiftId) {
                $stmt->execute([
                    'employee_id' => $employeeId,
                    'weekday' => (int)$weekday,
                    'shift_id' => $shiftId,
                ]);
            }
        }
    }

    /**
     * Liefert alle Rollen-IDs für den angegebenen Mitarbeiter.
     *
     * @param int $employeeId ID des Mitarbeiters
     * @return int[] Array von Rollen-IDs (aufsteigend sortiert)
     */
    public function getRoles(int $employeeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT role_id FROM employee_role WHERE employee_id = :id ORDER BY role_id'
        );
        $stmt->execute(['id' => $employeeId]);
        $result = array_map('intval', array_column($stmt->fetchAll(), 'role_id'));
        return $result;
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
