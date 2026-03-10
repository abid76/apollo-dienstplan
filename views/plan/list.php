<?php
/** @var array $plans */

function formatDateRange(string $startDate, int $weeks): string
{
    $start = new DateTime($startDate);
    $end = clone $start;
    $end->modify('+' . ($weeks - 1) . ' weeks');
    $end->modify('+6 days');
    return $start->format('d.m.Y') . ' – ' . $end->format('d.m.Y');
}
?>

<h1>Dienstpläne</h1>

<p><a href="<?= BASE_PATH ?>/plan/create">Neuen Dienstplan erstellen</a></p>

<?php if (empty($plans)): ?>
    <p>Noch keine Dienstpläne vorhanden.</p>
<?php else: ?>
<?php
    usort($plans, static function (array $a, array $b): int {
        return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
    });
?>
<table>
    <thead>
    <tr>
        <th>#</th>
        <th>Zeitraum</th>
        <th>Wochen</th>
        <th>Erstellt am</th>
        <th>Aktionen</th>
    </tr>
    </thead>
    <tbody>
    <?php $rowNumber = 1; ?>
    <?php foreach ($plans as $plan): ?>
        <tr>
            <td><?php echo $rowNumber++; ?></td>
            <td><?php echo htmlspecialchars(formatDateRange($plan['start_date'], (int)$plan['weeks']), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo (int)$plan['weeks']; ?></td>
            <td><?php echo htmlspecialchars((new DateTime($plan['created_at']))->format('d.m.Y H:i'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="actions">
                <a href="<?= BASE_PATH ?>/plan/show?id=<?php echo (int)$plan['id']; ?>">Anzeigen</a>
                <form class="inline" method="post" action="<?= BASE_PATH ?>/plan/delete" onsubmit="return confirm('Dienstplan wirklich löschen? Alle Einträge gehen verloren.');">
                    <input type="hidden" name="id" value="<?php echo (int)$plan['id']; ?>">
                    <button type="submit">Löschen</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
