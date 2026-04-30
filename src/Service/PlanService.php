<?php

namespace App\Service;

use App\Repository\PlanRepository;
use App\Repository\ShiftRepository;
use App\Repository\RuleRepository;
use App\Repository\EmployeeRepository;
use App\Repository\HolidayRepository;
use App\Repository\AbsenceRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PlanService
{
    private PlanRepository $plans;
    private ShiftRepository $shifts;
    private RuleRepository $rules;
    private EmployeeRepository $employees;
    private HolidayRepository $holidays;
    private AbsenceRepository $absences;

    public function __construct()
    {
        $this->plans = new PlanRepository();
        $this->shifts = new ShiftRepository();
        $this->rules = new RuleRepository();
        $this->employees = new EmployeeRepository();
        $this->holidays = new HolidayRepository();
        $this->absences = new AbsenceRepository();
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

        $shifts = $this->shifts->findAll();

        $start = new \DateTimeImmutable($startDate);

        $assignmentsPerWeek = [];
        $assignedPerDay = [];
        $currentPlan = [];
        $completeShiftRoleAssignment = [];
        $employeesPerWeek = [];
        for ($weekIndex = 0; $weekIndex < $weeks; $weekIndex++) {
            $employeesPerWeek[$weekIndex] = $this->loadEmployeesWithRelationsForWeek($startDate, $weekIndex);
        }

        for ($weekIndex = 0; $weekIndex < $weeks; $weekIndex++) {

            $prevWeekOffset = 7 + $weekIndex * 7;
            $prevWeekStart = $start->modify("-{$prevWeekOffset} days")->format('Y-m-d');
            $prevPlan = $this->plans->findByStartDate($prevWeekStart);
            if ($prevPlan !== null) {
                $prevEntries = $this->plans->getEntriesWithDetails((int)$prevPlan['id']);
                foreach ($prevEntries as $entry) {
                    // Wir befüllen nur Tage, die nicht in diesen Dienstplan fallen
                    if ($entry['date'] < $startDate) {
                        $assignedPerDay[$entry['date']][(int)$entry['employee_id']] = (int)$entry['role_id'];
                    }
                }
            }

            $employees = $employeesPerWeek[$weekIndex];

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
                                $currentPlan
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
                        usort($candidates, function ($a, $b) use ($assignmentsPerWeek, $weekIndex, $randomOrder, $employees, $actualWeekday, $shiftId) {

                            // Es wird der Mitarbeiter mit der kleinsten Anzahl an möglichen Schichten (für diesen Tag) bevorzugt.
                            // Gedanke: Wenn ein Mitarbeiter nur eine bestimmte Schicht machen kann, dann soll er sie bekommen.
                            $employeeA = array_values(array_filter($employees, fn($emp) => (int)$emp['id'] === $a))[0] ?? null;
                            $employeeB = array_values(array_filter($employees, fn($emp) => (int)$emp['id'] === $b))[0] ?? null;
                            $aCount = PHP_INT_MAX;
                            $bCount = PHP_INT_MAX;
                            if (
                                !empty($employeeA['allowed_weekday_shifts']) &&
                                array_key_exists($actualWeekday, $employeeA['allowed_weekday_shifts']) &&
                                in_array($shiftId, $employeeA['allowed_weekday_shifts'][$actualWeekday], true)
                            ) {
                                $aCount = count($employeeA['allowed_weekday_shifts'][$actualWeekday]);
                            } else {
                                $aCount = count($employeeA['allowed_shifts']);
                            }
                            if (
                                !empty($employeeB['allowed_weekday_shifts']) &&
                                array_key_exists($actualWeekday, $employeeB['allowed_weekday_shifts']) &&
                                in_array($shiftId, $employeeB['allowed_weekday_shifts'][$actualWeekday], true)
                            ) {
                                $bCount = count($employeeB['allowed_weekday_shifts'][$actualWeekday]);
                            } else {
                                $bCount = count($employeeB['allowed_shifts']);
                            }
                            if ($aCount !== $bCount) {
                                return ($aCount <=> $bCount);
                            }

                            $ca = $assignmentsPerWeek[$a][$weekIndex] ?? 0;
                            $cb = $assignmentsPerWeek[$b][$weekIndex] ?? 0;
                            if ($ca === $cb) {
                                return ($randomOrder[$a] <=> $randomOrder[$b]);
                            }
                            return $ca <=> $cb;
                        });

                        $selected = array_slice($candidates, 0, $requiredCount);

                        foreach ($selected as $employeeId) {
                            $assignmentsPerWeek[$employeeId][$weekIndex] = ($assignmentsPerWeek[$employeeId][$weekIndex] ?? 0) + 1;
                            $assignedPerDay[$dateString][$employeeId] = $roleId;
                            $remainingEmployeeShifts[$employeeId]--;
                            if ($rule['required_count_exact'] === 1) {
                                $completeShiftRoleAssignment[$dateString][$shiftId][$roleId] = true;
                            }
                            $currentPlan[$dateString][$shiftId][$roleId][] = $employeeId;
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

            $employees = $employeesPerWeek[$weekIndex];

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
                    $requiredCount = (int)$rule['required_count'];

                    foreach ($employees as $employee) {

                        if (!$this->isEmployeeAllowedForDayShiftAndRole(
                            $employee,
                            $shiftId,
                            $roleId,
                            $dateString,
                            $currentPlan
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
                        $currentPlan[$dateString][$shiftId][$roleId][] = $employeeId;
                        $assignedPerDay[$dateString][$employeeId] = $roleId;
                        $assignmentsPerWeek[$employeeId][$weekIndex] = ($assignmentsPerWeek[$employeeId][$weekIndex] ?? 0) + 1;
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

            $employees = $employeesPerWeek[$weekIndex];

            $remainingEmployeeShifts = [];
            foreach ($employees as $employee) {
                $remainingEmployeeShifts[(int)$employee['id']] =
                    (int)$employee['max_shifts_per_week'] - ($assignmentsPerWeek[$employee['id']][$weekIndex] ?? 0);
            }
            // Hier gilt folgende Logik
            // Wir iterieren über alle Wochentage und Schichten und fügen nach und nach jeweils ein Mitarbeiter hinzu
            // Dies tun wir solange, bis alle Mitarbeiter auf alle Schichten verteilt sind
            // Durch das tageweise hinzufügen eines einzelnen Mitarbeiters werden die Wochentage halbwegs gleichmäßig besetzt
            $maxIterations = 500;
            $iterations = 0;
            while (max($remainingEmployeeShifts) > 0 && $iterations < $maxIterations) {
                $iterations++;
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
                                    $currentPlan
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
                                $assignmentsPerWeek[$employeeId][$weekIndex] = ($assignmentsPerWeek[$employeeId][$weekIndex] ?? 0) + 1;
                                $assignedPerDay[$dateString][$employeeId] = $roleId;
                                $remainingEmployeeShifts[$employeeId]--;
                                if ($rule['required_count_exact'] === 1) {
                                    $completeShiftRoleAssignment[$dateString][$shiftId][$roleId] = true;
                                }
                                $currentPlan[$dateString][$shiftId][$roleId][] = $employeeId;
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

        // Nun prüfen wir, ob es Mitarbeiter mit unbesetzten Schichten gibt
        // Wenn ja, dann versuchen wir, diese Schichten durch Wechsel mit anderen Kollegen zu besetzen.
        for ($weekIndex = 0; $weekIndex < $weeks; $weekIndex++) {

            $employees = $employeesPerWeek[$weekIndex];

            foreach ($employees as $employee) {

                $employeeId = (int)$employee['id'];
                $remainingEmployeeShifts = (int)$employee['max_shifts_per_week'] - ($assignmentsPerWeek[$employeeId][$weekIndex] ?? 0);
                if ($remainingEmployeeShifts <= 0) {
                    continue;
                }

                error_log('Employee: ' . $employeeId . '/' . $employee['name'] . ' has remaining shifts: ' . $remainingEmployeeShifts);

                // Ermittle alle Wochentage und Schichten, die der Mitarbeiter machen darf
                $employeeAllowedWeekdayShifts = $this->findAllowedWeekdayShifts($employee);

                foreach ($employeeAllowedWeekdayShifts as $allowedWeekday => $allowedShiftIds) {

                    $dayIndex = $weekIndex * 7 + $allowedWeekday;
                    $dateString = $start->modify("+{$dayIndex} day")->format('Y-m-d');

                    foreach ($allowedShiftIds as $allowedShiftId) {

                        $allowedShiftId = (int)$allowedShiftId;

                        foreach ($employee['roles'] as $roleId) {

                            if (!$this->isEmployeeAllowedForDayShiftAndRole($employee, $allowedShiftId, $roleId, $dateString, $currentPlan)) {
                                continue;
                            }

                            error_log('Employee: ' . $employeeId . '/' . $employee['name'] . ' is allowed at ' . $dateString . ' for shift: ' . $allowedShiftId . ' ' . $roleId);

                            // Ok, der Mitarbeiter ist an diesen Tag und in dieser Schicht nicht besetzt.
                            // Prüfen, ob bereits besetzte Mitarbeiter auf eine andere freie Schicht wechseln können
                            $assignedEmployees = $currentPlan[$dateString][$allowedShiftId][$roleId] ?? [];
                            error_log('Assigned employees at ' . $dateString . ' ' . $allowedShiftId . ' ' . $roleId . ': ' . print_r($assignedEmployees, true));

                            foreach ($assignedEmployees as $assignedEmployeeId) {

                                $assignedEmployee = null;
                                foreach ($employees as $emp) {
                                    if ((int)$emp['id'] === (int)$assignedEmployeeId) {
                                        $assignedEmployee = $emp;
                                        break;
                                    }
                                }

                                // Suchen nach einer verfügbaren Schicht für den Kollegen
                                $availableShifts = $this->findAvailableReplacementShiftsForEmployee($assignedEmployee, $weekIndex, $dateString, $allowedShiftId, $currentPlan);
                                if (empty($availableShifts)) {
                                    error_log('No available shifts found for employee: ' . $assignedEmployeeId);
                                    continue;
                                }
                                error_log('Found ' . count($availableShifts) . ' available shifts for employee: ' . $assignedEmployeeId);
                                $availableDateString = $availableShifts[0]['date'];
                                $availableShiftId = $availableShifts[0]['shift_id'];
                                $availableRoleId = $availableShifts[0]['role_id'];

                                // Wechsel durchführen: Kollege aus der Ursprungsschicht entfernen
                                error_log('Deleting entry: ' . $dateString . ' ' . $allowedShiftId . ' ' . $assignedEmployeeId . ' ' . $roleId);
                                $this->plans->deleteEntry(
                                    $planId,
                                    $dateString,
                                    $allowedShiftId,
                                    $assignedEmployeeId,
                                    $roleId
                                );
                                if (($key = array_search($assignedEmployeeId, $currentPlan[$dateString][$allowedShiftId][$roleId] ?? [], true)) !== false) {
                                    unset($currentPlan[$dateString][$allowedShiftId][$roleId][$key]);
                                    // Re-index to maintain numeric keys
                                    $currentPlan[$dateString][$allowedShiftId][$roleId] = array_values($currentPlan[$dateString][$allowedShiftId][$roleId]);
                                }

                                // Kollege auf die neue Schicht setzen
                                $currentPlan[$dateString][$allowedShiftId][$roleId][] = $employeeId;
                                error_log('Adding entry: ' . $availableDateString . ' ' . $availableShiftId . ' ' . $assignedEmployeeId . ' ' . $availableRoleId);
                                $this->plans->addEntry(
                                    $planId,
                                    $availableDateString,
                                    $availableShiftId,
                                    $assignedEmployeeId,
                                    $availableRoleId
                                );

                                // Mitarbeiter mit unbesetzten Schichten auf die neue Schicht setzen
                                $currentPlan[$availableDateString][$availableShiftId][$availableRoleId][] = $employeeId;
                                error_log('Adding entry: ' . $dateString . ' ' . $allowedShiftId . ' ' . $employeeId . ' ' . $roleId);
                                $this->plans->addEntry(
                                    $planId,
                                    $dateString,
                                    $allowedShiftId,
                                    $employeeId,
                                    $roleId
                                );
                                break 4;
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
        $employeeHolidays = $data['employeeHolidays'] ?? [];

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

        // Mitarbeiteranzahl pro Datum (mindestens eine Schicht)
        $employeeCountByDate = [];
        foreach ($dates as $date) {
            $count = 0;
            foreach ($employees as $employee) {
                $entries = $grid[$employee['id']][$date] ?? [];
                if (!empty($entries)) {
                    $count++;
                }
            }
            $employeeCountByDate[$date] = $count;
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
                                'names' => [],
                            ];
                        }
                        $byShift[$key]['count']++;
                        $byShift[$key]['names'][] = $employee['name'] ?? '';
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
        // Standard: vertikale Ausrichtung unten für alle Zellen der Arbeitsmappe
        $spreadsheet->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_BOTTOM);

        for ($weekIndex = 0; $weekIndex < $weeks; $weekIndex++) {
            $sheet = $weekIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            // Gleiche Beschriftung wie die Tabs in der Web-Ansicht: "KW 11 (09.03.–15.03.)"
            $weekStartIndex = $weekIndex * 7;
            $weekDates = array_slice($dates, $weekStartIndex, 7);
            $sheetTitle = 'Woche ' . ($weekIndex + 1);
            if (count($weekDates) >= 2) {
                $first = new \DateTimeImmutable($weekDates[0]);
                $last = new \DateTimeImmutable($weekDates[count($weekDates) - 1]);
                $sheetTitle = 'KW ' . $first->format('W') . ' (' . $first->format('d.m.') . '–' . $last->format('d.m.') . ')';
                // Excel-Blattnamen: max. 31 Zeichen, keine Zeichen \ / * ? : [ ]
                $sheetTitle = substr(str_replace(['\\', '/', '*', '?', ':', '[', ']'], '', $sheetTitle), 0, 31);
            }
            $sheet->setTitle($sheetTitle);
            // Zoomfaktor auf 110 % setzen
            $sheet->getSheetView()->setZoomScale(110);

            // Kopfzeile: Zeile 2 = Wochentag/Datum (über 2 Spalten), Zeile 3 = Unterüberschriften
            // "Mitarbeiter" steht in Zeile 3 der zweiten Spalte (erste Zeile/Spalte bleiben leer)
            $sheet->setCellValue('B3', 'Mitarbeiter');

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

                $colTimeIndex = 3 + $dayOffset * 2;
                $colRoleIndex = $colTimeIndex + 1;
                $colTime = Coordinate::stringFromColumnIndex($colTimeIndex);
                $colRole = Coordinate::stringFromColumnIndex($colRoleIndex);

                // Zeile 2: Tages-Header über beide Spalten
                $sheet->mergeCells($colTime . '2:' . $colRole . '2');
                $sheet->setCellValue($colTime . '2', $label);

                // Zeile 3: Unterüberschriften
                $sheet->setCellValue($colTime . '3', 'Arbeitszeit');
                $sheet->setCellValue($colRole . '3', 'Rolle');
            }

            $lastCol = Coordinate::stringFromColumnIndex(2 + 7 * 2);
            // Überschriftenzeilen 2–3 fett, horizontal zentriert, vertikal unten, mit grauem Hintergrund
            $headerRange = 'B2:' . $lastCol . '3';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_BOTTOM);
            $sheet->getStyle($headerRange)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFEFEFEF');

            // Dünner Rahmen unten an Zeile 2 und 3 (erste Tabellenzeilen)
            $secondRowRange = 'B2:' . $lastCol . '2';
            $sheet->getStyle($secondRowRange)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
            $thirdRowRange = 'B3:' . $lastCol . '3';
            $sheet->getStyle($thirdRowRange)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);

            // Daten beginnen in Zeile 4
            $row = 4;
            $employeeRowIndex = 0;
            foreach ($employees as $employee) {
                $employeeRowIndex++;
                $employeeId = (int)$employee['id'];

                // Alternierender Hintergrund für Mitarbeiterzeilen (Zebra)
                if ($employeeRowIndex % 2 === 0) {
                    $zebraRange = 'B' . $row . ':' . $lastCol . $row;
                    $sheet->getStyle($zebraRange)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFE6F0FF');
                }

                // Mitarbeiter-Namen in die erste Daten-Spalte (B) schreiben
                $sheet->setCellValue('B' . $row, $employee['name'] ?? '');

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

                    $colTimeIndex = 3 + $dayOffset * 2;
                    $colRoleIndex = $colTimeIndex + 1;
                    $colTime = Coordinate::stringFromColumnIndex($colTimeIndex);
                    $colRole = Coordinate::stringFromColumnIndex($colRoleIndex);

                    $isOnHoliday = !empty($employeeHolidays[$employeeId][$dateString] ?? false);

                    if ($isOnHoliday) {
                        $sheet->setCellValue($colTime . $row, 'U');
                        $sheet->getStyle($colTime . $row)->getFont()->getColor()->setARGB('FF888888');
                    } elseif ($times) {
                        $sheet->setCellValue($colTime . $row, implode(', ', $times));
                    }
                    if ($roles) {
                        $sheet->setCellValue($colRole . $row, implode(', ', $roles));
                    }
                }

                $row++;
            }

            // Footer-Zeilen ergänzen: Mitarbeiteranzahl, Rollen, Schichten
            $footerCountRow = $row;
            $footerRolesRow = $row + 1;
            $footerShiftsRow = $row + 2;

            $sheet->setCellValue('B' . $footerCountRow, 'Mitarbeiter');
            $sheet->setCellValue('B' . $footerRolesRow, 'Rollen');
            $sheet->setCellValue('B' . $footerShiftsRow, 'Schichten');
            // Footer-Bezeichner wie Überschrift formatieren
            $sheet->getStyle('B' . $footerCountRow)->getFont()->setBold(true);
            $sheet->getStyle('B' . $footerRolesRow)->getFont()->setBold(true);
            $sheet->getStyle('B' . $footerShiftsRow)->getFont()->setBold(true);
            // Footer-Zeilen Hintergrund grau
            $footerFullRange = 'B' . $footerCountRow . ':' . $lastCol . $footerShiftsRow;
            $sheet->getStyle($footerFullRange)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFEFEFEF');

            // Dünner Rahmen oben an allen Footerzeilen (drittletzte, vorletzte, letzte Zeile)
            $footerTopCountRange = 'B' . $footerCountRow . ':' . $lastCol . $footerCountRow;
            $sheet->getStyle($footerTopCountRange)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
            $footerTopRolesRange = 'B' . $footerRolesRow . ':' . $lastCol . $footerRolesRow;
            $sheet->getStyle($footerTopRolesRange)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
            $footerTopShiftsRange = 'B' . $footerShiftsRow . ':' . $lastCol . $footerShiftsRow;
            $sheet->getStyle($footerTopShiftsRange)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
            // Dünner Rahmen unterhalb der letzten Zeile
            $footerBottomShiftsRange = 'B' . $footerShiftsRow . ':' . $lastCol . $footerShiftsRow;
            $sheet->getStyle($footerBottomShiftsRange)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);

            $maxShiftLines = 0;

            for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
                $dateIndex = $weekIndex * 7 + $dayOffset;
                if (!isset($dates[$dateIndex])) {
                    continue;
                }
                $dateString = $dates[$dateIndex];

                $colTimeIndex = 3 + $dayOffset * 2;
                $colRoleIndex = $colTimeIndex + 1;
                $colTime = Coordinate::stringFromColumnIndex($colTimeIndex);
                $colRole = Coordinate::stringFromColumnIndex($colRoleIndex);

                // Mitarbeiteranzahl pro Datum
                $employeeCount = $employeeCountByDate[$dateString] ?? 0;
                $countRange = $colTime . $footerCountRow . ':' . $colRole . $footerCountRow;
                $sheet->mergeCells($countRange);
                $sheet->setCellValue($colTime . $footerCountRow, $employeeCount);
                $sheet->getStyle($countRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

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
                    $names = array_unique($info['names'] ?? []);
                    $names = array_values(array_filter($names, static fn($n) => $n !== ''));
                    $label = ($info['time_range'] ?? '') . ': ' . $info['count'];
                    if ($names) {
                        $label .= ' (' . implode(', ', $names) . ')';
                    }
                    $shiftParts[] = $label;
                }
                if ($shiftParts) {
                    // Alle Schichten des Tages in einer Zelle mit Zeilenumbruch (über beide Spalten).
                    $shiftRange = $colTime . $footerShiftsRow . ':' . $colRole . $footerShiftsRow;
                    $sheet->mergeCells($shiftRange);
                    $sheet->setCellValue($colTime . $footerShiftsRow, implode("\n", $shiftParts));
                    $sheet->getStyle($shiftRange)->getAlignment()
                        ->setWrapText(true)
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $maxShiftLines = max($maxShiftLines, count($shiftParts));
                }
            }

            if ($maxShiftLines > 0) {
                $defaultHeight = $sheet->getDefaultRowDimension()->getRowHeight();
                if ($defaultHeight <= 0) {
                    $defaultHeight = 15;
                }
                // etwas Puffer, damit alle Textzeilen sicher sichtbar sind
                $effectiveLines = $maxShiftLines + 5;
                $sheet->getRowDimension($footerShiftsRow)->setRowHeight($defaultHeight * $effectiveLines);
            }

            // Footer-Zellen vertikal oben ausrichten
            $footerRange = 'B' . $footerRolesRow . ':' . $lastCol . $footerShiftsRow;
            $sheet->getStyle($footerRange)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

            // Trennlinie zwischen "Arbeitszeit" und "Rolle" explizit setzen, damit sie auch bei Zellfüllungen (Zebra) sichtbar bleibt.
            // Excel-Gridlines werden bei gefüllten Zellen nicht angezeigt.
            for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
                $colTimeIndex = 3 + $dayOffset * 2;
                $colRoleIndex = $colTimeIndex + 1;
                $colRole = Coordinate::stringFromColumnIndex($colRoleIndex);

                $dividerRange = $colRole . '3:' . $colRole . $footerShiftsRow;
                $sheet->getStyle($dividerRange)->getBorders()->getLeft()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFBFBFBF');
            }

            // Für jeden Tag einen fetten Rahmen um den kompletten Bereich (Überschriften, Daten, Footer)
            for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
                $colTimeIndex = 3 + $dayOffset * 2;
                $colRoleIndex = $colTimeIndex + 1;
                $colTime = Coordinate::stringFromColumnIndex($colTimeIndex);
                $colRole = Coordinate::stringFromColumnIndex($colRoleIndex);

                $dayRange = $colTime . '2:' . $colRole . $footerShiftsRow;
                $sheet->getStyle($dayRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
            }

            // Dünner Rahmen um die gesamte Tabelle (außen)
            $tableRange = 'B2:' . $lastCol . $footerShiftsRow;
            $sheet->getStyle($tableRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);

            // Spaltenbreite automatisch an Inhalt anpassen
            for ($colIndex = 2; $colIndex <= 2 + 7 * 2; $colIndex++) {
                $col = Coordinate::stringFromColumnIndex($colIndex);
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Beim Öffnen soll nur A1 ausgewählt sein (keine ganze Tabelle markiert)
            $sheet->setSelectedCell('A1');
            // Je nach PhpSpreadsheet-Version sitzt top-left am Pane-Objekt (nicht direkt am SheetView)
            if (method_exists($sheet->getSheetView(), 'getPane')) {
                $pane = $sheet->getSheetView()->getPane();
                if ($pane && method_exists($pane, 'setTopLeftCell')) {
                    $pane->setTopLeftCell('A1');
                }
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

    /**
     * Ermittelt die erlaubten Schichten pro Wochentag für einen Mitarbeiter.
     * Falls `allowed_weekday_shifts` für einen Tag gesetzt ist, gilt diese Einschränkung.
     * Andernfalls gelten alle `allowed_shifts` für diesen Wochentag.
     *
     * @return array<int, array<int>>
     */
    private function findAllowedWeekdayShifts(array $employee): array
    {
        $allowedWeekdays = array_values(array_map('intval', (array)($employee['allowed_weekdays'] ?? [])));

        $defaultShiftIds = array_values(array_map(
            static fn($a) => (int)($a['shift_id'] ?? 0),
            (array)($employee['allowed_shifts'] ?? [])
        ));
        $defaultShiftIds = array_values(array_filter($defaultShiftIds, static fn($sid) => $sid > 0));

        $result = [];
        foreach ($allowedWeekdays as $weekday) {
            $daySpecific = $employee['allowed_weekday_shifts'][$weekday] ?? null;
            $shiftIds = $daySpecific !== null ? (array)$daySpecific : $defaultShiftIds;
            $shiftIds = array_values(array_map('intval', $shiftIds));
            $shiftIds = array_values(array_filter($shiftIds, static fn($sid) => $sid > 0));
            $result[$weekday] = $shiftIds;
        }

        return $result;
    }

    /**
     * Liefert "freie" Schichten (Datum/Schicht/Rolle) innerhalb einer Woche, bei denen der Mitarbeiter
     * laut seinen Einschränkungen grundsätzlich eingesetzt werden könnte und im aktuellen Plan an diesem
     * Tag noch nicht eingeteilt ist.
     *
     * @return array<int, array{date: string, shift_id: int, role_id: int}>
     */
    private function findAvailableReplacementShiftsForEmployee(array $employee, int $weekIndex, string $replacementDateString, int $replacementShiftId, array $currentPlan): array
    {
        $employeeId = (int)($employee['id'] ?? 0);
        if ($employeeId <= 0) {
            return [];
        }

        $datesInPlan = array_keys($currentPlan);
        if (!$datesInPlan) {
            return [];
        }
        sort($datesInPlan);
        $planStartDate = $datesInPlan[0];

        $start = new \DateTimeImmutable($planStartDate);
        $weekStart = $start->modify('+' . ($weekIndex * 7) . ' days');

        $rules = $this->rules->findAllWithDetails();

        $allowedShiftIdsDefault = array_values(array_map(
            static fn($a) => (int)($a['shift_id'] ?? 0),
            $employee['allowed_shifts'] ?? []
        ));
        $allowedShiftIdsDefault = array_values(array_filter($allowedShiftIdsDefault, static fn($sid) => $sid > 0));

        $availableShifts = [];

        for ($weekday = 0; $weekday < 7; $weekday++) {
            if (!in_array($weekday, $employee['allowed_weekdays'] ?? [], true)) {
                continue;
            }

            $dateString = $weekStart->modify('+' . $weekday . ' days')->format('Y-m-d');

            // Wenn der Mitarbeiter an diesem Tag bereits irgendwo eingeteilt ist, ist nichts "frei".
            $dayAssignments = $currentPlan[$dateString] ?? [];
            $alreadyAssignedToday = false;
            foreach ($dayAssignments as $shiftAssignments) {
                foreach ($shiftAssignments as $roleAssignments) {
                    if (in_array($employeeId, $roleAssignments ?? [], true)) {
                        $alreadyAssignedToday = true;
                        break 2;
                    }
                }
            }
            if ($alreadyAssignedToday) {
                continue;
            }

            $allowedShiftIds = $employee['allowed_weekday_shifts'][$weekday] ?? $allowedShiftIdsDefault;
            $allowedShiftIds = array_values(array_map('intval', (array)$allowedShiftIds));
            $allowedShiftIds = array_values(array_filter($allowedShiftIds, static fn($sid) => $sid > 0));

            foreach ($allowedShiftIds as $shiftId) {

                foreach (($employee['roles'] ?? []) as $roleId) {

                    $rule = null;
                    foreach ($rules as $r) {
                        if ((int)$r['shift_id'] === $shiftId && (int)$r['role_id'] === $roleId) {
                            $rule = $r;
                            break;
                        }
                    }
                    if ($rule === null) {
                        continue;
                    }
                    $requiredCount = (int)$rule['required_count'];
                    $requiredCountExact = (int)$rule['required_count_exact'];
                    if ($requiredCountExact === 1 && count($currentPlan[$dateString][$shiftId][$roleId] ?? []) >= $requiredCount) {
                        continue;
                    }

                    // Wenn der Tag ein anderer ist als der, der ersetzt wird, dann prüfen, ob der Mitarbeiter an diesem Tag bereits eingeteilt ist.
                    // Ansonsten muss es lediglich eine andere Schicht sein.
                    if ($dateString != $replacementDateString) {
                        $assignedThisDay = false;
                        foreach ($currentPlan[$dateString][$shiftId][$roleId] ?? [] as $assignedEmployeeId) {
                            if ((int)$assignedEmployeeId === $employeeId) {
                                $assignedThisDay = true;
                                break;
                            }
                        }
                        if ($assignedThisDay) {
                            continue;
                        }
                    } else if ($shiftId === $replacementShiftId) {
                        error_log('Skipping shift: ' . $dateString . ' ' . $shiftId . ' ' . $roleId);
                        continue;
                    }

                    $availableShifts[] = [
                        'date' => $dateString,
                        'shift_id' => (int)$shiftId,
                        'role_id' => (int)$roleId,
                    ];
                }
            }
        }

        return $availableShifts;
    }

    private function isEmployeeAllowedForDayShiftAndRole(
        array $employee,
        int $shiftId,
        int $roleId,
        string $dateString,
        array $currentPlan
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

        $allowedShiftsToday = $employee['allowed_weekday_shifts'][$actualWeekday] ?? [];
        if (!empty($allowedShiftsToday) && !in_array($shiftId, $allowedShiftsToday, true)) {
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
            // Max. diese Schicht pro Woche (anhand $currentPlan prüfen)
            $date = new \DateTimeImmutable($dateString);
            $monday = $date->modify('-' . ((int)$date->format('N') - 1) . ' days');
            $countThisShift = 0;
            for ($i = 0; $i < 7; $i++) {
                $dayInWeek = $monday->modify("+{$i} days")->format('Y-m-d');
                $shiftAssignments = $currentPlan[$dayInWeek][$shiftId] ?? [];
                foreach ($shiftAssignments as $roleAssignments) {
                    if (in_array($employeeId, $roleAssignments, true)) {
                        $countThisShift++;
                        if ($countThisShift >= $maxThisShiftPerWeek) {
                            return false;
                        }
                    }
                }
            }
        }

        // Max. Schichten pro Woche (anhand $currentPlan prüfen)
        $date = new \DateTimeImmutable($dateString);
        $monday = $date->modify('-' . ((int)$date->format('N') - 1) . ' days');
        $currentWeekCount = 0;
        for ($i = 0; $i < 7; $i++) {
            $dayInWeek = $monday->modify("+{$i} days")->format('Y-m-d');
            $dayAssignments = $currentPlan[$dayInWeek] ?? [];
            foreach ($dayAssignments as $shiftAssignments) {
                foreach ($shiftAssignments as $roleAssignments) {
                    if (in_array($employeeId, $roleAssignments, true)) {
                        $currentWeekCount++;
                        if ($currentWeekCount >= (int)$employee['max_shifts_per_week']) {
                            return false;
                        }
                    }
                }
            }
        }

        // Nur eine Schicht am Wochenende erlaubt (anhand $currentPlan prüfen)
        if ($actualWeekday >= 5) {
            $date = new \DateTimeImmutable($dateString);
            $monday = $date->modify('-' . ((int)$date->format('N') - 1) . ' days');
            $weekendDates = [
                $monday->modify('+5 days')->format('Y-m-d'),
                $monday->modify('+6 days')->format('Y-m-d'),
            ];
            foreach ($weekendDates as $weekendDate) {
                $dayAssignments = $currentPlan[$weekendDate] ?? [];
                foreach ($dayAssignments as $shiftAssignments) {
                    foreach ($shiftAssignments as $roleAssignments) {
                        if (in_array($employeeId, $roleAssignments, true)) {
                            return false;
                        }
                    }
                }
            }
        }

        $dayAssignments = $currentPlan[$dateString] ?? [];
        foreach ($dayAssignments as $shiftAssignments) {
            foreach ($shiftAssignments as $roleAssignments) {
                if (in_array($employeeId, $roleAssignments, true)) {
                    return false;
                }
            }
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
        $weeks = (int)$plan['weeks'];
        $startDate = $plan['start_date'];

        $entries = $this->plans->getEntriesWithDetails($planId);

        // Alle Tage des Plans aus start_date und weeks ableiten (immer volle Wochen anzeigen)
        $start = new \DateTimeImmutable($startDate);
        $totalDays = (int)$plan['weeks'] * 7;
        $dateList = [];
        for ($i = 0; $i < $totalDays; $i++) {
            $dateList[] = $start->modify("+{$i} day")->format('Y-m-d');
        }
        $dateSet = array_flip($dateList);

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

        // Urlaubstage pro Mitarbeiter/Datum innerhalb des Planzeitraums ermitteln
        $employeeHolidays = [];
        if ($totalDays > 0) {
            $planEnd = $start->modify('+' . ($totalDays - 1) . ' days');
            $holidays = $this->holidays->findByDateRange(
                $start->format('Y-m-d'),
                $planEnd->format('Y-m-d')
            );

            foreach ($holidays as $holiday) {
                $empId = (int)$holiday['employee_id'];
                $from = new \DateTimeImmutable($holiday['date_from']);
                $to = new \DateTimeImmutable($holiday['date_to']);

                $current = $from;
                while ($current <= $to) {
                    $dateString = $current->format('Y-m-d');
                    if (isset($dateSet[$dateString])) {
                        $employeeHolidays[$empId][$dateString] = true;
                    }
                    $current = $current->modify('+1 day');
                }
            }
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

            $employeeWeeklyShiftCounts[$employeeId][$weekIndex] = ($employeeWeeklyShiftCounts[$employeeId][$weekIndex] ?? 0) + 1;
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
                        'shift_id' => $shiftId,
                        'role_id' => $roleId,
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

        // Pro Warnung: Mitarbeiter ermitteln, die an diesem Tag in dieser Schicht/Rolle eingeteilt werden könnten
        foreach ($coverageWarnings as &$warning) {
            $weekdayZeroBased = (int)(new \DateTimeImmutable($warning['date']))->format('N') - 1;
            $warning['eligible_employees'] = $this->employees->getEligibleEmployeeNamesForShiftOnWeekday(
                $weekdayZeroBased,
                (int)$warning['shift_id'],
                (int)$warning['role_id']
            );
        }
        unset($warning);

        // Maximalwerte aus den Mitarbeiterdaten holen
        $employeesPerWeek = [];
        for ($weekIndex = 0; $weekIndex < $weeks; $weekIndex++) {
            $employeesPerWeek[$weekIndex] = $this->loadEmployeesWithRelationsForWeek($startDate, $weekIndex);
        }

        $employeeUnderloadWarnings = [];

        for ($weekIndex = 0; $weekIndex < $weeks; $weekIndex++) {

            $employees = $employeesPerWeek[$weekIndex];

            foreach ($employees as $employee) {
                $employeeId = (int)$employee['id'];
                $maxPerWeek = (int)($employee['max_shifts_per_week'] ?? 0);
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
            'employeeHolidays' => $employeeHolidays,
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

    private function loadEmployeesWithRelationsForWeek($startDate, $weekIndex): array
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

        $weekStart = (new \DateTimeImmutable($startDate))->modify('+' . ($weekIndex * 7) . ' days');
        $weekEnd   = $weekStart->modify('+6 days');

        $allShifts = $this->shifts->findAll();

        $holidays = $this->holidays->findByDateRange(
            $weekStart->format('Y-m-d'),
            $weekEnd->format('Y-m-d')
        );

        $holidaysByEmployee = [];
        foreach ($holidays as $holiday) {
            $holidaysByEmployee[(int)$holiday['employee_id']][] = $holiday;
        }

        $absences = $this->absences->findByDateRange(
            $weekStart->format('Y-m-d'),
            $weekEnd->format('Y-m-d')
        );

        $absencesByEmployee = [];
        foreach ($absences as $absence) {
            $absencesByEmployee[(int)$absence['employee_id']][] = $absence;
        }

        foreach ($result as &$employee) {
            $empId = (int)$employee['id'];
            if (!isset($holidaysByEmployee[$empId])) {
                continue;
            }

            // Urlaubstage dieser Woche als Wochentage (0 = Montag, 6 = Sonntag) ermitteln
            $holidayWeekdays = [];
            foreach ($holidaysByEmployee[$empId] as $holiday) {
                $from = new \DateTimeImmutable($holiday['date_from']);
                $to   = new \DateTimeImmutable($holiday['date_to']);
                // Auf Wochengrenzen eingrenzen
                $from = $from < $weekStart ? $weekStart : $from;
                $to   = $to   > $weekEnd   ? $weekEnd   : $to;
                $current = $from;
                while ($current <= $to) {
                    $holidayWeekdays[(int)$current->format('N') - 1] = true;
                    $current = $current->modify('+1 day');
                }
            }

            $employee['allowed_weekdays'] = array_values(
                array_filter($employee['allowed_weekdays'], fn($wd) => !isset($holidayWeekdays[$wd]))
            );

            foreach (array_keys($holidayWeekdays) as $wd) {
                unset($employee['allowed_weekday_shifts'][$wd]);
            }

            // Die maximale Anzahl an Schichten pro Woche ist nun die erneute Anzahl der Wochentage
            // darf jedoch die ursprüngliche maximale Anzahl an Schichten pro Woche nicht überschreiten.
            $employee['max_shifts_per_week'] = min((int)$employee['max_shifts_per_week'], count($employee['allowed_weekdays']));
        }
        unset($employee);

        foreach ($result as &$employee) {
            $empId = (int)$employee['id'];

            if (isset($absencesByEmployee[$empId])) {

                foreach ($absencesByEmployee[$empId] as $absence) {

                    $absenceDate = new \DateTimeImmutable($absence['date']);
                    $absenceWeekday = (int)$absenceDate->format('N') - 1;
                    $absenceShiftId = isset($absence['shift_id']) ? (int)$absence['shift_id'] : null;

                    if (isset($absenceShiftId)) {
                        if (isset($employee['allowed_weekday_shifts'][$absenceWeekday])) {
                            if (in_array($absenceShiftId, $employee['allowed_weekday_shifts'][$absenceWeekday], true)) {
                                // Remove shift_id from allowed_weekday_shifts for this weekday
                                $employee['allowed_weekday_shifts'][$absenceWeekday] = array_values(
                                    array_filter(
                                        $employee['allowed_weekday_shifts'][$absenceWeekday],
                                        static fn($sid) => $sid !== $absenceShiftId
                                    )
                                );
                            }
                        } else {
                            // Der Mitarbeiter hat keinen Eintrag in allowed_weekday_shifts für diesen Wochentag.
                            // Erzeuge ein "künstliches" allowed_weekday_shifts-Array für diesen Wochentag
                            // Dieses enthält alle Schichten, die an diesem Tag möglich sind, außer der ausgeglichenen Schicht.
                            $shiftsAtThisDay = array_column(array_filter($allShifts, fn($shift) => in_array($absenceWeekday, $shift['weekdays'])), 'id');
                            $employee['allowed_weekday_shifts'][$absenceWeekday] = array_values(array_filter(
                                array_column($employee['allowed_shifts'], 'shift_id'),
                                static fn($shiftId) => $shiftId !== $absenceShiftId && in_array($shiftId, $shiftsAtThisDay, true) && in_array($shiftId, array_column($employee['allowed_shifts'], 'shift_id'), true)
                            ));
                        }
                    } else {
                        $employee['allowed_weekdays'] = array_filter($employee['allowed_weekdays'], fn($wd) => $wd !== $absenceWeekday);
                        unset($employee['allowed_weekday_shifts'][$absenceWeekday]);
                    }
                }
            }
        }
        unset($employee);

        return $result;
    }
}
