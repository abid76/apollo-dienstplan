<?php
/** @var string $action */
/** @var array|null $role */
/** @var array $errors */

$id = $role['id'] ?? null;
$name = $role['name'] ?? '';
$shortcode = $role['shortcode'] ?? '';
?>

<h1><?php echo $id ? 'Rolle bearbeiten' : 'Neue Rolle anlegen'; ?></h1>

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
        <label for="name">Bezeichnung</label><br>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div>
        <label for="shortcode">Kürzel</label><br>
        <input type="text" id="shortcode" name="shortcode" value="<?php echo htmlspecialchars($shortcode, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div style="margin-top: 10px;">
        <button type="submit">Speichern</button>
        <a href="<?= BASE_PATH ?>/roles">Abbrechen</a>
    </div>
</form>

