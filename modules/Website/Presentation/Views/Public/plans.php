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
          <div class="card marketing-pricing-card <?= !empty($plan['is_featured']) ? 'is-featured' : '' ?> p-4 h-100 d-flex flex-column border-0 shadow-sm rounded-3">
            <?php if (!empty($plan['is_featured'])): ?>
              <span class="badge bg-primary text-white rounded-pill align-self-start mb-3 px-3 py-1">Recommended</span>
            <?php endif; ?>
            <h2 class="h4 text-dark fw-bold mb-2"><?= htmlspecialchars((string) $plan['name'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="text-muted mb-4 small"><?= htmlspecialchars((string) ($plan['badge'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

            <div class="display-5 text-dark fw-bold mb-4">
              <?php if ($isEnterprise): ?>
                Custom pricing
              <?php else: ?>
                $<?= number_format((float) $plan['price'], 0) ?><span class="fs-6 text-muted fw-semibold">/<?= $plan['billing_cycle'] === 'annual' ? 'yr' : 'mo' ?></span>
              <?php endif; ?>
            </div>

            <p class="text-muted mb-4 small"><?= htmlspecialchars((string) ($plan['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

            <ul class="list-unstyled text-start mb-4">
              <?php foreach (($plan['features'] ?? []) as $feature): ?>
                <li class="d-flex gap-2 mb-3 text-muted small">
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

    <!-- Detailed Comparison Table -->
    <div class="my-5 py-3">
      <h3 class="text-center fw-bold mb-4">Compare Plans in Detail</h3>
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover align-middle text-center pricing-comparison-table bg-white">
          <thead class="table-light">
            <tr>
              <th class="text-start py-3" style="width: 34%;">Feature</th>
              <th class="py-3" style="width: 22%;">Pilot</th>
              <th class="py-3" style="width: 22%; position: relative;">
                Professional
                <span class="badge bg-primary position-absolute top-0 start-50 translate-middle-y fs-tiny px-2 py-1">RECOMMENDED</span>
              </th>
              <th class="py-3" style="width: 22%;">Multi-site</th>
            </tr>
          </thead>
          <tbody>
            <!-- Section: Assessments & Limits -->
            <tr class="table-group-divider">
              <td colspan="4" class="text-start fw-bold bg-light py-2 text-primary">Assessments &amp; Limits</td>
            </tr>
            <tr>
              <td class="text-start">Completed Assessments / scans</td>
              <td>10 / month</td>
              <td>500 / month</td>
              <td>Custom / Unlimited</td>
            </tr>
            <tr>
              <td class="text-start">Authorized Active Worksites</td>
              <td>1 site</td>
              <td>5 sites</td>
              <td>Custom / Unlimited</td>
            </tr>
            <tr>
              <td class="text-start">Authorized Admins &amp; Users</td>
              <td>3 users</td>
              <td>50 users</td>
              <td>Custom / Unlimited</td>
            </tr>
            <tr>
              <td class="text-start">Video Upload Concurrent Sessions</td>
              <td>1 concurrent</td>
              <td>4 concurrent</td>
              <td>12 concurrent</td>
            </tr>

            <!-- Section: Ergonomic Features -->
            <tr class="table-group-divider">
              <td colspan="4" class="text-start fw-bold bg-light py-2 text-primary">Ergonomic Features</td>
            </tr>
            <tr>
              <td class="text-start">Recognized Assessment Methods (REBA, RULA, NIOSH)</td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
            </tr>
            <tr>
              <td class="text-start">Structured Discomfort &amp; Task Feedback Intake</td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
            </tr>
            <tr>
              <td class="text-start">Corrective Action Assignment &amp; Workflow Tracking</td>
              <td><i class="bi bi-dash text-muted fs-4"></i></td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
            </tr>
            <tr>
              <td class="text-start">Before-and-After Reassessment Analysis</td>
              <td><i class="bi bi-dash text-muted fs-4"></i></td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
            </tr>

            <!-- Section: Administration & Security -->
            <tr class="table-group-divider">
              <td colspan="4" class="text-start fw-bold bg-light py-2 text-primary">Administration &amp; Security</td>
            </tr>
            <tr>
              <td class="text-start">Role-Based Access Control (RBAC) &amp; Permissions</td>
              <td><i class="bi bi-dash text-muted fs-4"></i></td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
            </tr>
            <tr>
              <td class="text-start">Worker Privacy &amp; Video Face Blurring</td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
            </tr>
            <tr>
              <td class="text-start">Configured Video Storage Retention</td>
              <td>30 days</td>
              <td>180 days</td>
              <td>Custom (up to 365 days)</td>
            </tr>
            <tr>
              <td class="text-start">Organization-wide Activity Logging</td>
              <td><i class="bi bi-dash text-muted fs-4"></i></td>
              <td><i class="bi bi-dash text-muted fs-4"></i></td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
            </tr>

            <!-- Section: Service & Support -->
            <tr class="table-group-divider">
              <td colspan="4" class="text-start fw-bold bg-light py-2 text-primary">Service &amp; Support</td>
            </tr>
            <tr>
              <td class="text-start">Support Channel</td>
              <td>Standard (Email/Web)</td>
              <td>Priority Support</td>
              <td>Dedicated Account Ergonomist</td>
            </tr>
            <tr>
              <td class="text-start">Onboarding &amp; Procurement Assistance</td>
              <td><i class="bi bi-dash text-muted fs-4"></i></td>
              <td><i class="bi bi-dash text-muted fs-4"></i></td>
              <td><i class="bi bi-check-lg text-success fs-4"></i></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card p-4 mt-5 shadow-none border-0 bg-light">
      <div class="row g-3 text-center text-lg-start">
        <div class="col-lg-4"><i class="bi bi-credit-card-2-front text-success me-2"></i>No credit card required</div>
        <div class="col-lg-4"><i class="bi bi-check-all text-success me-2"></i>Built on recognized ergonomic methods</div>
        <div class="col-lg-4"><i class="bi bi-shield-lock text-success me-2"></i>Privacy aware task analysis</div>
      </div>
    </div>
  </div>
</section>