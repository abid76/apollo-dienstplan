<?php
/** @var array $rules */

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

<h1>Regeln</h1>

<p><a href="/rules/create">Neue Regel anlegen</a></p>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Schicht</th>
        <th>Wochentag</th>
        <th>Uhrzeit</th>
        <th>Rolle</th>
        <th>Anzahl</th>
        <th>Aktionen</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rules as $rule): ?>
        <tr>
            <td><?php echo (int)$rule['id']; ?></td>
            <td><?php echo htmlspecialchars($rule['shift_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo $weekdayNames[(int)$rule['weekday']] ?? (int)$rule['weekday']; ?></td>
            <td><?php echo htmlspecialchars(substr($rule['time_from'] ?? '', 0, 5), ENT_QUOTES, 'UTF-8'); ?>-<?php echo htmlspecialchars(substr($rule['time_to'] ?? '', 0, 5), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($rule['role_name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($rule['shortcode'], ENT_QUOTES, 'UTF-8'); ?>)</td>
            <td><?php echo (int)$rule['required_count']; ?></td>
            <td class="actions">
                <a href="/rules/edit?id=<?php echo (int)$rule['id']; ?>">Bearbeiten</a>
                <form class="inline" method="post" action="/rules/delete" onsubmit="return confirm('Regel wirklich löschen?');">
                    <input type="hidden" name="id" value="<?php echo (int)$rule['id']; ?>">
                    <button type="submit">Löschen</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

