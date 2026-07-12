<?php

declare(strict_types=1);

$layoutTitle = $layoutTitle ?? 'WorkEddy';
$moduleName = $moduleName ?? 'Auth';
$content = $content ?? '';

$assetBase = '/assets';
$asset = static fn(string $path): string => htmlspecialchars($assetBase . '/' . ltrim($path, '/'), ENT_QUOTES, 'UTF-8');
$title = htmlspecialchars((string) $layoutTitle, ENT_QUOTES, 'UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = (string) $_SESSION['_csrf_token'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title>WorkEddy | <?= $title ?></title>
    <link rel="icon" type="image/png" href="<?= $asset('img/favicon.ico') ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= $asset('css/core.css') ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= $asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="auth-page-body">
<?= $content ?>

<!-- System Dialog (appAlert / appConfirm) -->
<?php require __DIR__ . '/../Partials/system_dialog.php'; ?>

<script src="<?= $asset('vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= $asset('js/app.js') ?>"></script>
<script src="<?= $asset('js/auth.js') ?>"></script>
<?php foreach (($pageScripts ?? []) as $script): ?>
    <script src="<?= $asset((string) $script) ?>"></script>
<?php endforeach; ?>
</body>
</html>