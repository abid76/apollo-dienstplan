<?php
/** @var array $errors */
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

<form method="post" action="/plan/generate">
    <div>
        <label for="start_date">Startdatum (Montag empfohlen)</label><br>
        <input type="date" id="start_date" name="start_date" required>
    </div>
    <div>
        <label for="weeks">Anzahl Wochen</label><br>
        <input type="number" id="weeks" name="weeks" min="1" value="4" required>
    </div>
    <div style="margin-top: 10px;">
        <button type="submit">Plan erzeugen</button>
    </div>
</form>

