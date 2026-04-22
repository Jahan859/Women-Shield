<?php

declare(strict_types=1);

function render_header(string $title, array $options = []): void
{
    $account = current_account();
    $user = current_user();
    $admin = is_admin();
    $pageClass = $options['page_class'] ?? '';
    $description = $options['description'] ?? 'AI-supported Women Shield safety dashboard.';
    $success = flash_message('success');
    $error = flash_message('error');
    $isAdminLoginPage = is_active_route('login.php') && (($_GET['mode'] ?? '') === 'admin');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> | <?= e(app_config('app_name')) ?></title>
        <meta name="description" content="<?= e($description) ?>">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
    </head>
    <body class="<?= e($pageClass) ?>">
        <div class="ambient ambient-one"></div>
        <div class="ambient ambient-two"></div>
        <header class="site-header">
            <div class="container nav-shell">
                <a class="brand" href="<?= e(route_url('index.php')) ?>">
                    <span class="brand-mark" aria-hidden="true">
                        <img src="<?= e(asset_url('assets/images/women-shield-logo.png')) ?>" alt="">
                    </span>
                    <span class="brand-copy">
                        <strong><?= e(app_config('app_name')) ?></strong>
                        <small>Protect. Report. Respond.</small>
                    </span>
                </a>

                <button class="nav-toggle" type="button" data-nav-toggle aria-label="Toggle navigation">Menu</button>

                <nav class="site-nav" data-nav-menu>
                    <?php if ($account): ?>
                        <?php if ($admin): ?>
                        <a class="<?= is_active_route('mail_setup.php') ? 'active' : '' ?>" href="<?= e(route_url('mail_setup.php')) ?>">Email Setup</a>
                        <a class="<?= str_contains(current_uri(), '/admin') ? 'active' : '' ?>" href="<?= e(route_url('admin/index.php')) ?>">Admin</a>
                        <?php elseif ($user): ?>
                        <a class="<?= is_active_route('dashboard.php') ? 'active' : '' ?>" href="<?= e(route_url('dashboard.php')) ?>">Dashboard</a>
                        <a class="<?= is_active_route('contacts.php') ? 'active' : '' ?>" href="<?= e(route_url('contacts.php')) ?>">Contacts</a>
                        <a class="<?= is_active_route('reports.php') ? 'active' : '' ?>" href="<?= e(route_url('reports.php')) ?>">Reports</a>
                        <a class="<?= is_active_route('safety_map.php') ? 'active' : '' ?>" href="<?= e(route_url('safety_map.php')) ?>">Safety Map</a>
                        <a class="<?= is_active_route('assistant.php') ? 'active' : '' ?>" href="<?= e(route_url('assistant.php')) ?>">AI Assistant</a>
                        <a class="<?= is_active_route('emergency.php') ? 'active' : '' ?>" href="<?= e(route_url('emergency.php')) ?>">Emergency</a>
                        <?php endif; ?>
                        <a class="nav-user" href="<?= e(route_url('logout.php')) ?>">Logout</a>
                    <?php else: ?>
                        <a class="<?= is_active_route('login.php') && !$isAdminLoginPage ? 'active' : '' ?>" href="<?= e(route_url('login.php')) ?>">User Login</a>
                        <a class="<?= $isAdminLoginPage ? 'active' : '' ?>" href="<?= e(route_url('login.php?mode=admin')) ?>">Admin Login</a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>

        <main class="container main-shell">
            <?php if ($success): ?>
                <div class="flash flash-success" data-flash><?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="flash flash-error" data-flash><?= e($error) ?></div>
            <?php endif; ?>
    <?php
}

function render_footer(string $extraScripts = ''): void
{
    ?>
        </main>

        <footer class="site-footer container">
            <p>Women Shield for XAMPP/PHP/MySQL. Built for local deployment and rapid safety response workflows.</p>
        </footer>

        <script src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
        <?= $extraScripts ?>
    </body>
    </html>
    <?php
}
