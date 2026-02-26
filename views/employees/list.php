<?php
/** @var array $employees */

$weekdayShort = [0 => 'Mo', 1 => 'Di', 2 => 'Mi', 3 => 'Do', 4 => 'Fr', 5 => 'Sa', 6 => 'So'];
?>

<h1>Mitarbeiter</h1>

<p><a href="/employees/create">Neuen Mitarbeiter anlegen</a></p>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Vorname</th>
        <th>Nachname</th>
        <th>Rollen</th>
        <th>Zulässige Wochentage</th>
        <th>Anz. Schichten/Woche</th>
        <th>Aktionen</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($employees as $employee): ?>
        <?php
        $days = $employee['allowed_weekdays'] ?? [];
        $labels = [];
        foreach ($days as $d) {
            $d = (int)$d;
            if (isset($weekdayShort[$d])) {
                $labels[] = $weekdayShort[$d];
            }
        }
        ?>
        <tr>
            <td><?php echo (int)$employee['id']; ?></td>
            <td><?php echo htmlspecialchars($employee['first_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($employee['last_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(implode(', ', $employee['role_shortcodes'] ?? []), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(implode(', ', $labels), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo (int)$employee['max_shifts_per_week']; ?></td>
            <td class="actions">
                <a href="/employees/edit?id=<?php echo (int)$employee['id']; ?>">Bearbeiten</a>
                <form class="inline" method="post" action="/employees/delete" onsubmit="return confirm('Mitarbeiter wirklich löschen?');">
                    <input type="hidden" name="id" value="<?php echo (int)$employee['id']; ?>">
                    <button type="submit">Löschen</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

