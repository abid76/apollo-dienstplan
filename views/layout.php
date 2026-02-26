<?php
/** @var string $content */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Dienstplan</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        header { background: #333; color: #fff; padding: 10px 20px; }
        nav a { color: #fff; margin-right: 15px; text-decoration: none; }
        nav a:hover { text-decoration: underline; }
        main { padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f0f0f0; }
        .actions a { margin-right: 8px; }
        form.inline { display: inline; }
        .message { padding: 8px 10px; margin-bottom: 10px; border-radius: 4px; }
        .message-success { background: #e0ffe0; border: 1px solid #80c080; }
        .message-error { background: #ffe0e0; border: 1px solid #c08080; }
    </style>
</head>
<body>
    <header>
        <nav>
            <a href="/">Übersicht</a>
            <a href="/shifts">Schichten</a>
            <a href="/roles">Rollen</a>
            <a href="/employees">Mitarbeiter</a>
            <a href="/rules">Regeln</a>
            <a href="/plan">Dienstplan</a>
        </nav>
    </header>
    <main>
        <?php echo $content ?? ''; ?>
    </main>
</body>
</html>

