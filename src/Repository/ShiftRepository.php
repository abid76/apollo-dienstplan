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
        $stmt = $this->db->query('SELECT * FROM shift ORDER BY time_from, name');
        $shifts = $stmt->fetchAll();

        if (!$shifts) {
            return [];
        }

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
            $shift['weekdays'] = $weekdaysByShift[$id] ?? [];
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

        $stmt = $this->db->prepare('SELECT weekday FROM shift_weekday WHERE shift_id = :id ORDER BY weekday');
        $stmt->execute(['id' => $id]);
        $row['weekdays'] = array_map('intval', array_column($stmt->fetchAll(), 'weekday'));

        return $row;
    }

    public function create(string $name, array $weekdays, string $timeFrom, string $timeTo): void
    {
        if (empty($weekdays)) {
            return;
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO shift (name, time_from, time_to) VALUES (:name, :time_from, :time_to)'
            );
            $stmt->execute([
                'name' => $name,
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

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'UPDATE shift SET name = :name, time_from = :time_from, time_to = :time_to WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'name' => $name,
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
        $stmt = $this->db->prepare('DELETE FROM shift_weekday WHERE shift_id = :shift_id');
        $stmt->execute(['shift_id' => $shiftId]);

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
