<?php

namespace App\Service;

use App\Repository\ShiftRepository;

class ShiftService
{
    private ShiftRepository $repository;

    public function __construct()
    {
        $this->repository = new ShiftRepository();
    }

    public function list(): array
    {
        return $this->repository->findAll();
    }

    public function get(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function create(array $data): array
    {
        $errors = $this->validate($data);
        if ($errors) {
            return $errors;
        }

        $this->repository->create(
            trim($data['name']),
            $this->extractWeekdays($data),
            $this->normalizeHourToTime($data['time_from']),
            $this->normalizeHourToTime($data['time_to'])
        );

        return [];
    }

    public function update(int $id, array $data): array
    {
        $errors = $this->validate($data);
        if ($errors) {
            return $errors;
        }

        $this->repository->update(
            $id,
            trim($data['name']),
            $this->extractWeekdays($data),
            $this->normalizeHourToTime($data['time_from']),
            $this->normalizeHourToTime($data['time_to'])
        );

        return [];
    }

    public function delete(int $id): void
    {
        $this->repository->delete($id);
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (empty(trim($data['name'] ?? ''))) {
            $errors[] = 'Name ist erforderlich.';
        }
        // Mindestens ein Wochentag muss ausgewählt sein
        $weekdays = $data['weekdays'] ?? null;
        if (empty($weekdays) || !is_array($weekdays)) {
            $errors[] = 'Mindestens ein Wochentag ist erforderlich.';
        }
        // Stundenfelder (0–23) validieren
        $timeFrom = $data['time_from'] ?? '';
        $timeTo = $data['time_to'] ?? '';

        if ($timeFrom === '' || $timeFrom === null) {
            $errors[] = 'Uhrzeit von ist erforderlich.';
        } elseif (filter_var($timeFrom, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 23],
        ]) === false) {
            $errors[] = 'Uhrzeit von muss eine ganze Stunde zwischen 0 und 23 sein.';
        }

        if ($timeTo === '' || $timeTo === null) {
            $errors[] = 'Uhrzeit bis ist erforderlich.';
        } elseif (filter_var($timeTo, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 23],
        ]) === false) {
            $errors[] = 'Uhrzeit bis muss eine ganze Stunde zwischen 0 und 23 sein.';
        }

        if ($timeFrom !== '' && $timeTo !== '' && is_numeric($timeFrom) && is_numeric($timeTo)) {
            if ((int)$timeFrom >= (int)$timeTo) {
                $errors[] = 'Uhrzeit von muss vor Uhrzeit bis liegen.';
            }
        }
        return $errors;
    }

    /**
     * Wandelt eine Stundenangabe (0–23) in ein TIME-Format HH:00:00 um.
     */
    private function normalizeHourToTime($hour): string
    {
        $hourInt = (int)$hour;
        return sprintf('%02d:00:00', $hourInt);
    }

    /**
     * Extrahiert und normalisiert die ausgewählten Wochentage aus den Formulardaten.
     *
     * @return int[]
     */
    private function extractWeekdays(array $data): array
    {
        $weekdays = $data['weekdays'] ?? [];
        if (!is_array($weekdays)) {
            $weekdays = [$weekdays];
        }

        $weekdays = array_map('intval', $weekdays);
        // Nur gültige Wochentage 0–6 zulassen
        $weekdays = array_values(array_filter($weekdays, static function (int $day): bool {
            return $day >= 0 && $day <= 6;
        }));

        return $weekdays;
    }
}

