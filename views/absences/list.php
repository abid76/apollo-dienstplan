<?php
/** @var array $absences */

$formatTime = static function (?string $time): string {
    $time = trim((string)$time);
    if ($time === '') {
        return '';
    }

    $dt = \DateTime::createFromFormat('H:i:s', $time) ?: \DateTime::createFromFormat('H:i', $time) ?: null;
    if (!$dt) {
        return $time;
    }

    $h = (int)$dt->format('G');
    $m = (int)$dt->format('i');
    return $m === 0 ? (string)$h : sprintf('%d:%02d', $h, $m);
};

$formatRange = static function (?string $from, ?string $to) use ($formatTime): string {
    $a = $formatTime($from);
    $b = $formatTime($to);
    if ($a === '' && $b === '') {
        return '';
    }
    return trim($a . '-' . $b . ' Uhr');
};
?>

<h1>Ausgleichstage</h1>

<p><a href="<?= BASE_PATH ?>/absences/create">Neuen Ausgleichstag anlegen</a></p>

<table>
    <thead>
    <tr>
        <th>#</th>
        <th>Mitarbeiter</th>
        <th>Datum</th>
        <th>Schicht</th>
        <th>Aktionen</th>
    </tr>
    </thead>
    <tbody>
    <?php $rowNumber = 1; ?>
    <?php foreach ($absences as $absence): ?>
        <?php
        $date = '';
        $dateWithWeekday = '';
        if (!empty($absence['date'])) {
            $dt = new DateTime($absence['date']);
            $date = $dt->format('d.m.Y');

            $weekdayNames = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
            $weekday = (int)$dt->format('N'); // 1=Mo ... 7=So
            $weekdayLabel = $weekdayNames[$weekday] ?? '';
            $dateWithWeekday = $weekdayLabel !== '' ? ($weekdayLabel . ', ' . $date) : $date;
        }
        ?>
        <tr>
            <td><?php echo $rowNumber++; ?></td>
            <td><?php echo htmlspecialchars($absence['employee_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($dateWithWeekday, ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
                <?php
                if (!empty($absence['shift_id'])) {
                    $label = trim((string)($absence['shift_name'] ?? ''));
                    if ($label === '') {
                        $label = 'Schicht #' . (int)$absence['shift_id'];
                    }
                    $range = $formatRange($absence['shift_time_from'] ?? null, $absence['shift_time_to'] ?? null);
                    if ($range !== '') {
                        $label .= ' (' . $range . ')';
                    }
                    echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
                } else {
                    echo '—';
                }
                ?>
            </td>
            <td class="actions">
                <a href="<?= BASE_PATH ?>/absences/edit?id=<?php echo (int)$absence['id']; ?>">Bearbeiten</a>
                <form class="inline" method="post" action="<?= BASE_PATH ?>/absences/delete" onsubmit="return confirm('Eintrag wirklich löschen?');">
                    <input type="hidden" name="id" value="<?php echo (int)$absence['id']; ?>">
                    <button type="submit">Löschen</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

