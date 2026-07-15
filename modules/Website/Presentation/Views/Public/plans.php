<header class="page-header text-center">
  <div class="container">
    <span class="badge badge-soft-primary rounded-pill px-3 py-2 mb-3">Plans</span>
    <h1 class="mb-3">Pilot And Implementation Pathways</h1>
    <p class="fs-5 text-muted mb-0 mx-auto page-lede">Public plan pages show the operating model for each pathway. Detailed quota, governance, and implementation comparisons are handled separately during scoping.</p>
  </div>
</header>

<section class="site-section">
  <div class="container">
    <div class="row justify-content-center g-4">
      <?php foreach (($plans ?? []) as $plan): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card marketing-pricing-card <?= !empty($plan['is_featured']) ? 'is-featured' : '' ?> p-4 h-100 d-flex flex-column border-0 shadow-sm rounded-3">
            <?php if (!empty($plan['is_featured'])): ?>
              <span class="badge bg-primary text-white rounded-pill align-self-start mb-3 px-3 py-1">Recommended</span>
            <?php endif; ?>
            <h2 class="h4 text-dark fw-bold mb-2"><?= htmlspecialchars((string) $plan['name'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="text-muted mb-4 small"><?= htmlspecialchars((string) ($plan['summary'] ?? $plan['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

            <div class="display-5 text-dark fw-bold mb-4">
              <?php if (!empty($plan['is_custom_pricing'])): ?>
                Custom pricing
              <?php else: ?>
                $<?= number_format((float) $plan['price'], 0) ?><span class="fs-6 text-muted fw-semibold">/<?= $plan['billing_cycle'] === 'annual' ? 'yr' : 'mo' ?></span>
              <?php endif; ?>
            </div>

            <ul class="list-unstyled text-start mb-4">
              <?php foreach (($plan['features'] ?? []) as $feature): ?>
                <li class="d-flex gap-2 mb-3 text-muted small">
                  <i class="bi bi-check-circle-fill text-success"></i>
                  <span><?= htmlspecialchars((string) $feature, ENT_QUOTES, 'UTF-8') ?></span>
                </li>
              <?php endforeach; ?>
            </ul>

            <a href="<?= htmlspecialchars((string) ($plan['cta_href'] ?? '/register'), ENT_QUOTES, 'UTF-8') ?>" class="btn <?= !empty($plan['is_featured']) ? 'btn-primary' : 'btn-outline-primary' ?> w-100 mt-auto">
              <?= htmlspecialchars((string) ($plan['cta_label'] ?? 'Get Started'), ENT_QUOTES, 'UTF-8') ?>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card p-4 mt-5 shadow-none border-0 bg-light">
      <div class="row g-3 align-items-center">
        <div class="col-lg-8">
          <h3 class="h5 fw-bold mb-2">Need the detailed implementation comparison?</h3>
          <p class="text-muted mb-0">WorkEddy handles detailed quota, governance, retention, and implementation scoping separately from the public plan page so commercial discussions stay aligned with actual backend entitlements.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
          <a href="/contact-us" class="btn btn-outline-primary">Request detailed comparison</a>
        </div>
      </div>
    </div>
  </div>
</section>
