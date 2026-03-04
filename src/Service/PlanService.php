<?php

namespace App\Service;

use App\Repository\PlanRepository;
use App\Repository\ShiftRepository;
use App\Repository\RuleRepository;
use App\Repository\EmployeeRepository;

class PlanService
{
    private PlanRepository $plans;
    private ShiftRepository $shifts;
    private RuleRepository $rules;
    private EmployeeRepository $employees;

    public function __construct()
    {
        $this->plans = new PlanRepository();
        $this->shifts = new ShiftRepository();
        $this->rules = new RuleRepository();
        $this->employees = new EmployeeRepository();
    }

    /**
     * Alle Pläne für die Listenansicht.
     */
    public function listPlans(): array
    {
        return $this->plans->findAll();
    }

    public function generate(string $startDate, int $weeks): int
    {
        $planId = $this->plans->createPlan($startDate, $weeks);

        $employees = $this->loadEmployeesWithRelations();
        $shifts = $this->shifts->findAll();

        $start = new \DateTimeImmutable($startDate);
        $totalDays = $weeks * 7;

        $assignmentsPerWeek = [];
        $assignmentsPerEmployeeShiftPerWeek = [];
        $assignedPerDay = [];

        $completeShiftRoleAssignment = [];

        for ($weekIndex = 0; $weekIndex < $weeks; $weekIndex++) {

            $prevWeekOffset = 7 + $weekIndex * 7;
            $prevWeekStart = $start->modify("-{$prevWeekOffset} days")->format('Y-m-d');
            $prevPlan = $this->plans->findByStartDate($prevWeekStart);
            if ($prevPlan !== null) {
                $prevEntries = $this->plans->getEntriesWithDetails((int)$prevPlan['id']);
                foreach ($prevEntries as $entry) {
                    $assignedPerDay[$entry['date']][(int)$entry['employee_id']] = (int)$entry['role_id'];
                }
            }

            $remainingEmployeeShifts = [];
            foreach ($employees as $employee) {
                $remainingEmployeeShifts[(int)$employee['id']] = (int)$employee['max_shifts_per_week'];
            }
            for ($weekday = 0; $weekday < 7; $weekday++) {
                $dayIndex = $weekIndex * 7 + $weekday;
                $date = $start->modify("+{$dayIndex} day");
                $dateString = $date->format('Y-m-d');
                $actualWeekday = (int)$date->format('N') - 1; // 0 = Montag

                foreach ($shifts as $shift) {

                    $shiftId = (int)$shift['id'];
                    // Schicht gilt an diesem Tag, wenn der Wochentag in den Schicht-Wochentagen vorkommt
                    $shiftWeekdays = $shift['weekdays'] ?? [isset($shift['weekday']) ? (int)$shift['weekday'] : 0];
                    if (!in_array($actualWeekday, $shiftWeekdays, true)) {
                        continue;
                    }

                    $shiftRules = $this->rules->findByShift($shiftId);
                    if (!$shiftRules) {
                        continue;
                    }

                    foreach ($shiftRules as $rule) {
                        $roleId = (int)$rule['role_id'];
                        $requiredCount = (int)$rule['required_count'];

                        $candidates = [];
                        foreach ($employees as $employee) {
                            $employeeId = (int)$employee['id'];

                            if (
                                !in_array($actualWeekday, $employee['allowed_weekdays'], true) ||
                                !in_array($roleId, $employee['roles'], true)
                            ) {
                                continue;
                            }
                            $allowedShifts = $employee['allowed_shifts'] ?? [];
                            $allowedShiftIds = array_column($allowedShifts, 'shift_id');
                            if (!in_array($shiftId, $allowedShiftIds, true)) {
                                continue;
                            }
                            $maxThisShiftPerWeek = null;
                            foreach ($allowedShifts as $a) {
                                if ((int)$a['shift_id'] === $shiftId) {
                                    $maxThisShiftPerWeek = $a['max_per_week'] ?? null;
                                    break;
                                }
                            }
                            if ($maxThisShiftPerWeek !== null) {
                                $countThisShift = $assignmentsPerEmployeeShiftPerWeek[$employeeId][$shiftId][$weekIndex] ?? 0;
                                if ($countThisShift >= $maxThisShiftPerWeek) {
                                    continue;
                                }
                            }
                            $allowedShiftsToday = $employee['allowed_weekday_shifts'][$actualWeekday] ?? [];
                            if (!empty($allowedShiftsToday) && !in_array($shiftId, $allowedShiftsToday, true)) {
                                continue;
                            }
                            $currentWeekCount = $assignmentsPerWeek[$employeeId][$weekIndex] ?? 0;
                            if ($currentWeekCount >= (int)$employee['max_shifts_per_week']) {
                                continue;
                            }
                            if (!empty($assignedPerDay[$dateString][$employeeId])) {
                                continue;
                            }

                            $candidates[] = $employeeId;
                        }

                        if (!$candidates) {
                            continue;
                        }

                        // Mindestanzahl besetzen; Kandidaten mit wenigsten Einsätzen zuerst wählen,
                        // damit Kapazität (Anz. Schichten/Woche) genutzt wird
                        $randomOrder = range(0, max(array_column($employees, 'id')) ?: 0);
                        shuffle($randomOrder); // Zufälligkeit für den Fall, dass die Anzahl der Einsätze gleich ist
                        usort($candidates, function ($a, $b) use ($assignmentsPerWeek, $weekIndex, $randomOrder) {

                            $ca = $assignmentsPerWeek[$a][$weekIndex] ?? 0;
                            $cb = $assignmentsPerWeek[$b][$weekIndex] ?? 0;

                            if ($ca === $cb) {
                                return ($randomOrder[$a] <=> $randomOrder[$b]);
                            }

                            return $ca <=> $cb;
                        });
                        $selected = array_slice($candidates, 0, $requiredCount);

                        foreach ($selected as $employeeId) {
                            $assignmentsPerWeek[$employeeId][$weekIndex] =
                                ($assignmentsPerWeek[$employeeId][$weekIndex] ?? 0) + 1;
                            $assignmentsPerEmployeeShiftPerWeek[$employeeId][$shiftId][$weekIndex] =
                                ($assignmentsPerEmployeeShiftPerWeek[$employeeId][$shiftId][$weekIndex] ?? 0) + 1;
                            $assignedPerDay[$dateString][$employeeId] = $roleId;
                            $remainingEmployeeShifts[$employeeId]--;
                            if ($rule['required_count_exact'] === 1) {
                                $completeShiftRoleAssignment[$dateString][$shiftId][$roleId] = true;
                            }
                            $this->plans->addEntry(
                                $planId,
                                $dateString,
                                $shiftId,
                                $employeeId,
                                $roleId
                            );
                        }
                    }
                }
            }
        }

        // Nun stellen wir sicher, dass Montags so viele Mitarbeiter wie möglich besetzt sind
        for ($weekIndex = 0; $weekIndex < $weeks; $weekIndex++) {
            $dayIndex = $weekIndex * 7;
            $date = $start->modify("+{$dayIndex} day");
            $dateString = $date->format('Y-m-d');
            $actualWeekday = (int)$date->format('N') - 1; // 0 = Montag
            if ($actualWeekday !== 0) {
                continue;
            }
            
            foreach ($shifts as $shift) {
                $shiftId = (int)$shift['id'];

                // Schicht gilt an diesem Tag, wenn der Wochentag in den Schicht-Wochentagen vorkommt
                $shiftWeekdays = $shift['weekdays'] ?? [isset($shift['weekday']) ? (int)$shift['weekday'] : 0];
                if (!in_array($actualWeekday, $shiftWeekdays, true)) {
                    continue;
                }

                $shiftRules = $this->rules->findByShift($shiftId);
                if (!$shiftRules) {
                    continue;
                }

                foreach ($shiftRules as $rule) {
                    $roleId = (int)$rule['role_id'];
                    $requiredCount = (int)$rule['required_count'];
                    
                    foreach ($employees as $employee) {
                        $employeeId = (int)$employee['id'];

                        if (!in_array($actualWeekday, $employee['allowed_weekdays'], true) || !in_array($roleId, $employee['roles'], true)) {
                            continue;
                        }
                        if ($completeShiftRoleAssignment[$dateString][$shiftId][$roleId] ?? false) {
                            continue;
                        }
                        if (!empty($assignedPerDay[$dateString][$employeeId])) {
                            continue;
                        }
                        $allowedShifts = $employee['allowed_shifts'] ?? [];
                        $allowedShiftIds = array_column($allowedShifts, 'shift_id');
                        if (!in_array($shiftId, $allowedShiftIds, true)) {
                            continue;
                        }
                        $allowedShiftsToday = $employee['allowed_weekday_shifts'][$actualWeekday] ?? [];
                        if (!empty($allowedShiftsToday) && !in_array($shiftId, $allowedShiftsToday, true)) {
                            continue;
                        }
                        $currentWeekCount = $assignmentsPerWeek[$employeeId][$weekIndex] ?? 0;
                        if ($currentWeekCount >= (int)$employee['max_shifts_per_week']) {
                            continue;
                        }
                        $this->plans->addEntry(
                            $planId,
                            $dateString,
                            $shiftId,
                            $employeeId,
                            $roleId
                        );
                        $assignedPerDay[$dateString][$employeeId] = $roleId;
                        $assignmentsPerWeek[$employeeId][$weekIndex] =
                            ($assignmentsPerWeek[$employeeId][$weekIndex] ?? 0) + 1;
                        $assignmentsPerEmployeeShiftPerWeek[$employeeId][$shiftId][$weekIndex] =
                            ($assignmentsPerEmployeeShiftPerWeek[$employeeId][$shiftId][$weekIndex] ?? 0) + 1;
                        $remainingEmployeeShifts[$employeeId]--;
                        if ($rule['required_count_exact'] === 1) {
                            $completeShiftRoleAssignment[$dateString][$shiftId][$roleId] = true;
                        }
                    }
                }
            }
        }

        // Nun für alle Mitarbeiter sicherstellen, dass sie auf die Schichten verteilt sind
        for ($weekIndex = 0; $weekIndex < $weeks; $weekIndex++) {
            $remainingEmployeeShifts = [];
            foreach ($employees as $employee) {
                $remainingEmployeeShifts[(int)$employee['id']] =
                    (int)$employee['max_shifts_per_week'] - ($assignmentsPerWeek[$employee['id']][$weekIndex] ?? 0);
            }
            // Hier gilt folgende Logik
            // Wir iterieren über alle Wochentage und Schichten und fügen nach und nach jeweils ein Mitarbeiter hinzu
            // Dies tun wir solange, bis alle Mitarbeiter auf alle Schichten verteilt sind
            // Durch das tageweise hinzufügen eines einzelnen Mitarbeiters werden die Wochentage halbwegs gleichmäßig besetzt
            $maxIterations = 1000;
            $iterations = 0;
            while (max($remainingEmployeeShifts) > 0 && $iterations < $maxIterations) {
                $iterations++;
                if ($iterations > $maxIterations) {
                    throw new \Exception("Maximale Anzahl an Iterationen erreicht. Mitarbeiter nicht vollständig verteilt.");
                    break;
                }
                for ($weekday = 0; $weekday < 7; $weekday++) {
                    $dayIndex = $weekIndex * 7 + $weekday;
                    $date = $start->modify("+{$dayIndex} day");
                    $dateString = $date->format('Y-m-d');
                    $actualWeekday = (int)$date->format('N') - 1; // 0 = Montag

                    foreach ($shifts as $shift) {
                        $shiftId = (int)$shift['id'];
                        // Schicht gilt an diesem Tag, wenn der Wochentag in den Schicht-Wochentagen vorkommt
                        $shiftWeekdays = $shift['weekdays'] ?? [isset($shift['weekday']) ? (int)$shift['weekday'] : 0];
                        if (!in_array($actualWeekday, $shiftWeekdays, true)) {
                            continue;
                        }

                        $shiftRules = $this->rules->findByShift($shiftId);
                        if (!$shiftRules) {
                            continue;
                        }

                        foreach ($shiftRules as $rule) {
                            $roleId = (int)$rule['role_id'];
                            if ($completeShiftRoleAssignment[$dateString][$shiftId][$roleId] ?? false) {
                                continue;
                            }

                            $candidates = [];
                            foreach ($employees as $employee) {
                                $employeeId = (int)$employee['id'];

                                if (
                                    !in_array($actualWeekday, $employee['allowed_weekdays'], true) ||
                                    !in_array($roleId, $employee['roles'], true)
                                ) {
                                    continue;
                                }
                                $allowedShifts = $employee['allowed_shifts'] ?? [];
                                $allowedShiftIds = array_column($allowedShifts, 'shift_id');
                                if (!in_array($shiftId, $allowedShiftIds, true)) {
                                    continue;
                                }
                                $maxThisShiftPerWeek = null;
                                foreach ($allowedShifts as $a) {
                                    if ((int)$a['shift_id'] === $shiftId) {
                                        $maxThisShiftPerWeek = $a['max_per_week'] ?? null;
                                        break;
                                    }
                                }
                                if ($maxThisShiftPerWeek !== null) {
                                    $countThisShift = $assignmentsPerEmployeeShiftPerWeek[$employeeId][$shiftId][$weekIndex] ?? 0;
                                    if ($countThisShift >= $maxThisShiftPerWeek) {
                                        continue;
                                    }
                                }
                                $allowedShiftsToday = $employee['allowed_weekday_shifts'][$actualWeekday] ?? [];
                                if (!empty($allowedShiftsToday) && !in_array($shiftId, $allowedShiftsToday, true)) {
                                    continue;
                                }
                                $currentWeekCount = $assignmentsPerWeek[$employeeId][$weekIndex] ?? 0;
                                if ($currentWeekCount >= (int)$employee['max_shifts_per_week']) {
                                    continue;
                                }
                                if (!empty($assignedPerDay[$dateString][$employeeId])) {
                                    continue;
                                }

                                $candidates[] = $employeeId;
                            }

                            if (!$candidates) {
                                continue;
                            }

                            // Mindestanzahl besetzen; Kandidaten mit wenigsten Einsätzen zuerst wählen,
                            // damit Kapazität (Anz. Schichten/Woche) genutzt wird
                            $randomOrder = range(0, max(array_column($employees, 'id')) ?: 0);
                            shuffle($randomOrder); // Zufälligkeit für den Fall, dass die Anzahl der Einsätze gleich ist
                            usort($candidates, function ($a, $b) use ($assignmentsPerWeek, $weekIndex, $dateString, $assignedPerDay, $randomOrder) {
                                if ($weekIndex > 0) {
                                    // Falls der Mitarbeiter $a an diesem Tag der letzten Woche bereits hinzugefügt wurde,
                                    // Mitarbeiter $b aber nicht, gewinnt Mitarbeiter $b.
                                    $datePrev = (new \DateTimeImmutable($dateString))->modify('-7 days')->format('Y-m-d');
                                    $aAssignedLastWeek = !empty($assignedPerDay[$datePrev][$a]);
                                    $bAssignedLastWeek = !empty($assignedPerDay[$datePrev][$b]);
                                    if ($aAssignedLastWeek && !$bAssignedLastWeek) {
                                        return 1;
                                    }
                                    if (!$aAssignedLastWeek && $bAssignedLastWeek) {
                                        return -1;
                                    }
                                }

                                $ca = $assignmentsPerWeek[$a][$weekIndex] ?? 0;
                                $cb = $assignmentsPerWeek[$b][$weekIndex] ?? 0;
                                if ($ca === $cb) {
                                    return ($randomOrder[$a] <=> $randomOrder[$b]);
                                }
                                return $ca <=> $cb;
                            });
                            $selected = array_slice($candidates, 0, 1);

                            foreach ($selected as $employeeId) {
                                $assignmentsPerWeek[$employeeId][$weekIndex] =
                                    ($assignmentsPerWeek[$employeeId][$weekIndex] ?? 0) + 1;
                                $assignmentsPerEmployeeShiftPerWeek[$employeeId][$shiftId][$weekIndex] =
                                    ($assignmentsPerEmployeeShiftPerWeek[$employeeId][$shiftId][$weekIndex] ?? 0) + 1;
                                $assignedPerDay[$dateString][$employeeId] = $roleId;
                                $remainingEmployeeShifts[$employeeId]--;
                                $this->plans->addEntry(
                                    $planId,
                                    $dateString,
                                    $shiftId,
                                    $employeeId,
                                    $roleId
                                );
                            }
                        }
                    }
                }
            }
        }

        return $planId;
    }

    public function deletePlan(int $id): void
    {
        $this->plans->delete($id);
    }

    public function getPlanViewData(int $planId): ?array
    {
        $plan = $this->plans->getPlan($planId);
        if (!$plan) {
            return null;
        }

        $entries = $this->plans->getEntriesWithDetails($planId);

        // Alle Tage des Plans aus start_date und weeks ableiten (immer volle Wochen anzeigen)
        $start = new \DateTimeImmutable($plan['start_date']);
        $totalDays = (int)$plan['weeks'] * 7;
        $dateList = [];
        for ($i = 0; $i < $totalDays; $i++) {
            $dateList[] = $start->modify("+{$i} day")->format('Y-m-d');
        }

        $employees = [];
        $grid = [];

        foreach ($entries as $entry) {
            $employeeId = (int)$entry['employee_id'];
            $date = $entry['date'];

            $employees[$employeeId] = $employees[$employeeId]
                ?? [
                    'id' => $employeeId,
                    'name' => $entry['employee_name'],
                ];

            $grid[$employeeId][$date][] = [
                'shift_name' => $entry['shift_name'],
                'time_from' => $entry['time_from'],
                'time_to' => $entry['time_to'],
                'role_name' => $entry['role_name'],
                'shortcode' => $entry['shortcode'],
            ];
        }

        // Wochenweise Zählung der Schichten pro Mitarbeiter
        $employeeWeeklyShiftCounts = [];

        foreach ($entries as $entry) {
            $employeeId = (int)$entry['employee_id'];
            $entryDate = new \DateTimeImmutable($entry['date']);
            $diffDays = (int)$start->diff($entryDate)->days;
            $weekIndex = intdiv($diffDays, 7);

            if ($weekIndex < 0 || $weekIndex >= (int)$plan['weeks']) {
                continue;
            }

            $employeeWeeklyShiftCounts[$employeeId][$weekIndex] =
                ($employeeWeeklyShiftCounts[$employeeId][$weekIndex] ?? 0) + 1;
        }

        // Maximalwerte aus den Mitarbeiterdaten holen
        $allEmployees = $this->employees->findAll();
        $maxPerWeekByEmployee = [];
        $nameByEmployee = [];
        foreach ($allEmployees as $empRow) {
            $empId = (int)$empRow['id'];
            $nameByEmployee[$empId] = $empRow['name'] ?? '';
            if (isset($empRow['max_shifts_per_week'])) {
                $maxPerWeekByEmployee[$empId] = (int)$empRow['max_shifts_per_week'];
            }
        }

        $employeeUnderloadWarnings = [];
        $weeks = (int)$plan['weeks'];

        foreach ($employees as $employee) {
            $employeeId = (int)$employee['id'];
            $maxPerWeek = $maxPerWeekByEmployee[$employeeId] ?? null;
            if ($maxPerWeek === null || $maxPerWeek <= 0) {
                continue;
            }

            for ($weekIndex = 0; $weekIndex < $weeks; $weekIndex++) {
                $actual = $employeeWeeklyShiftCounts[$employeeId][$weekIndex] ?? 0;
                if ($actual < $maxPerWeek) {
                    $employeeUnderloadWarnings[] = [
                        'employee_id' => $employeeId,
                        'employee_name' => $employee['name'] ?? ($nameByEmployee[$employeeId] ?? ''),
                        'week' => $weekIndex + 1,
                        'actual' => $actual,
                        'max' => $maxPerWeek,
                    ];
                }
            }
        }

        usort(
            $employees,
            fn($a, $b) => ($a['name'] ?? '') <=> ($b['name'] ?? '')
        );

        return [
            'plan' => $plan,
            'employees' => $employees,
            'dates' => $dateList,
            'grid' => $grid,
            'employeeWeeklyShiftCounts' => $employeeWeeklyShiftCounts,
            'employeeUnderloadWarnings' => $employeeUnderloadWarnings,
        ];
    }

    private function loadEmployeesWithRelations(): array
    {
        $all = $this->employees->findAll();
        $result = [];
        foreach ($all as $employee) {
            $id = (int)$employee['id'];
            $employee['allowed_weekdays'] = $this->employees->getAllowedWeekdays($id);
            $employee['allowed_shifts'] = $this->employees->getAllowedShifts($id);
            $employee['allowed_weekday_shifts'] = $this->employees->getAllowedWeekdayShifts($id);
            $employee['roles'] = $this->employees->getRoles($id);
            $result[] = $employee;
        }
        return $result;
    }
}
