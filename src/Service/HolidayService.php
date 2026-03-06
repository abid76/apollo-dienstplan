<?php

namespace App\Service;

use App\Repository\HolidayRepository;
use App\Repository\EmployeeRepository;

class HolidayService
{
    private HolidayRepository $holidays;
    private EmployeeRepository $employees;

    public function __construct()
    {
        $this->holidays = new HolidayRepository();
        $this->employees = new EmployeeRepository();
    }

    public function list(): array
    {
        return $this->holidays->findAll();
    }

    public function get(int $id): ?array
    {
        return $this->holidays->find($id);
    }

    public function getFormData(?int $id = null): array
    {
        $holiday = null;
        if ($id !== null) {
            $holiday = $this->get($id);
        }

        return [
            'holiday' => $holiday,
            'employees' => $this->employees->findAll(),
        ];
    }

    public function create(array $data): array
    {
        $errors = $this->validate($data);
        if ($errors) {
            return $errors;
        }

        $employeeId = (int)$data['employee_id'];
        $dateFrom = trim($data['date_from']);
        $dateTo = trim($data['date_to']);

        $this->holidays->create($employeeId, $dateFrom, $dateTo);

        return [];
    }

    public function update(int $id, array $data): array
    {
        $errors = $this->validate($data);
        if ($errors) {
            return $errors;
        }

        $employeeId = (int)$data['employee_id'];
        $dateFrom = trim($data['date_from']);
        $dateTo = trim($data['date_to']);

        $this->holidays->update($id, $employeeId, $dateFrom, $dateTo);

        return [];
    }

    public function delete(int $id): void
    {
        $this->holidays->delete($id);
    }

    private function validate(array $data): array
    {
        $errors = [];

        $employeeId = isset($data['employee_id']) ? (int)$data['employee_id'] : 0;
        if ($employeeId <= 0) {
            $errors[] = 'Mitarbeiter ist erforderlich.';
        } elseif (!$this->employees->find($employeeId)) {
            $errors[] = 'Ausgewählter Mitarbeiter existiert nicht.';
        }

        $dateFromRaw = trim($data['date_from'] ?? '');
        $dateToRaw = trim($data['date_to'] ?? '');

        if ($dateFromRaw === '') {
            $errors[] = 'Von-Datum ist erforderlich.';
        }
        if ($dateToRaw === '') {
            $errors[] = 'Bis-Datum ist erforderlich.';
        }

        $dateFrom = null;
        $dateTo = null;

        if ($dateFromRaw !== '') {
            $dateFrom = \DateTime::createFromFormat('Y-m-d', $dateFromRaw) ?: null;
            if (!$dateFrom) {
                $errors[] = 'Von-Datum hat ein ungültiges Format.';
            }
        }

        if ($dateToRaw !== '') {
            $dateTo = \DateTime::createFromFormat('Y-m-d', $dateToRaw) ?: null;
            if (!$dateTo) {
                $errors[] = 'Bis-Datum hat ein ungültiges Format.';
            }
        }

        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            $errors[] = 'Von-Datum darf nicht nach dem Bis-Datum liegen.';
        }

        return $errors;
    }
}

