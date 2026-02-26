<?php
/** @var array $plan */
/** @var array $employees */
/** @var array $dates */
/** @var array $grid */

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
?>

<h1>Dienstplan #<?php echo (int)$plan['id']; ?></h1>

<p>
    Startdatum: <?php echo htmlspecialchars($plan['start_date'], ENT_QUOTES, 'UTF-8'); ?><br>
    Wochen: <?php echo (int)$plan['weeks']; ?>
</p>

<table>
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
                <?php echo htmlspecialchars(trim($employee['last_name'] . ', ' . $employee['first_name'], ', '), ENT_QUOTES, 'UTF-8'); ?>
            </td>
            <?php foreach ($dates as $date): ?>
                <?php
                $entries = $grid[$employee['id']][$date] ?? [];
                $times = [];
                $roles = [];
                foreach ($entries as $entry) {
                    $times[] = substr($entry['time_from'] ?? '', 0, 5) . '-' . substr($entry['time_to'] ?? '', 0, 5);
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
        <th style="text-align: left;">Rollen</th>
        <?php foreach ($dates as $date): ?>
            <?php
            $counts = $roleCountsByDate[$date] ?? [];
            $parts = [];
            foreach ($counts as $shortcode => $n) {
                $parts[] = htmlspecialchars($shortcode, ENT_QUOTES, 'UTF-8') . ': ' . $n;
            }
            ?>
            <td colspan="2" style="text-align: center;"><?php echo implode(', ', $parts); ?></td>
        <?php endforeach; ?>
    </tr>
    </tfoot>
</table>

<p><a href="/plan">Neuen Plan erstellen</a></p>

