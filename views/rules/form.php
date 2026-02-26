<?php
/** @var string $action */
/** @var array|null $rule */
/** @var array $errors */
/** @var array $shifts */
/** @var array $roles */

$weekdayShort = [
    0 => 'Mo',
    1 => 'Di',
    2 => 'Mi',
    3 => 'Do',
    4 => 'Fr',
    5 => 'Sa',
    6 => 'So',
];

$id = $rule['id'] ?? null;
$shiftId = isset($rule['shift_id']) ? (int)$rule['shift_id'] : null;
$roleId = isset($rule['role_id']) ? (int)$rule['role_id'] : null;
$requiredCount = $rule['required_count'] ?? 1;
?>

<h1><?php echo $id ? 'Regel bearbeiten' : 'Neue Regel anlegen'; ?></h1>

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
        <label for="shift_id">Schicht</label><br>
        <select id="shift_id" name="shift_id">
            <option value="">Bitte wählen</option>
            <?php foreach ($shifts as $shift): ?>
                <?php
                $weekdays = $shift['weekdays'] ?? [isset($shift['weekday']) ? (int) $shift['weekday'] : 0];
                $weekdayLabels = [];
                foreach ((array) $weekdays as $day) {
                    $day = (int) $day;
                    if (isset($weekdayShort[$day])) {
                        $weekdayLabels[] = $weekdayShort[$day];
                    }
                }
                $weekdayStr = implode(', ', $weekdayLabels);
                ?>
                <option value="<?php echo (int)$shift['id']; ?>" <?php echo ((int)$shift['id'] === $shiftId) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($shift['name'], ENT_QUOTES, 'UTF-8'); ?>
                    (<?php echo htmlspecialchars($weekdayStr, ENT_QUOTES, 'UTF-8'); ?>,
                    <?php echo htmlspecialchars(substr($shift['time_from'] ?? '', 0, 5), ENT_QUOTES, 'UTF-8'); ?>-<?php echo htmlspecialchars(substr($shift['time_to'] ?? '', 0, 5), ENT_QUOTES, 'UTF-8'); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label for="role_id">Rolle</label><br>
        <select id="role_id" name="role_id">
            <option value="">Bitte wählen</option>
            <?php foreach ($roles as $role): ?>
                <option value="<?php echo (int)$role['id']; ?>" <?php echo ((int)$role['id'] === $roleId) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>
                    (<?php echo htmlspecialchars($role['shortcode'], ENT_QUOTES, 'UTF-8'); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label for="required_count">Anzahl</label><br>
        <input type="number" min="1" id="required_count" name="required_count" value="<?php echo htmlspecialchars((string)$requiredCount, ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div style="margin-top: 10px;">
        <button type="submit">Speichern</button>
        <a href="/rules">Abbrechen</a>
    </div>
</form>

