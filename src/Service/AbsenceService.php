<?php

namespace App\Service;

use App\Repository\AbsenceRepository;
use App\Repository\EmployeeRepository;
use App\Repository\ShiftRepository;

class AbsenceService
{
    private AbsenceRepository $absences;
    private EmployeeRepository $employees;
    private ShiftRepository $shifts;

    public function __construct()
    {
        $this->absences = new AbsenceRepository();
        $this->employees = new EmployeeRepository();
        $this->shifts = new ShiftRepository();
    }

    public function list(): array
    {
        return $this->absences->findAll();
    }

    public function get(int $id): ?array
    {
        return $this->absences->find($id);
    }

    public function getFormData(?int $id = null): array
    {
        $absence = null;
        if ($id !== null) {
            $absence = $this->get($id);
        }

        return [
            'absence' => $absence,
            'employees' => $this->employees->findAll(),
            'shifts' => $this->shifts->findAll(),
        ];
    }

    public function create(array $data): array
    {
        $errors = $this->validate($data, null);
        if ($errors) {
            return $errors;
        }

        $employeeId = (int)$data['employee_id'];
        $date = trim($data['date']);
        $shiftId = $this->nullableInt($data['shift_id'] ?? null);

        $this->absences->create($employeeId, $date, $shiftId);

        return [];
    }

    public function update(int $id, array $data): array
    {
        $errors = $this->validate($data, $id);
        if ($errors) {
            return $errors;
        }

        $employeeId = (int)$data['employee_id'];
        $date = trim($data['date']);
        $shiftId = $this->nullableInt($data['shift_id'] ?? null);

        $this->absences->update($id, $employeeId, $date, $shiftId);

        return [];
    }

    public function delete(int $id): void
    {
        $this->absences->delete($id);
    }

    private function validate(array $data, ?int $currentId): array
    {
        $errors = [];

        $employeeId = isset($data['employee_id']) ? (int)$data['employee_id'] : 0;
        if ($employeeId <= 0) {
            $errors[] = 'Mitarbeiter ist erforderlich.';
        } elseif (!$this->employees->find($employeeId)) {
            $errors[] = 'Ausgewählter Mitarbeiter existiert nicht.';
        }

        $dateRaw = trim($data['date'] ?? '');
        if ($dateRaw === '') {
            $errors[] = 'Datum ist erforderlich.';
        } else {
            $date = \DateTime::createFromFormat('Y-m-d', $dateRaw) ?: null;
            if (!$date) {
                $errors[] = 'Datum hat ein ungültiges Format.';
            }
        }

        if (!$errors && $employeeId > 0 && $dateRaw !== '') {
            if ($this->absences->existsForEmployeeDate($employeeId, $dateRaw, $currentId)) {
                $errors[] = 'Für diesen Mitarbeiter existiert bereits eine Abwesenheit an diesem Datum.';
            }
        }

        $shiftId = $this->nullableInt($data['shift_id'] ?? null);
        if ($shiftId !== null && !$this->shifts->find($shiftId)) {
            $errors[] = 'Ausgewählte Schicht existiert nicht.';
        }

        return $errors;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        return (int)$value;
    }
}

