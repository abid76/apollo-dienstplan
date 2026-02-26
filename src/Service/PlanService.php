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
        $assignedPerDay = [];

        for ($dayIndex = 0; $dayIndex < $totalDays; $dayIndex++) {
            $date = $start->modify("+{$dayIndex} day");
            $dateString = $date->format('Y-m-d');
            $weekday = (int)$date->format('N') - 1; // 0 = Montag
            $weekIndex = intdiv($dayIndex, 7);

            foreach ($shifts as $shift) {
                // Schicht gilt an diesem Tag, wenn der Wochentag in den Schicht-Wochentagen vorkommt
                $shiftWeekdays = $shift['weekdays'] ?? [isset($shift['weekday']) ? (int)$shift['weekday'] : 0];
                if (!in_array($weekday, $shiftWeekdays, true)) {
                    continue;
                }

                $shiftRules = $this->rules->findByShift((int)$shift['id']);
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
                            !in_array($weekday, $employee['allowed_weekdays'], true) ||
                            !in_array((int)$shift['id'], $employee['allowed_shifts'], true) ||
                            !in_array($roleId, $employee['roles'], true)
                        ) {
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
                    usort($candidates, function ($a, $b) use ($assignmentsPerWeek, $weekIndex) {
                        $ca = $assignmentsPerWeek[$a][$weekIndex] ?? 0;
                        $cb = $assignmentsPerWeek[$b][$weekIndex] ?? 0;
                        return $ca <=> $cb;
                    });
                    $selected = array_slice($candidates, 0, $requiredCount);

                    foreach ($selected as $employeeId) {
                        $assignmentsPerWeek[$employeeId][$weekIndex] =
                            ($assignmentsPerWeek[$employeeId][$weekIndex] ?? 0) + 1;
                        $assignedPerDay[$dateString][$employeeId] = true;

                        $this->plans->addEntry(
                            $planId,
                            $dateString,
                            (int)$shift['id'],
                            $employeeId,
                            $roleId
                        );
                    }
                }
            }
        }

        // Nun für alle Mitarbeiter sicherstellen, dass sie in den Schichten gemäß ihrer Anzahl der Schichten besetzt werden
        for ($dayIndex = 0; $dayIndex < $totalDays; $dayIndex++) {
            $date = $start->modify("+{$dayIndex} day");
            $dateString = $date->format('Y-m-d');
            $weekday = (int)$date->format('N') - 1; // 0 = Montag
            $weekIndex = intdiv($dayIndex, 7);

            foreach ($employees as $employee) {
                $employeeId = (int)$employee['id'];
                // Zulässige Schichten auswählen
                $shifts = $employee['allowed_shifts'];
                foreach ($shifts as $shiftId) {

                    // Wenn der Mitarbeiter bereits an diesem Tag besetzt ist, überspringen
                    if (!empty($assignedPerDay[$dateString][$employeeId])) {
                        continue;
                    }
                    // Wenn der Mitarbeiter bereits die maximale Anzahl an Schichten pro Woche erreicht hat, überspringen
                    $currentWeekCount = $assignmentsPerWeek[$employeeId][$weekIndex] ?? 0;
                    if ($currentWeekCount >= (int)$employee['max_shifts_per_week']) {
                        continue;
                    }

                    // Passende Regel auswählen
                    $shiftRules = $this->rules->findByShift((int)$shiftId);
                    // Regeln auswählen, die nicht in der ersten Schleife abschließend behandelt wurden
                    $shiftRules = array_filter($shiftRules, function ($rule) {
                        return $rule['required_count_exact'] === 0;
                    });
                    if (empty($shiftRules)) {
                        continue;
                    } else {
                        // Ansonsten passende Rolle ermitteln
                        $intersection_roles = array_intersect(array_column($shiftRules, 'role_id'), $employee['roles']);
                        if (empty($intersection_roles)) {
                            continue;
                        } else {
                            // Rolle zufällig auswählen
                            $roleId = $intersection_roles[array_rand($intersection_roles)];
                        }
                    }
                    $assignmentsPerWeek[$employeeId][$weekIndex] =
                        ($assignmentsPerWeek[$employeeId][$weekIndex] ?? 0) + 1;
                    $assignedPerDay[$dateString][$employeeId] = true;
                    $this->plans->addEntry(
                        $planId,
                        $dateString,
                        (int)$shiftId,
                        $employeeId,
                        $roleId
                    );
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
                    'first_name' => $entry['first_name'],
                    'last_name' => $entry['last_name'],
                ];

            $grid[$employeeId][$date][] = [
                'shift_name' => $entry['shift_name'],
                'time_from' => $entry['time_from'],
                'time_to' => $entry['time_to'],
                'role_name' => $entry['role_name'],
                'shortcode' => $entry['shortcode'],
            ];
        }

        usort(
            $employees,
            fn($a, $b) => [$a['last_name'], $a['first_name']] <=> [$b['last_name'], $b['first_name']]
        );

        return [
            'plan' => $plan,
            'employees' => $employees,
            'dates' => $dateList,
            'grid' => $grid,
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
            $employee['roles'] = $this->employees->getRoles($id);
            $result[] = $employee;
        }
        return $result;
    }
}
