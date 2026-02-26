<?php
/** @var string $action */
/** @var array|null $employee */
/** @var array $errors */
/** @var array $shifts */
/** @var array $roles */

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
$firstName = $employee['first_name'] ?? '';
$lastName = $employee['last_name'] ?? '';
$maxShiftsPerWeek = $employee['max_shifts_per_week'] ?? 5;
$allowedWeekdays = isset($employee['allowed_weekdays']) ? (array)$employee['allowed_weekdays'] : [];
$allowedShifts = isset($employee['allowed_shifts']) ? (array)$employee['allowed_shifts'] : [];
$roleIds = isset($employee['roles']) ? (array)$employee['roles'] : [];
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
        <label for="first_name">Vorname</label><br>
        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div>
        <label for="last_name">Nachname</label><br>
        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8'); ?>">
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
                <?php echo htmlspecialchars(substr($shift['time_from'] ?? '', 0, 5), ENT_QUOTES, 'UTF-8'); ?>-<?php echo htmlspecialchars(substr($shift['time_to'] ?? '', 0, 5), ENT_QUOTES, 'UTF-8'); ?>)
            </label><br>
        <?php endforeach; ?>
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
        <a href="/employees">Abbrechen</a>
    </div>
</form>
<script>
document.getElementById('employee-form').addEventListener('submit', function(e) {
    var weekdays = document.querySelectorAll('input[name="allowed_weekdays[]"]:checked');
    var shifts = document.querySelectorAll('input[name="allowed_shifts[]"]:checked');
    var roles = document.querySelectorAll('input[name="roles[]"]:checked');
    var msg = [];
    if (weekdays.length === 0) msg.push('Mindestens ein Wochentag muss ausgewählt werden.');
    if (shifts.length === 0) msg.push('Mindestens eine Schicht muss ausgewählt werden.');
    if (roles.length === 0) msg.push('Mindestens eine Rolle muss ausgewählt werden.');
    if (msg.length) {
        e.preventDefault();
        alert(msg.join('\n'));
    }
});
</script>

