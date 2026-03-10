<?php
/** @var string $action */
/** @var array|null $absence */
/** @var array $employees */
/** @var array $shifts */
/** @var array $errors */

$id = $absence['id'] ?? null;
$employeeId = isset($absence['employee_id']) ? (int)$absence['employee_id'] : 0;
$date = $absence['date'] ?? '';
$shiftId = isset($absence['shift_id']) && $absence['shift_id'] !== '' ? (int)$absence['shift_id'] : 0;
?>

<h1><?php echo $id ? 'Ausgleichstag bearbeiten' : 'Neuen Ausgleichstag anlegen'; ?></h1>

<?php if (!empty($errors)): ?>
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
        <label for="employee_id">Mitarbeiter</label><br>
        <select id="employee_id" name="employee_id">
            <option value="">Bitte wählen …</option>
            <?php foreach ($employees as $employee): ?>
                <option value="<?php echo (int)$employee['id']; ?>"<?php echo $employeeId === (int)$employee['id'] ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($employee['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label for="date">Datum</label><br>
        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div>
        <label for="shift_id">Schicht (optional)</label><br>
        <select id="shift_id" name="shift_id">
            <option value="">—</option>
            <?php foreach ($shifts as $shift): ?>
                <?php
                $labelParts = [];
                if (!empty($shift['name'])) {
                    $labelParts[] = (string)$shift['name'];
                }
                if (!empty($shift['time_from']) || !empty($shift['time_to'])) {
                    $from = trim((string)($shift['time_from'] ?? ''));
                    $to = trim((string)($shift['time_to'] ?? ''));
                    if ($from !== '' || $to !== '') {
                        $labelParts[] = trim($from . ' - ' . $to);
                    }
                }
                $label = implode(' ', $labelParts);
                ?>
                <option value="<?php echo (int)$shift['id']; ?>"<?php echo $shiftId === (int)$shift['id'] ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="margin-top: 10px;">
        <button type="submit">Speichern</button>
        <a href="<?= BASE_PATH ?>/absences">Abbrechen</a>
    </div>
</form>
