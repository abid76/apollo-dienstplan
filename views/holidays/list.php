<?php
/** @var array $holidays */
?>

<h1>Urlaub / Abwesenheiten</h1>

<p><a href="<?= BASE_PATH ?>/holidays/create">Neuen Eintrag anlegen</a></p>

<table>
    <thead>
    <tr>
        <th>#</th>
        <th>Mitarbeiter</th>
        <th>Von</th>
        <th>Bis</th>
        <th>Aktionen</th>
    </tr>
    </thead>
    <tbody>
    <?php $rowNumber = 1; ?>
    <?php foreach ($holidays as $holiday): ?>
        <?php
        $from = '';
        if (!empty($holiday['date_from'])) {
            $dtFrom = new DateTime($holiday['date_from']);
            $from = $dtFrom->format('d.m.Y');
        }
        $to = '';
        if (!empty($holiday['date_to'])) {
            $dtTo = new DateTime($holiday['date_to']);
            $to = $dtTo->format('d.m.Y');
        }
        ?>
        <tr>
            <td><?php echo $rowNumber++; ?></td>
            <td><?php echo htmlspecialchars($holiday['employee_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="actions">
                <a href="<?= BASE_PATH ?>/holidays/edit?id=<?php echo (int)$holiday['id']; ?>">Bearbeiten</a>
                <form class="inline" method="post" action="<?= BASE_PATH ?>/holidays/delete" onsubmit="return confirm('Eintrag wirklich löschen?');">
                    <input type="hidden" name="id" value="<?php echo (int)$holiday['id']; ?>">
                    <button type="submit">Löschen</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

