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
        $errors = $this->validate($data, null);
        if ($errors) {
            return $errors;
        }

        $employeeId = $this->employees->create(
            trim($data['name']),
            (int)$data['max_shifts_per_week']
        );

        $this->updateRelations($employeeId, $data);

        return [];
    }

    public function update(int $id, array $data): array
    {
        $errors = $this->validate($data, $id);
        if ($errors) {
            return $errors;
        }

        $this->employees->update(
            $id,
            trim($data['name']),
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
        $rawShifts = isset($data['allowed_shifts']) ? (array)$data['allowed_shifts'] : [];
        $roles = isset($data['roles']) ? (array)$data['roles'] : [];

        $shifts = [];
        $maxPerWeekByShift = $data['allowed_shift_max_per_week'] ?? [];
        foreach ($rawShifts as $shiftId) {
            $shiftId = (int)$shiftId;
            $maxPerWeek = isset($maxPerWeekByShift[$shiftId]) && $maxPerWeekByShift[$shiftId] !== ''
                ? (int)$maxPerWeekByShift[$shiftId] : null;
            $shifts[] = ['shift_id' => $shiftId, 'max_per_week' => $maxPerWeek];
        }

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

    private function validate(array $data, ?int $currentId = null): array
    {
        $errors = [];
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Name ist erforderlich.';
        } elseif ($this->employees->nameExists($name, $currentId)) {
            $errors[] = 'Ein Mitarbeiter mit diesem Namen existiert bereits.';
        }
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
        $maxPerWeek = $data['allowed_shift_max_per_week'] ?? [];
        if (is_array($maxPerWeek)) {
            foreach ($maxPerWeek as $sid => $val) {
                if ($val !== '' && $val !== null && (!is_numeric($val) || (int)$val < 0)) {
                    $errors[] = 'Max. Schichten pro Woche muss eine nichtnegative Zahl sein.';
                    break;
                }
            }
        }

        $roles = $data['roles'] ?? [];
        if (empty($roles) || !is_array($roles)) {
            $errors[] = 'Mindestens eine Rolle muss ausgewählt werden.';
        }

        return $errors;
    }
}

