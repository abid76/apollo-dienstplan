<?php
/** @var array $shifts */
require __DIR__ . '/../helpers/format_time.php';

$weekdayShort = [
    0 => 'Mo',
    1 => 'Di',
    2 => 'Mi',
    3 => 'Do',
    4 => 'Fr',
    5 => 'Sa',
    6 => 'So',
];
?>

<h1>Schichten</h1>

<p><a href="/shifts/create">Neue Schicht anlegen</a></p>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Wochentag</th>
        <th>Uhrzeit</th>
        <th>Aktionen</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($shifts as $shift): ?>
        <tr>
            <td><?php echo (int)$shift['id']; ?></td>
            <td><?php echo htmlspecialchars($shift['name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
                <?php
                $weekdays = $shift['weekdays'] ?? [isset($shift['weekday']) ? (int)$shift['weekday'] : 0];
                $labels = [];
                foreach ($weekdays as $day) {
                    $day = (int)$day;
                    if (isset($weekdayShort[$day])) {
                        $labels[] = $weekdayShort[$day];
                    }
                }
                echo htmlspecialchars(implode(', ', $labels), ENT_QUOTES, 'UTF-8');
                ?>
            </td>
            <td><?php echo htmlspecialchars(formatTimeRange($shift['time_from'] ?? '', $shift['time_to'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="actions">
                <a href="/shifts/edit?id=<?php echo (int)$shift['id']; ?>">Bearbeiten</a>
                <form class="inline" method="post" action="/shifts/delete" onsubmit="return confirm('Schicht wirklich löschen?');">
                    <input type="hidden" name="id" value="<?php echo (int)$shift['id']; ?>">
                    <button type="submit">Löschen</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

