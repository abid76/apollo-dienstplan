<?php
/** @var string $action */
/** @var array|null $shift */
/** @var array $errors */

$weekdayNames = [
    0 => 'Montag',
    1 => 'Dienstag',
    2 => 'Mittwoch',
    3 => 'Donnerstag',
    4 => 'Freitag',
    5 => 'Samstag',
    6 => 'Sonntag',
];

$id = $shift['id'] ?? null;
$name = $shift['name'] ?? '';

// Ausgewählte Wochentage ermitteln (Mehrfachauswahl möglich)
$weekdays = $shift['weekdays'] ?? null;
if ($weekdays === null && isset($shift['weekday'])) {
    // Fallback für bestehende Daten, die nur einen Wochentag enthalten
    $weekdays = [(int)$shift['weekday']];
}
$weekdays = array_map('intval', (array)$weekdays);

$timeFromRaw = $shift['time_from'] ?? '';
$timeToRaw = $shift['time_to'] ?? '';

// Für die Formularanzeige nur die Stunde (0–23) verwenden.
if ($timeFromRaw !== '' && strpos((string)$timeFromRaw, ':') !== false) {
    $timeFrom = (int)substr((string)$timeFromRaw, 0, 2);
} else {
    $timeFrom = $timeFromRaw;
}

if ($timeToRaw !== '' && strpos((string)$timeToRaw, ':') !== false) {
    $timeTo = (int)substr((string)$timeToRaw, 0, 2);
} else {
    $timeTo = $timeToRaw;
}
?>

<h1><?php echo $id ? 'Schicht bearbeiten' : 'Neue Schicht anlegen'; ?></h1>

<?php if ($errors): ?>
    <div class="message message-error">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>">
    <div>
        <label for="name">Name</label><br>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div>
        <span>Wochentage</span><br>
        <?php foreach ($weekdayNames as $value => $label): ?>
            <label>
                <input
                    type="checkbox"
                    name="weekdays[]"
                    value="<?php echo $value; ?>"
                    <?php echo in_array($value, $weekdays, true) ? 'checked' : ''; ?>
                >
                <?php echo $label; ?>
            </label><br>
        <?php endforeach; ?>
    </div>
    <div>
        <label for="time_from">Uhrzeit von (Stunde 0–23)</label><br>
        <input
            type="number"
            id="time_from"
            name="time_from"
            min="0"
            max="23"
            step="1"
            value="<?php echo htmlspecialchars((string)$timeFrom, ENT_QUOTES, 'UTF-8'); ?>"
        >
    </div>
    <div>
        <label for="time_to">Uhrzeit bis (Stunde 0–23)</label><br>
        <input
            type="number"
            id="time_to"
            name="time_to"
            min="0"
            max="23"
            step="1"
            value="<?php echo htmlspecialchars((string)$timeTo, ENT_QUOTES, 'UTF-8'); ?>"
        >
    </div>
    <div style="margin-top: 10px;">
        <button type="submit">Speichern</button>
        <a href="<?= BASE_PATH ?>/shifts">Abbrechen</a>
    </div>
</form>

