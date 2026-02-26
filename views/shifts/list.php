<?php
/** @var array $shifts */

$weekdayNames = [
    0 => 'Montag',
    1 => 'Dienstag',
    2 => 'Mittwoch',
    3 => 'Donnerstag',
    4 => 'Freitag',
    5 => 'Samstag',
    6 => 'Sonntag',
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
        <th>Von</th>
        <th>Bis</th>
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
                    if (isset($weekdayNames[$day])) {
                        $labels[] = $weekdayNames[$day];
                    }
                }
                echo htmlspecialchars(implode(', ', $labels), ENT_QUOTES, 'UTF-8');
                ?>
            </td>
            <td><?php echo htmlspecialchars(substr($shift['time_from'] ?? '', 0, 5), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(substr($shift['time_to'] ?? '', 0, 5), ENT_QUOTES, 'UTF-8'); ?></td>
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

