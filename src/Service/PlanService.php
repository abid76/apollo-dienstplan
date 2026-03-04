<?php

namespace App\Service;

use App\Repository\PlanRepository;
use App\Repository\ShiftRepository;
use App\Repository\RuleRepository;
use App\Repository\EmployeeRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

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

                            if (!$this->isEmployeeAllowedForDayShiftAndRole(
                                $employee,
                                $shiftId,
                                $roleId,
                                $dateString,
                                $weekIndex,
                                $assignmentsPerEmployeeShiftPerWeek,
                                $assignmentsPerWeek,
                                $assignedPerDay
                            )) {
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

                        if (!$this->isEmployeeAllowedForDayShiftAndRole(
                            $employee,
                            $shiftId,
                            $roleId,
                            $dateString,
                            $weekIndex,
                            $assignmentsPerEmployeeShiftPerWeek,
                            $assignmentsPerWeek,
                            $assignedPerDay
                        )) {
                            continue;
                        }
                        $employeeId = (int)$employee['id'];
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

                                if (!$this->isEmployeeAllowedForDayShiftAndRole(
                                    $employee,
                                    $shiftId,
                                    $roleId,
                                    $dateString,
                                    $weekIndex,
                                    $assignmentsPerEmployeeShiftPerWeek,
                                    $assignmentsPerWeek,
                                    $assignedPerDay
                                )) {
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

    public function createSpreadsheetForPlan(int $planId): ?Spreadsheet
    {
        $data = $this->getPlanViewData($planId);
        if (!$data) {
            return null;
        }

        $plan = $data['plan'];
        $employees = $data['employees'];
        $dates = $data['dates'];
        $grid = $data['grid'];

        // Aggregationen wie in der HTML-Ansicht (Footer "Rollen" / "Schichten")
        $roleCountsByDate = [];
        foreach ($dates as $date) {
            $counts = [];
            foreach ($employees as $employee) {
                $entries = $grid[$employee['id']][$date] ?? [];
                foreach ($entries as $entry) {
                    $sc = $entry['shortcode'] ?? '';
                    if ($sc !== '') {
                        $counts[$sc] = ($counts[$sc] ?? 0) + 1;
                    }
                }
            }
            $roleCountsByDate[$date] = $counts;
        }

        $shiftsByDate = [];
        foreach ($dates as $date) {
            $byShift = [];
            foreach ($employees as $employee) {
                $entries = $grid[$employee['id']][$date] ?? [];
                foreach ($entries as $entry) {
                    $from = $entry['time_from'] ?? '';
                    $to = $entry['time_to'] ?? '';
                    $key = ($entry['shift_name'] ?? '') . '|' . $from . '|' . $to;
                    if ($key !== '||') {
                        if (!isset($byShift[$key])) {
                            $byShift[$key] = [
                                'time_range' => $this->formatTimeRangeDisplay($from, $to),
                                'count' => 0,
                                'time_from' => $from,
                            ];
                        }
                        $byShift[$key]['count']++;
                    }
                }
            }
            $shiftsByDate[$date] = $byShift;
        }

        $weeks = (int)$plan['weeks'];
        if ($weeks < 1) {
            return null;
        }

        $spreadsheet = new Spreadsheet();

        for ($weekIndex = 0; $weekIndex < $weeks; $weekIndex++) {
            $sheet = $weekIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle('Woche ' . ($weekIndex + 1));

            // Kopfzeile: Zeile 1 = Wochentag/Datum (über 2 Spalten), Zeile 2 = Unterüberschriften
            // "Mitarbeiter" steht in Zeile 2 der ersten Spalte
            $sheet->setCellValue('A2', 'Mitarbeiter');

            for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
                $dateIndex = $weekIndex * 7 + $dayOffset;
                if (!isset($dates[$dateIndex])) {
                    continue;
                }
                $dateString = $dates[$dateIndex];
                $dt = new \DateTimeImmutable($dateString);
                $weekdayNames = [
                    1 => 'Mo',
                    2 => 'Di',
                    3 => 'Mi',
                    4 => 'Do',
                    5 => 'Fr',
                    6 => 'Sa',
                    7 => 'So',
                ];
                $weekday = (int)$dt->format('N');
                $label = ($weekdayNames[$weekday] ?? '') . ' ' . $dt->format('d.m.');

                $colTimeIndex = 2 + $dayOffset * 2;
                $colRoleIndex = $colTimeIndex + 1;
                $colTime = Coordinate::stringFromColumnIndex($colTimeIndex);
                $colRole = Coordinate::stringFromColumnIndex($colRoleIndex);

                // Zeile 1: Tages-Header über beide Spalten
                $sheet->mergeCells($colTime . '1:' . $colRole . '1');
                $sheet->setCellValue($colTime . '1', $label);

                // Zeile 2: Unterüberschriften
                $sheet->setCellValue($colTime . '2', 'Arbeitszeit');
                $sheet->setCellValue($colRole . '2', 'Rolle');
            }

            $lastCol = Coordinate::stringFromColumnIndex(1 + 7 * 2);
            // Überschriftenzeilen 1–2 fett und zentriert
            $headerRange = 'A1:' . $lastCol . '2';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            // Daten beginnen in Zeile 3
            $row = 3;
            foreach ($employees as $employee) {
                $employeeId = (int)$employee['id'];
                $sheet->setCellValue('A' . $row, $employee['name'] ?? '');

                for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
                    $dateIndex = $weekIndex * 7 + $dayOffset;
                    if (!isset($dates[$dateIndex])) {
                        continue;
                    }
                    $dateString = $dates[$dateIndex];

                    $entries = $grid[$employeeId][$dateString] ?? [];
                    $times = [];
                    $roles = [];
                    foreach ($entries as $entry) {
                        $from = $entry['time_from'] ?? '';
                        $to = $entry['time_to'] ?? '';
                        if ($from !== '' || $to !== '') {
                            $times[] = $this->formatTimeRangeDisplay($from, $to);
                        }
                        if (!empty($entry['shortcode'])) {
                            $roles[] = $entry['shortcode'];
                        }
                    }

                    $colTimeIndex = 2 + $dayOffset * 2;
                    $colRoleIndex = $colTimeIndex + 1;
                    $colTime = Coordinate::stringFromColumnIndex($colTimeIndex);
                    $colRole = Coordinate::stringFromColumnIndex($colRoleIndex);

                    if ($times) {
                        $sheet->setCellValue($colTime . $row, implode(', ', $times));
                    }
                    if ($roles) {
                        $sheet->setCellValue($colRole . $row, implode(', ', $roles));
                    }
                }

                $row++;
            }

            // Footer-Zeile "Rollen" / "Schichten" ergänzen
            $footerRolesRow = $row;
            $footerShiftsRow = $row + 1;

            $sheet->setCellValue('A' . $footerRolesRow, 'Rollen');
            $sheet->setCellValue('A' . $footerShiftsRow, 'Schichten');
            // Footer-Bezeichner wie Überschrift formatieren
            $sheet->getStyle('A' . $footerRolesRow)->getFont()->setBold(true);
            $sheet->getStyle('A' . $footerShiftsRow)->getFont()->setBold(true);

            for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
                $dateIndex = $weekIndex * 7 + $dayOffset;
                if (!isset($dates[$dateIndex])) {
                    continue;
                }
                $dateString = $dates[$dateIndex];

                $colTimeIndex = 2 + $dayOffset * 2;
                $colRoleIndex = $colTimeIndex + 1;
                $colTime = Coordinate::stringFromColumnIndex($colTimeIndex);
                $colRole = Coordinate::stringFromColumnIndex($colRoleIndex);

                // Rollen pro Datum
                $roleCounts = $roleCountsByDate[$dateString] ?? [];
                $roleParts = [];
                foreach ($roleCounts as $shortcode => $n) {
                    $roleParts[] = $shortcode . ': ' . $n;
                }
                if ($roleParts) {
                    $range = $colTime . $footerRolesRow . ':' . $colRole . $footerRolesRow;
                    $sheet->mergeCells($range);
                    $sheet->setCellValue($colTime . $footerRolesRow, implode(', ', $roleParts));
                }

                // Schichten pro Datum
                $shiftInfos = $shiftsByDate[$dateString] ?? [];
                // nach Startzeit sortieren (wie in der HTML-Ansicht)
                uasort($shiftInfos, static function (array $a, array $b): int {
                    return ($a['time_from'] ?? '') <=> ($b['time_from'] ?? '');
                });
                $shiftParts = [];
                foreach ($shiftInfos as $info) {
                    $shiftParts[] = ($info['time_range'] ?? '') . ': ' . $info['count'];
                }
                if ($shiftParts) {
                    $range = $colTime . $footerShiftsRow . ':' . $colRole . $footerShiftsRow;
                    $sheet->mergeCells($range);
                    $sheet->setCellValue($colTime . $footerShiftsRow, implode("\n", $shiftParts));
                    $sheet->getStyle($colTime . $footerShiftsRow)->getAlignment()->setWrapText(true);
                }
            }

            // Spaltenbreite automatisch an Inhalt anpassen
            for ($colIndex = 1; $colIndex <= 1 + 7 * 2; $colIndex++) {
                $col = Coordinate::stringFromColumnIndex($colIndex);
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /** Uhrzeitbereich im Anzeigeformat z. B. "9-17 Uhr", "13-20 Uhr" */
    private function formatTimeRangeDisplay(string $timeFrom, string $timeTo): string
    {
        $from = substr($timeFrom, 0, 5);
        $to = substr($timeTo, 0, 5);
        if (substr($from, -3) === ':00') {
            $from = (int) substr($from, 0, 2);
        }
        if (substr($to, -3) === ':00') {
            $to = (int) substr($to, 0, 2);
        }
        return $from . '-' . $to . ' Uhr';
    }

    private function isEmployeeAllowedForDayShiftAndRole(
        array $employee,
        int $shiftId,
        int $roleId,
        string $dateString,
        int $weekIndex,
        array $assignmentsPerEmployeeShiftPerWeek,
        array $assignmentsPerWeek,
        array $assignedPerDay
    ): bool {
        $employeeId = (int)$employee['id'];
        $actualWeekday = (int)(new \DateTimeImmutable($dateString))->format('N') - 1;
        if (
            !in_array($actualWeekday, $employee['allowed_weekdays'], true) ||
            !in_array($roleId, $employee['roles'], true)
        ) {
            return false;
        }

        $allowedShifts = $employee['allowed_shifts'] ?? [];
        $allowedShiftIds = array_column($allowedShifts, 'shift_id');
        if (!in_array($shiftId, $allowedShiftIds, true)) {
            return false;
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
                return false;
            }
        }

        $allowedShiftsToday = $employee['allowed_weekday_shifts'][$actualWeekday] ?? [];
        if (!empty($allowedShiftsToday) && !in_array($shiftId, $allowedShiftsToday, true)) {
            return false;
        }

        $currentWeekCount = $assignmentsPerWeek[$employeeId][$weekIndex] ?? 0;
        if ($currentWeekCount >= (int)$employee['max_shifts_per_week']) {
            return false;
        }

        if (!empty($assignedPerDay[$dateString][$employeeId])) {
            return false;
        }

        return true;
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

        // Belegungsregeln pro Tag/Schicht prüfen
        $ruleRows = $this->rules->findAllWithDetails();

        // Zuordnungen nach Datum/Schicht/Rolle zählen
        $assignmentsByDateShiftRole = [];
        foreach ($entries as $entry) {
            $date = $entry['date'];
            $shiftId = (int)$entry['shift_id'];
            $roleId = (int)$entry['role_id'];
            $assignmentsByDateShiftRole[$date][$shiftId][$roleId] =
                ($assignmentsByDateShiftRole[$date][$shiftId][$roleId] ?? 0) + 1;
        }

        $coverageWarnings = [];
        foreach ($dateList as $date) {
            $dt = new \DateTimeImmutable($date);
            $weekdayZeroBased = (int)$dt->format('N') - 1; // 0 = Montag

            foreach ($ruleRows as $rule) {
                $shiftId = (int)$rule['shift_id'];
                $roleId = (int)$rule['role_id'];
                $requiredCount = (int)$rule['required_count'];

                // Regel gilt nur an den in der Schicht definierten Wochentagen
                $shiftWeekdays = $rule['shift_weekdays'] ?? [isset($rule['weekday']) ? (int)$rule['weekday'] : 0];
                if (!in_array($weekdayZeroBased, $shiftWeekdays, true)) {
                    continue;
                }

                $actualCount = (int)($assignmentsByDateShiftRole[$date][$shiftId][$roleId] ?? 0);
                if ($actualCount < $requiredCount) {
                    $coverageWarnings[] = [
                        'date' => $date,
                        'shift_name' => $rule['shift_name'] ?? '',
                        'time_from' => $rule['time_from'] ?? '',
                        'time_to' => $rule['time_to'] ?? '',
                        'role_name' => $rule['role_name'] ?? '',
                        'role_shortcode' => $rule['shortcode'] ?? '',
                        'required' => $requiredCount,
                        'actual' => $actualCount,
                    ];
                }
            }
        }

        // Warnungen sinnvoll sortieren (Datum, Zeit, Rolle)
        usort(
            $coverageWarnings,
            function (array $a, array $b): int {
                if ($a['date'] === $b['date']) {
                    $timeA = ($a['time_from'] ?? '') . ($a['time_to'] ?? '');
                    $timeB = ($b['time_from'] ?? '') . ($b['time_to'] ?? '');
                    if ($timeA === $timeB) {
                        return ($a['role_name'] ?? '') <=> ($b['role_name'] ?? '');
                    }
                    return $timeA <=> $timeB;
                }
                return $a['date'] <=> $b['date'];
            }
        );

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
            'coverageWarnings' => $coverageWarnings,
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
