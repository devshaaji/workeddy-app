<?php
$assetBase = '/assets';
$asset = static fn(string $path): string => htmlspecialchars($assetBase . '/' . ltrim($path, '/'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) ($pageTitle ?? 'WorkEddy - Ergonomics Risk Assessment'), ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="Automating ergonomic risk assessments to support MSD prevention, improve assessment consistency, and document prevention actions.">
    <meta name="keywords" content="ergonomics, MSD prevention, task analysis, video posture analysis, RULA, REBA">
    <meta name="robots" content="index, follow">

    <link rel="icon" href="/assets/img/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="/assets/css/core.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/assets/css/app.css" rel="stylesheet">
    <link href="/assets/css/site.css" rel="stylesheet">

    <?php foreach ($pageCss ?? [] as $css): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars((string) $css, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
</head>

<body>

    <?php include __DIR__ . '/../Partials/navbar_public.php'; ?>

    <?php if ($content !== ''): ?>
        <?= $content ?>
    <?php else: ?>
        <main class="site-section">
            <div class="container">
                <div class="card p-4">
                    <h1 class="h5 mb-2">Application Shell</h1>
                    <p class="text-muted mb-0">WorkEddy is an ergonomics risk assessment platform.</p>
                </div>
            </div>
        </main>
    <?php endif; ?>

    <footer class="site-footer">
        <div class="container py-4">
            <div class="row g-4">
                <div class="col-lg-4">
                    <img src="/assets/img/workeddy.png" alt="WorkEddy" class="site-logo mb-3">
                    <p class="text-muted pe-lg-5">Supporting task-level ergonomic assessment, corrective-action follow-through, and responsible workplace prevention.</p>
                </div>
                <div class="col-6 col-lg-2 offset-lg-2">
                    <h6>Product</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="/#features">Features</a></li>
                        <li class="mb-2"><a href="/plans">Implementation Pathways</a></li>
                        <li class="mb-2"><a href="/contact-us">Request Detailed Comparison</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <h6>Company</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="/about-us">Our Company</a></li>
                        <li class="mb-2"><a href="/founder-message">Founder's Message</a></li>
                        <li class="mb-2"><a href="/why-us">Why Us</a></li>
                        <li class="mb-2"><a href="/contact-us">Contact Us</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <h6>Legal</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="/privacy-policy">Privacy Policy</a></li>
                        <li class="mb-2"><a href="/terms-of-service">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-top mt-5 pt-4 text-center text-muted small">
                &copy; <?= date('Y') ?> WorkEddy. All rights reserved.
            </div>
        </div>
    </footer>

    <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <?php foreach ($pageJs ?? [] as $js): ?>
        <script src="<?= htmlspecialchars((string) $js, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endforeach; ?>

</body>

</html>