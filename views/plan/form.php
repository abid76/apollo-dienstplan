<?php
/** @var array $errors */
/** @var string|null $submitted_start_date */
/** @var int|null $submitted_weeks */

// Startdatum: Standard = nächster Montag (nur wenn kein fehlgeschlagener Submit)
$defaultStart = new DateTime('today');
$defaultStart->setISODate((int) $defaultStart->format('o'), (int) $defaultStart->format('W'), 1);
if ($defaultStart < new DateTime('today')) {
    $defaultStart->modify('+1 week');
}
$defaultStartStr = $defaultStart->format('Y-m-d');
$startDateValue = $submitted_start_date ?? $defaultStartStr;
$serverIsLocalhost = isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] === 'localhost');
$weeksValue = $submitted_weeks ?? ($serverIsLocalhost ? 1 : 4);
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
        <label for="start_date">Startdatum (muss Montag sein)</label><br>
        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDateValue, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>
    <div>
        <label for="weeks">Anzahl Wochen</label><br>
        <input type="number" id="weeks" name="weeks" min="1" value="<?php echo (int) $weeksValue; ?>" required>
    </div>
    <div style="margin-top: 10px;">
        <button type="submit">Plan erzeugen</button>
        <a href="<?= BASE_PATH ?>/plan">Abbrechen</a>
    </div>
</form>
<script>
(function() {
    var input = document.getElementById('start_date');
    var form = input && input.closest('form');
    if (!form) return;
    function isMonday(dateStr) {
        var d = new Date(dateStr + 'T12:00:00');
        return d.getDay() === 1;
    }
    function showError(msg) {
        var existing = form.querySelector('.start_date-error');
        if (existing) existing.remove();
        if (!msg) return;
        var div = document.createElement('div');
        div.className = 'message message-error start_date-error';
        div.style.marginTop = '6px';
        div.textContent = msg;
        input.closest('div').appendChild(div);
    }
    input.addEventListener('change', function() {
        var val = input.value;
        showError(val && !isMonday(val) ? 'Das Startdatum muss ein Montag sein.' : '');
    });
    form.addEventListener('submit', function(e) {
        var val = input.value;
        if (val && !isMonday(val)) {
            e.preventDefault();
            showError('Das Startdatum muss ein Montag sein.');
            input.focus();
        }
    });
})();
</script>

