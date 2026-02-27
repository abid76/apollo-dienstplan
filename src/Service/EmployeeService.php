<?php

namespace App\Service;

use App\Repository\EmployeeRepository;
use App\Repository\ShiftRepository;
use App\Repository\RoleRepository;

class EmployeeService
{
    private EmployeeRepository $employees;
    private ShiftRepository $shifts;
    private RoleRepository $roles;

    public function __construct()
    {
        $this->employees = new EmployeeRepository();
        $this->shifts = new ShiftRepository();
        $this->roles = new RoleRepository();
    }

    public function list(): array
    {
        return $this->employees->findAllWithAllowedWeekdays();
    }

    public function get(int $id): ?array
    {
        $employee = $this->employees->find($id);
        if (!$employee) {
            return null;
        }

        $employee['allowed_weekdays'] = $this->employees->getAllowedWeekdays($id);
        $employee['allowed_shifts'] = $this->employees->getAllowedShifts($id);
        $employee['allowed_weekday_shifts'] = $this->employees->getAllowedWeekdayShifts($id);
        $employee['roles'] = $this->employees->getRoles($id);

        return $employee;
    }

    public function getFormData(?int $id = null): array
    {
        $employee = null;
        if ($id !== null) {
            $employee = $this->get($id);
        }

        return [
            'employee' => $employee,
            'shifts' => $this->shifts->findAll(),
            'roles' => $this->roles->findAll(),
        ];
    }

    public function create(array $data): array
    {
        $errors = $this->validate($data);
        if ($errors) {
            return $errors;
        }

        $employeeId = $this->employees->create(
            trim($data['first_name']),
            trim($data['last_name']),
            (int)$data['max_shifts_per_week']
        );

        $this->updateRelations($employeeId, $data);

        return [];
    }

    public function update(int $id, array $data): array
    {
        $errors = $this->validate($data);
        if ($errors) {
            return $errors;
        }

        $this->employees->update(
            $id,
            trim($data['first_name']),
            trim($data['last_name']),
            (int)$data['max_shifts_per_week']
        );

        $this->updateRelations($id, $data);

        return [];
    }

    public function delete(int $id): void
    {
        $this->employees->delete($id);
    }

    private function updateRelations(int $employeeId, array $data): void
    {
        $weekdays = isset($data['allowed_weekdays']) ? (array)$data['allowed_weekdays'] : [];
        $shifts = isset($data['allowed_shifts']) ? (array)$data['allowed_shifts'] : [];
        $roles = isset($data['roles']) ? (array)$data['roles'] : [];

        $this->employees->setAllowedWeekdays($employeeId, $weekdays);
        $this->employees->setAllowedShifts($employeeId, $shifts);

        $weekdayShifts = [];
        if (isset($data['allowed_weekday_shift']) && is_array($data['allowed_weekday_shift'])) {
            foreach ($data['allowed_weekday_shift'] as $weekday => $shiftIds) {
                $shiftIds = array_filter((array)$shiftIds);
                if (!empty($shiftIds)) {
                    $weekdayShifts[(int)$weekday] = array_map('intval', $shiftIds);
                }
            }
        }
        $this->employees->setAllowedWeekdayShifts($employeeId, $weekdayShifts);

        $this->employees->setRoles($employeeId, $roles);
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (empty(trim($data['first_name'] ?? ''))) {
            $errors[] = 'Vorname ist erforderlich.';
        }
        // Nachname ist optional
        if (!isset($data['max_shifts_per_week']) || $data['max_shifts_per_week'] === '') {
            $errors[] = 'Anzahl Schichten pro Woche ist erforderlich.';
        } elseif (!is_numeric($data['max_shifts_per_week']) || (int)$data['max_shifts_per_week'] < 0) {
            $errors[] = 'Anzahl Schichten pro Woche muss eine nichtnegative Zahl sein.';
        }

        $weekdays = $data['allowed_weekdays'] ?? [];
        if (empty($weekdays) || !is_array($weekdays)) {
            $errors[] = 'Mindestens ein Wochentag muss ausgewählt werden.';
        }

        $shifts = $data['allowed_shifts'] ?? [];
        if (empty($shifts) || !is_array($shifts)) {
            $errors[] = 'Mindestens eine Schicht muss ausgewählt werden.';
        }

        $roles = $data['roles'] ?? [];
        if (empty($roles) || !is_array($roles)) {
            $errors[] = 'Mindestens eine Rolle muss ausgewählt werden.';
        }

        return $errors;
    }
}

