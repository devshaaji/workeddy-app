<?php

declare(strict_types=1);

/**
 * WorkEddy v2 – error layout wrapper.
 */

$content ??= '';
$code ??= '500';
$error ??= ['title' => 'Internal Server Error'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>WorkEddy | <?= htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) $error['title'], ENT_QUOTES, 'UTF-8') ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS files -->
    <link href="/assets/css/core.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/assets/css/app.css" rel="stylesheet" />
</head>
<?= $content; ?>