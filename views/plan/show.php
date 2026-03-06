<?php
/** @var array $plan */
/** @var array $employees */
/** @var array $dates */
/** @var array $grid */
require __DIR__ . '/../helpers/format_time.php';

$weekdayNames = [
    1 => 'Mo',
    2 => 'Di',
    3 => 'Mi',
    4 => 'Do',
    5 => 'Fr',
    6 => 'Sa',
    7 => 'So',
];

// Pro Datum: Rollen-Kürzel mit Anzahl
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

// Pro Datum: Gesamtanzahl der eingeteilten Mitarbeiter (mindestens eine Schicht)
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

// Pro Datum: Schichten mit Mitarbeiteranzahl (Schicht = Name + Uhrzeit)
$shiftsByDate = [];
foreach ($dates as $date) {
    $byShift = [];
    foreach ($employees as $employee) {
        $entries = $grid[$employee['id']][$date] ?? [];
        foreach ($entries as $entry) {
            $key = ($entry['shift_name'] ?? '') . '|' . ($entry['time_from'] ?? '') . '|' . ($entry['time_to'] ?? '');
            if ($key !== '||') {
                if (!isset($byShift[$key])) {
                    $byShift[$key] = [
                        'time_range' => formatTimeRange($entry['time_from'] ?? '', $entry['time_to'] ?? ''),
                        'count' => 0,
                        'time_from' => $entry['time_from'] ?? '',
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

$employeeWeeklyShiftCounts = $employeeWeeklyShiftCounts ?? [];
$employeeUnderloadWarnings = $employeeUnderloadWarnings ?? [];
$coverageWarnings = $coverageWarnings ?? [];

$planPeriod = '';
if (!empty($dates)) {
    $firstDate = reset($dates);
    $lastDate = end($dates);
    try {
        $first = new DateTime($firstDate);
        $last = new DateTime($lastDate);
        $planPeriod = $first->format('d.m.Y') . ' – ' . $last->format('d.m.Y');
    } catch (Exception $e) {
        // Fallback: Zeitraum nicht formatieren, falls Datum ungültig ist
        $planPeriod = '';
    }
}
?>

<h1>
    Dienstplan
    <?php if ($planPeriod !== ''): ?>
        <?php echo htmlspecialchars($planPeriod, ENT_QUOTES, 'UTF-8'); ?>
    <?php endif; ?>
</h1>

<?php
$formattedStartDate = '';
if (!empty($plan['start_date'])) {
    try {
        $start = new DateTime($plan['start_date']);
        $formattedStartDate = $start->format('d.m.Y');
    } catch (Exception $e) {
        $formattedStartDate = (string)$plan['start_date'];
    }
}
?>
<p>
    Startdatum: <?php echo htmlspecialchars($formattedStartDate, ENT_QUOTES, 'UTF-8'); ?><br>
    Wochen: <?php echo (int)$plan['weeks']; ?>
</p>

<p>
    <a href="<?= BASE_PATH ?>/plan/export?id=<?= (int)$plan['id'] ?>">Excel-Export</a>
</p>

<?php if (!empty($employeeUnderloadWarnings)): ?>
    <div style="border: 1px solid #f0ad4e; background-color: #fcf8e3; padding: 10px; margin-bottom: 15px;">
        <strong>Warnung zur Auslastung:</strong>
        <ul style="margin: 5px 0 0 20px;">
            <?php foreach ($employeeUnderloadWarnings as $warning): ?>
                <li>
                    <?php echo htmlspecialchars($warning['employee_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    in Woche <?php echo (int)$warning['week']; ?>:
                    <?php echo (int)$warning['actual']; ?> von
                    <?php echo (int)$warning['max']; ?> geplanten Schichten.
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($coverageWarnings)): ?>
    <div style="border: 1px solid #d9534f; background-color: #f2dede; padding: 10px; margin-bottom: 15px;">
        <strong>Warnung zur Schichtbelegung:</strong>
        <ul style="margin: 5px 0 0 20px;">
            <?php foreach ($coverageWarnings as $warning): ?>
                <?php
                $dt = new DateTime($warning['date']);
                $w = (int)$dt->format('N');
                $label = $weekdayNames[$w] . ' ' . $dt->format('d.m.');
                $timeRange = formatTimeRange($warning['time_from'] ?? '', $warning['time_to'] ?? '');
                $roleLabel = $warning['role_shortcode'];
                ?>
                <li>
                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>,
                    <?php echo htmlspecialchars($warning['shift_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    (<?php echo htmlspecialchars($timeRange, ENT_QUOTES, 'UTF-8'); ?>),
                    Rolle <?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?>:
                    geplant <?php echo (int)$warning['required']; ?>,
                    tatsächlich <?php echo (int)$warning['actual']; ?>.
                    <?php
                    $eligible = $warning['eligible_employees'] ?? [];
                    if (!empty($eligible)):
                        $escaped = array_map(fn($n) => htmlspecialchars($n, ENT_QUOTES, 'UTF-8'), $eligible);
                    ?>
                        Mögliche Mitarbeiter: <?php echo implode(', ', $escaped); ?>.
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<style>.plan-table th:first-child,
.plan-table td:first-child { white-space: nowrap; }
.plan-table tbody tr:hover { background-color: rgba(0, 0, 0, 0.05); }
.plan-table .shift-line { margin-bottom: 2px; }
.plan-table .shift-toggle { margin-left: 6px; cursor: pointer; }</style>
<table class="plan-table">
    <thead>
    <tr>
        <th rowspan="2">Mitarbeiter</th>
        <?php foreach ($dates as $date): ?>
            <?php $dt = new DateTime($date);
            $w = (int)$dt->format('N');
            $label = $weekdayNames[$w] . ' ' . $dt->format('d.m.');
            ?>
            <th colspan="2" style="text-align: center;"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></th>
        <?php endforeach; ?>
    </tr>
    <tr>
        <?php foreach ($dates as $date): ?>
            <th style="text-align: center;">Arbeitszeit</th>
            <th style="text-align: center;">Rolle</th>
        <?php endforeach; ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($employees as $employee): ?>
        <tr>
            <td>
                <?php echo htmlspecialchars($employee['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
            </td>
            <?php foreach ($dates as $date): ?>
                <?php
                $entries = $grid[$employee['id']][$date] ?? [];
                $times = [];
                $roles = [];
                foreach ($entries as $entry) {
                    $times[] = formatTimeRange($entry['time_from'] ?? '', $entry['time_to'] ?? '');
                    $roles[] = $entry['shortcode'] ?? '';
                }
                ?>
                <td><?php echo htmlspecialchars(implode(', ', $times), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(implode(', ', $roles), ENT_QUOTES, 'UTF-8'); ?></td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
    <tr>
        <th style="text-align: left;">Mitarbeiter</th>
        <?php foreach ($dates as $date): ?>
            <?php $count = $employeeCountByDate[$date] ?? 0; ?>
            <td colspan="2" style="text-align: center;"><?php echo (int)$count; ?></td>
        <?php endforeach; ?>
    </tr>
    <tr>
        <th style="text-align: left;">Rollen</th>
        <?php foreach ($dates as $date): ?>
            <?php
            $counts = $roleCountsByDate[$date] ?? [];
            $parts = [];
            foreach ($counts as $shortcode => $n) {
                $parts[] = htmlspecialchars($shortcode, ENT_QUOTES, 'UTF-8') . ': ' . $n;
            }
            ?>
            <td colspan="2" style="text-align: left;"><?php echo implode(', ', $parts); ?></td>
        <?php endforeach; ?>
    </tr>
    <tr>
        <th style="text-align: left;">Schichten</th>
        <?php foreach ($dates as $date): ?>
            <?php
            $shifts = $shiftsByDate[$date] ?? [];
            uasort($shifts, fn($a, $b) => ($a['time_from'] ?? '') <=> ($b['time_from'] ?? ''));
            ?>
            <td colspan="2" style="text-align: left;">
                <?php
                $shiftIndex = 0;
                foreach ($shifts as $info) {
                    $shiftIndex++;
                    $detailsId = 'shift-details-' . $date . '-' . $shiftIndex;
                    $timeRange = htmlspecialchars($info['time_range'], ENT_QUOTES, 'UTF-8');
                    $count = (int)($info['count'] ?? 0);
                    $names = array_unique($info['names'] ?? []);
                    $escapedNames = array_map(
                        fn($n) => htmlspecialchars($n, ENT_QUOTES, 'UTF-8'),
                        array_filter($names, fn($n) => $n !== '')
                    );
                    $namesText = implode(', ', $escapedNames);
                    ?>
                    <div class="shift-line">
                        <span><?php echo $timeRange; ?>: <?php echo $count; ?></span>
                        <?php if ($namesText !== ''): ?>
                            <button type="button"
                                    class="shift-toggle"
                                    data-target="<?php echo htmlspecialchars($detailsId, ENT_QUOTES, 'UTF-8'); ?>">
                                +
                            </button>
                            <div id="<?php echo htmlspecialchars($detailsId, ENT_QUOTES, 'UTF-8'); ?>"
                                 class="shift-details"
                                 style="display: none; margin-left: 1.5em;">
                                <?php echo $namesText; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php } ?>
            </td>
        <?php endforeach; ?>
    </tr>
    </tfoot>
</table>

<script>
document.addEventListener('click', function (event) {
    const btn = event.target.closest('.shift-toggle');
    if (!btn) {
        return;
    }
    const targetId = btn.getAttribute('data-target');
    if (!targetId) {
        return;
    }
    const details = document.getElementById(targetId);
    if (!details) {
        return;
    }
    const isHidden = details.style.display === '' || details.style.display === 'none';
    details.style.display = isHidden ? 'block' : 'none';
    btn.textContent = isHidden ? '−' : '+';
});
</script>

<p><a href="<?= BASE_PATH ?>/plan">Zurück zur Übersicht</a> | <a href="<?= BASE_PATH ?>/plan/create">Neuen Plan erstellen</a></p>

