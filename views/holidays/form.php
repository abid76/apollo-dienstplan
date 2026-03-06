<?php
/** @var string $action */
/** @var array|null $holiday */
/** @var array $employees */
/** @var array $errors */

$id = $holiday['id'] ?? null;
$employeeId = isset($holiday['employee_id']) ? (int)$holiday['employee_id'] : 0;
$dateFrom = $holiday['date_from'] ?? '';
$dateTo = $holiday['date_to'] ?? '';
?>

<h1><?php echo $id ? 'Urlaub / Abwesenheit bearbeiten' : 'Neuen Urlaub / Abwesenheit anlegen'; ?></h1>

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
        <label for="date_from">Von</label><br>
        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div>
        <label for="date_to">Bis</label><br>
        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div style="margin-top: 10px;">
        <button type="submit">Speichern</button>
        <a href="<?= BASE_PATH ?>/holidays">Abbrechen</a>
    </div>
</form>

