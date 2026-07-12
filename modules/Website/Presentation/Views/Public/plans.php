<header class="page-header text-center">
  <div class="container">
    <span class="badge badge-soft-primary rounded-pill px-3 py-2 mb-3">Pricing</span>
    <h1 class="mb-3">Subscription Plans</h1>
    <p class="fs-5 text-muted mb-0 mx-auto page-lede">Choose the plan that fits your ergonomic risk workflow today and scale when more sites, users, or reports come online.</p>
  </div>
</header>

<section class="site-section">
  <div class="container">
    <div class="row justify-content-center g-4">
      <?php foreach (($plans ?? []) as $plan): ?>
        <?php $isEnterprise = strtolower((string) $plan['code']) === 'enterprise'; ?>
        <div class="col-md-6 col-lg-4">
          <div class="card marketing-pricing-card <?= !empty($plan['is_featured']) ? 'is-featured' : '' ?> p-4 h-100 d-flex flex-column">
            <?php if (!empty($plan['is_featured'])): ?>
              <span class="badge badge-soft-primary rounded-pill align-self-start mb-3">Most chosen by growing teams</span>
            <?php endif; ?>
            <h2 class="h4 text-dark fw-bold mb-2"><?= htmlspecialchars((string) $plan['name'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="text-muted mb-4"><?= htmlspecialchars((string) ($plan['badge'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

            <div class="display-5 text-dark fw-bold mb-4">
              <?php if ($isEnterprise): ?>
                Custom pricing
              <?php else: ?>
                $<?= number_format((float) $plan['price'], 0) ?><span class="fs-6 text-muted fw-semibold">/<?= $plan['billing_cycle'] === 'annual' ? 'yr' : 'mo' ?></span>
              <?php endif; ?>
            </div>

            <ul class="list-unstyled text-start mb-4">
              <?php foreach (($plan['features'] ?? []) as $feature): ?>
                <li class="d-flex gap-2 mb-3 text-muted">
                  <i class="bi bi-check-circle-fill text-success"></i>
                  <span><?= htmlspecialchars((string) $feature, ENT_QUOTES, 'UTF-8') ?></span>
                </li>
              <?php endforeach; ?>
            </ul>

            <a href="<?= $isEnterprise ? '/contact' : '/register' ?>" class="btn <?= !empty($plan['is_featured']) ? 'btn-primary' : 'btn-outline-primary' ?> w-100 mt-auto">
              <?= htmlspecialchars((string) ($plan['cta_label'] ?? 'Get Started'), ENT_QUOTES, 'UTF-8') ?>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card p-4 mt-5 shadow-none">
      <div class="row g-3 text-center text-lg-start">
        <div class="col-lg-4"><i class="bi bi-credit-card-2-front text-success me-2"></i>No credit card required</div>
        <div class="col-lg-4"><i class="bi bi-check-all text-success me-2"></i>Built on recognized ergonomic methods</div>
        <div class="col-lg-4"><i class="bi bi-shield-lock text-success me-2"></i>Privacy aware task analysis</div>
      </div>
    </div>
  </div>
</section>
