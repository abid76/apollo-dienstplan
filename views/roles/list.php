<?php
/** @var array $roles */
?>

<h1>Rollen</h1>

<p><a href="<?= BASE_PATH ?>/roles/create">Neue Rolle anlegen</a></p>

<table>
    <thead>
    <tr>
        <th>#</th>
        <th>Bezeichnung</th>
        <th>Kürzel</th>
        <th>Aktionen</th>
    </tr>
    </thead>
    <tbody>
    <?php $rowNumber = 1; ?>
    <?php foreach ($roles as $role): ?>
        <tr>
            <td><?php echo $rowNumber++; ?></td>
            <td><?php echo htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($role['shortcode'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="actions">
                <a href="<?= BASE_PATH ?>/roles/edit?id=<?php echo (int)$role['id']; ?>">Bearbeiten</a>
                <form class="inline" method="post" action="<?= BASE_PATH ?>/roles/delete" onsubmit="return confirm('Rolle wirklich löschen?');">
                    <input type="hidden" name="id" value="<?php echo (int)$role['id']; ?>">
                    <button type="submit">Löschen</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

