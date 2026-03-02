<?php
/** @var string $action */
/** @var array|null $employee */
/** @var array $errors */
/** @var array $shifts */
/** @var array $roles */
require __DIR__ . '/../helpers/format_time.php';

$weekdayNames = [
    0 => 'Montag',
    1 => 'Dienstag',
    2 => 'Mittwoch',
    3 => 'Donnerstag',
    4 => 'Freitag',
    5 => 'Samstag',
    6 => 'Sonntag',
];

$weekdayShortNames = [
    0 => 'Mo',
    1 => 'Di',
    2 => 'Mi',
    3 => 'Do',
    4 => 'Fr',
    5 => 'Sa',
    6 => 'So',
];

$id = $employee['id'] ?? null;
$name = $employee['name'] ?? '';
$maxShiftsPerWeek = $employee['max_shifts_per_week'] ?? 5;
$allowedWeekdays = isset($employee['allowed_weekdays']) ? array_map('intval', (array)$employee['allowed_weekdays']) : [];
$allowedShifts = [];
$allowedShiftMaxPerWeek = [];
if (isset($employee['allowed_shifts']) && is_array($employee['allowed_shifts'])) {
    foreach ($employee['allowed_shifts'] as $a) {
        $sid = (int)(is_array($a) ? ($a['shift_id'] ?? 0) : $a);
        $allowedShifts[] = $sid;
        if (is_array($a) && array_key_exists('max_per_week', $a) && $a['max_per_week'] !== null && $a['max_per_week'] !== '') {
            $allowedShiftMaxPerWeek[$sid] = (int)$a['max_per_week'];
        }
    }
}
$allowedWeekdayShifts = isset($employee['allowed_weekday_shifts']) ? (array)$employee['allowed_weekday_shifts'] : [];
if (isset($employee['allowed_weekday_shift']) && is_array($employee['allowed_weekday_shift'])) {
    $allowedWeekdayShifts = [];
    foreach ($employee['allowed_weekday_shift'] as $wd => $ids) {
        $allowedWeekdayShifts[(int)$wd] = array_map('intval', (array)$ids);
    }
}
$roleIds = isset($employee['roles']) ? array_map('intval', (array)$employee['roles']) : [];
?>

<h1><?php echo $id ? 'Mitarbeiter bearbeiten' : 'Neuen Mitarbeiter anlegen'; ?></h1>

<?php if ($errors): ?>
    <div class="message message-error">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" id="employee-form">
    <div>
        <label for="name">Name</label><br>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div>
        <label for="max_shifts_per_week">Anz. Schichten/Woche</label><br>
        <input type="number" min="0" id="max_shifts_per_week" name="max_shifts_per_week" value="<?php echo htmlspecialchars((string)$maxShiftsPerWeek, ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <fieldset>
        <legend>Zulässige Wochentage</legend>
        <?php foreach ($weekdayNames as $value => $label): ?>
            <label>
                <input type="checkbox" name="allowed_weekdays[]" value="<?php echo $value; ?>" <?php echo in_array($value, $allowedWeekdays, true) ? 'checked' : ''; ?>>
                <?php echo $label; ?>
            </label><br>
        <?php endforeach; ?>
    </fieldset>

    <fieldset>
        <legend>Zulässige Schichten</legend>
        <?php foreach ($shifts as $shift): ?>
            <?php
            // Alle Wochentage für die Schicht als Kürzel ausgeben
            $weekdays = $shift['weekdays'] ?? [isset($shift['weekday']) ? (int)$shift['weekday'] : 0];
            $weekdayLabels = [];
            foreach ((array)$weekdays as $day) {
                $day = (int)$day;
                if (isset($weekdayShortNames[$day])) {
                    $weekdayLabels[] = $weekdayShortNames[$day];
                }
            }
            ?>
            <label>
                <input type="checkbox" name="allowed_shifts[]" value="<?php echo (int)$shift['id']; ?>" <?php echo in_array((int)$shift['id'], $allowedShifts, true) ? 'checked' : ''; ?>>
                <?php echo htmlspecialchars($shift['name'], ENT_QUOTES, 'UTF-8'); ?>
                (<?php echo htmlspecialchars(implode(', ', $weekdayLabels), ENT_QUOTES, 'UTF-8'); ?>,
                <?php echo htmlspecialchars(formatTimeRange($shift['time_from'] ?? '', $shift['time_to'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)
                <span class="hint">Max. pro Woche:</span>
                <select name="allowed_shift_max_per_week[<?php echo (int)$shift['id']; ?>]" style="width: 4.5em;">
                    <option value=""<?php echo !isset($allowedShiftMaxPerWeek[(int)$shift['id']]) ? ' selected' : ''; ?>>—</option>
                    <?php for ($i = 1; $i <= 7; $i++): ?>
                        <option value="<?php echo $i; ?>"<?php echo (isset($allowedShiftMaxPerWeek[(int)$shift['id']]) && (int)$allowedShiftMaxPerWeek[(int)$shift['id']] === $i) ? ' selected' : ''; ?>>
                            <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </label><br>
        <?php endforeach; ?>
    </fieldset>

    <fieldset>
        <legend>Einschränkung Wochentage/Schichten</legend>
        <p class="hint">Optional: Pro Wochentag können Sie die zulässigen Schichten einschränken. Keine Auswahl = an diesem Tag sind alle oben gewählten Schichten erlaubt.</p>
        <?php
        $showRestriction = !empty($allowedWeekdays) && !empty($allowedShifts);
        if ($showRestriction):
            foreach ($weekdayNames as $wdValue => $wdLabel):
                if (!in_array($wdValue, $allowedWeekdays, true)) {
                    continue;
                }
                $shiftsForDay = $allowedWeekdayShifts[$wdValue] ?? [];
        ?>
        <div style="margin-bottom: 0.75em;">
            <strong><?php echo htmlspecialchars($wdLabel, ENT_QUOTES, 'UTF-8'); ?></strong>:<br>
            <?php foreach ($shifts as $shift): ?>
                <?php if (!in_array((int)$shift['id'], $allowedShifts, true)) continue; ?>
                <?php
                $weekdays = $shift['weekdays'] ?? [isset($shift['weekday']) ? (int)$shift['weekday'] : 0];
                $weekdays = array_map('intval', (array)$weekdays);
                if (!in_array($wdValue, $weekdays, true)) continue;
                $weekdayLabels = [];
                foreach ($weekdays as $day) {
                    if (isset($weekdayShortNames[$day])) {
                        $weekdayLabels[] = $weekdayShortNames[$day];
                    }
                }
                ?>
                <label style="margin-left: 1em;">
                    <input type="checkbox" name="allowed_weekday_shift[<?php echo (int)$wdValue; ?>][]" value="<?php echo (int)$shift['id']; ?>" <?php echo in_array((int)$shift['id'], $shiftsForDay, true) ? 'checked' : ''; ?>>
                    <?php echo htmlspecialchars($shift['name'], ENT_QUOTES, 'UTF-8'); ?>
                    (<?php echo htmlspecialchars(implode(', ', $weekdayLabels), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(formatTimeRange($shift['time_from'] ?? '', $shift['time_to'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)
                </label><br>
            <?php endforeach; ?>
        </div>
        <?php
            endforeach;
        else: ?>
        <p class="hint">Bitte zuerst „Zulässige Wochentage“ und „Zulässige Schichten“ wählen.</p>
        <?php endif; ?>
    </fieldset>

    <fieldset>
        <legend>Rollen</legend>
        <?php foreach ($roles as $role): ?>
            <label>
                <input type="checkbox" name="roles[]" value="<?php echo (int)$role['id']; ?>" <?php echo in_array((int)$role['id'], $roleIds, true) ? 'checked' : ''; ?>>
                <?php echo htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>
                (<?php echo htmlspecialchars($role['shortcode'], ENT_QUOTES, 'UTF-8'); ?>)
            </label><br>
        <?php endforeach; ?>
    </fieldset>

    <div style="margin-top: 10px;">
        <button type="submit">Speichern</button>
        <a href="<?= BASE_PATH ?>/employees">Abbrechen</a>
    </div>
</form>
