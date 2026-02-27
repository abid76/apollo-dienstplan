<?php
/** @var array $errors */

// Startdatum: Standard = nächster Montag
$defaultStart = new DateTime('today');
$defaultStart->setISODate((int) $defaultStart->format('o'), (int) $defaultStart->format('W'), 1);
if ($defaultStart < new DateTime('today')) {
    $defaultStart->modify('+1 week');
}
$defaultStartStr = $defaultStart->format('Y-m-d');
?>

<h1>Dienstplan erstellen</h1>

<?php if ($errors): ?>
    <div class="message message-error">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= BASE_PATH ?>/plan/generate">
    <div>
        <label for="start_date">Startdatum (Montag empfohlen)</label><br>
        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($defaultStartStr, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>
    <div>
        <label for="weeks">Anzahl Wochen</label><br>
        <input type="number" id="weeks" name="weeks" min="1" value="1" required>
    </div>
    <div style="margin-top: 10px;">
        <button type="submit">Plan erzeugen</button>
        <a href="<?= BASE_PATH ?>/plan">Abbrechen</a>
    </div>
</form>

