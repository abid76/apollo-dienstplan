<?php

namespace App\Repository;

use App\Core\Database;
use PDO;

class ShiftRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findAll(): array
    {
        // Alle Schichten laden
        $stmt = $this->db->query('SELECT * FROM shift ORDER BY weekday, time_from');
        $shifts = $stmt->fetchAll();

        if (!$shifts) {
            return [];
        }

        // Zugehörige Wochentage aus shift_weekday laden und nach shift_id gruppieren
        $stmt = $this->db->query('SELECT shift_id, weekday FROM shift_weekday ORDER BY weekday');
        $rows = $stmt->fetchAll();

        $weekdaysByShift = [];
        foreach ($rows as $row) {
            $shiftId = (int)$row['shift_id'];
            $weekday = (int)$row['weekday'];
            $weekdaysByShift[$shiftId][] = $weekday;
        }

        foreach ($shifts as &$shift) {
            $id = (int)$shift['id'];
            if (isset($weekdaysByShift[$id])) {
                $shift['weekdays'] = $weekdaysByShift[$id];
            } else {
                // Fallback: einzelner Wochentag aus der Haupttabelle
                $shift['weekdays'] = [isset($shift['weekday']) ? (int)$shift['weekday'] : 0];
            }
        }
        unset($shift);

        return $shifts;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM shift WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        // Zugehörige Wochentage laden
        $stmt = $this->db->prepare('SELECT weekday FROM shift_weekday WHERE shift_id = :id ORDER BY weekday');
        $stmt->execute(['id' => $id]);
        $weekdays = $stmt->fetchAll();

        if ($weekdays) {
            $row['weekdays'] = array_map('intval', array_column($weekdays, 'weekday'));
        } else {
            // Fallback: einzelner Wochentag aus der Haupttabelle
            $row['weekdays'] = [isset($row['weekday']) ? (int)$row['weekday'] : 0];
        }

        return $row;
    }

    public function create(string $name, array $weekdays, string $timeFrom, string $timeTo): void
    {
        if (empty($weekdays)) {
            return;
        }

        $primaryWeekday = min($weekdays);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO shift (name, weekday, time_from, time_to) VALUES (:name, :weekday, :time_from, :time_to)'
            );
            $stmt->execute([
                'name' => $name,
                'weekday' => $primaryWeekday,
                'time_from' => $timeFrom,
                'time_to' => $timeTo,
            ]);

            $shiftId = (int)$this->db->lastInsertId();
            $this->saveWeekdays($shiftId, $weekdays);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(int $id, string $name, array $weekdays, string $timeFrom, string $timeTo): void
    {
        if (empty($weekdays)) {
            return;
        }

        $primaryWeekday = min($weekdays);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'UPDATE shift SET name = :name, weekday = :weekday, time_from = :time_from, time_to = :time_to WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'name' => $name,
                'weekday' => $primaryWeekday,
                'time_from' => $timeFrom,
                'time_to' => $timeTo,
            ]);

            $this->saveWeekdays($id, $weekdays);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM shift WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Speichert die Wochentage für eine Schicht in der Tabelle shift_weekday.
     */
    private function saveWeekdays(int $shiftId, array $weekdays): void
    {
        // Bestehende Einträge löschen
        $stmt = $this->db->prepare('DELETE FROM shift_weekday WHERE shift_id = :shift_id');
        $stmt->execute(['shift_id' => $shiftId]);

        // Neue Einträge einfügen
        $stmt = $this->db->prepare(
            'INSERT INTO shift_weekday (shift_id, weekday) VALUES (:shift_id, :weekday)'
        );

        $uniqueWeekdays = array_unique(array_map('intval', $weekdays));
        foreach ($uniqueWeekdays as $weekday) {
            $stmt->execute([
                'shift_id' => $shiftId,
                'weekday' => $weekday,
            ]);
        }
    }
}

