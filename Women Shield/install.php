<?php

declare(strict_types=1);

$config = require __DIR__ . '/config/app.php';
$errors = [];
$installed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mysqli = @new mysqli(
        $config['db']['host'],
        $config['db']['user'],
        $config['db']['pass'],
        '',
        (int) $config['db']['port']
    );

    if ($mysqli->connect_errno) {
        $errors[] = 'MySQL connection failed: ' . $mysqli->connect_error;
    } else {
        $sql = file_get_contents(__DIR__ . '/database/schema.sql');

        if ($sql === false) {
            $errors[] = 'Could not read database/schema.sql';
        } elseif (!$mysqli->multi_query($sql)) {
            $errors[] = 'Schema import failed: ' . $mysqli->error;
        } else {
            do {
                if ($result = $mysqli->store_result()) {
                    $result->free();
                }
            } while ($mysqli->more_results() && $mysqli->next_result());

            if ($mysqli->errno) {
                $errors[] = 'Schema import completed with an error: ' . $mysqli->error;
            } else {
                $installed = true;
            }
        }

        $mysqli->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Women Shield</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars('assets/css/style.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<main class="container main-shell">
    <section class="auth-wrap">
        <div class="panel auth-panel wide">
            <span class="eyebrow">Installer</span>
            <h1>Women Shield</h1>
            <p>This installer imports the MySQL schema using the database settings from `config/app.php` or `config/local.php`.</p>

            <?php if ($installed): ?>
                <div class="flash flash-success">Schema installed successfully. You can now <a href="register.php">create a user account</a> or <a href="login.php?mode=admin">sign in as admin</a>.</div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="inline-errors">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="stack">
                <div class="list-card">
                    <div>
                        <strong>Database</strong>
                        <p><?= htmlspecialchars($config['db']['name'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <span class="badge badge-caution"><?= htmlspecialchars($config['db']['host'] . ':' . $config['db']['port'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="list-card">
                    <div>
                        <strong>Seeded Admin Account</strong>
                        <p>`admin` / `Admin@123`</p>
                    </div>
                    <span class="badge badge-warn">Local Demo</span>
                </div>
            </div>

            <form method="post" class="form-grid" style="margin-top: 1rem;">
                <button class="button button-primary" type="submit">Run Database Install</button>
                <a class="button button-secondary" href="index.php">Back to Home</a>
            </form>
        </div>
    </section>
</main>
</body>
</html>
