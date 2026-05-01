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

    /**
     * Liefert die erlaubten Schichten mit optionalem max_per_week.
     *
     * @return list<array{shift_id: int, max_per_week: ?int}>
     */
    public function getAllowedShifts(int $employeeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT shift_id, max_per_week FROM employee_allowed_shift WHERE employee_id = :id ORDER BY shift_id'
        );
        $stmt->execute(['id' => $employeeId]);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[] = [
                'shift_id' => (int)$row['shift_id'],
                'max_per_week' => isset($row['max_per_week']) && $row['max_per_week'] !== null
                    ? (int)$row['max_per_week'] : null,
            ];
        }
        return $result;
    }

    /**
     * Speichert die erlaubten Schichten. Jedes Element von $shifts ist entweder eine shift_id (int)
     * oder ein Array mit 'shift_id' und optional 'max_per_week' (?int).
     *
     * @param array<int|array{shift_id: int, max_per_week?: ?int}> $shifts
     */
    public function setAllowedShifts(int $employeeId, array $shifts): void
    {
        $this->db->prepare('DELETE FROM employee_allowed_shift WHERE employee_id = :id')
            ->execute(['id' => $employeeId]);

        if (!$shifts) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO employee_allowed_shift (employee_id, shift_id, max_per_week) VALUES (:employee_id, :shift_id, :max_per_week)'
        );
        foreach ($shifts as $entry) {
            if (is_array($entry)) {
                $shiftId = (int)($entry['shift_id'] ?? 0);
                $maxPerWeek = array_key_exists('max_per_week', $entry) && $entry['max_per_week'] !== '' && $entry['max_per_week'] !== null
                    ? (int)$entry['max_per_week'] : null;
            } else {
                $shiftId = (int)$entry;
                $maxPerWeek = null;
            }
            $stmt->execute([
                'employee_id' => $employeeId,
                'shift_id' => $shiftId,
                'max_per_week' => $maxPerWeek,
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

    /**
     * Liefert die Namen aller Mitarbeiter, die an einem Wochentag für die gegebene Schicht und Rolle
     * grundsätzlich eingeteilt werden könnten (gemäß employee_allowed_weekday, employee_allowed_shift,
     * employee_allowed_weekday_shift und employee_role). Wochentag 0 = Montag, 6 = Sonntag.
     * Mitarbeiter mit Urlaub (holiday) am angegebenen Kalendertag werden ausgeschlossen.
     *
     * @param int $weekday Wochentag 0–6 (0 = Montag)
     * @param int $shiftId Schicht-ID
     * @param int $roleId Rollen-ID
     * @param string $onDate Kalendertag Y-m-d
     * @return list<string> Mitarbeiternamen, sortiert
     */
    public function getEligibleEmployeeNamesForShiftOnWeekday(int $weekday, int $shiftId, int $roleId, string $onDate): array
    {
        $sql = '
            SELECT e.name
            FROM employee e
            INNER JOIN employee_role er ON e.id = er.employee_id AND er.role_id = :role_id
            INNER JOIN employee_allowed_weekday eaw ON e.id = eaw.employee_id AND eaw.weekday = :weekday
            INNER JOIN employee_allowed_shift eas ON e.id = eas.employee_id AND eas.shift_id = :shift_id
            LEFT JOIN employee_allowed_weekday_shift eaws ON e.id = eaws.employee_id AND eaws.weekday = :weekday2
            WHERE (eaws.employee_id IS NULL OR eaws.shift_id = :shift_id2)
              AND NOT EXISTS (
                  SELECT 1 FROM holiday h
                  WHERE h.employee_id = e.id
                    AND h.date_from <= :on_date
                    AND h.date_to >= :on_date2
              )
            ORDER BY e.name
        ';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'role_id' => $roleId,
            'weekday' => $weekday,
            'shift_id' => $shiftId,
            'weekday2' => $weekday,
            'shift_id2' => $shiftId,
            'on_date' => $onDate,
            'on_date2' => $onDate,
        ]);
        return array_column($stmt->fetchAll(), 'name');
    }
}
