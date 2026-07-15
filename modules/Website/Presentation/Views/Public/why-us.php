<header class="why-msg-section">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <div class="why-title-container">
          <span class="badge badge-soft-primary rounded-pill px-3 py-2 mb-3">Why Us</span>
          <h1 class="why-msg-title">Prevention should not end with a risk score.</h1>
          <p class="fs-5 text-muted mb-4 page-lede">Many workplace safety tools identify hazards. WorkEddy is different because it connects risk identification to corrective action, verification, follow-up review, and evidence generation. It helps organizations move from observation to action, from action to verification, and from verification to measurable prevention learning.</p>
          <a href="/contact-us" class="btn btn-primary">Talk to WorkEddy <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="why-visual-wrapper">
          <div class="why-visual-offset"></div>
          <figure class="content-visual-frame mb-0">
            <img src="/assets/img/about/privacy-first-ergonomic-assessment.png" alt="Privacy-first ergonomic assessment showing posture analysis with identity protection">
            <figcaption>Privacy-first ergonomic assessment keeps worker trust inside the prevention workflow.</figcaption>
          </figure>
        </div>
      </div>
    </div>
  </div>
</header>

<main>
  <section class="site-section bg-white">
    <div class="container">
      <div class="row g-4 align-items-stretch">
        <div class="col-lg-6">
          <div class="comparison-panel comparison-muted h-100">
            <span>Fragmented assessment workflows</span>
            <h2>When assessment findings, corrective actions, and follow-up evidence are stored in separate systems, it may be difficult to determine who was responsible, what action was completed, and whether the task was reassessed.</h2>
            <ul>
              <li>Hazards can become static notes.</li>
              <li>Corrective action ownership is hard to trace.</li>
              <li>Follow-up evidence may be scattered or missing.</li>
              <li>Workers can feel observed instead of protected.</li>
            </ul>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="comparison-panel comparison-primary h-100">
            <span>WorkEddy</span>
            <h2>Risk becomes an organized prevention loop.</h2>
            <ul>
              <li>Task review stays connected to body-region insights.</li>
              <li>Recommended controls become assigned actions.</li>
              <li>Follow-up evidence and reassessment document whether measured task-level risk changed.</li>
              <li>Consent, de-identification, access control, and human review protect trust.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="site-section why-proof-section">
    <div class="container">
      <div class="row g-5 align-items-center">
        <div class="col-lg-6">
          <div class="why-visual-wrapper">
            <div class="why-visual-offset"></div>
            <figure class="content-visual-frame mb-0">
              <img src="/assets/img/about/before-and-after-proof.png" alt="Before-and-after proof showing how corrective action can be linked to reassessment and documented improvement">
              <figcaption>Before-and-after proof links the correction to a measurable follow-up review.</figcaption>
            </figure>
          </div>
        </div>
        <div class="col-lg-6">
          <span class="section-kicker">What Makes WorkEddy Different</span>
          <h2 class="section-title">The platform is designed around action, verification, and learning.</h2>
          <div class="proof-list">
            <?php foreach (
              [
                ['icon' => 'bi-arrow-repeat', 'title' => 'Prevention loop, not checklist', 'copy' => 'Assign corrective actions, verify completion, reassess the task, and document whether risk changed.'],
                ['icon' => 'bi-bullseye', 'title' => 'Task-level visibility', 'copy' => 'Focus on where strain, awkward posture, repetition, force, fatigue, heat, and discomfort first appear.'],
                ['icon' => 'bi-person-check', 'title' => 'Worker voice without worker blame', 'copy' => 'Structure discomfort and task feedback around work design, tools, workload, staffing, pace, exposure, and controls.'],
                ['icon' => 'bi-shield-check', 'title' => 'Privacy-protective prevention', 'copy' => 'Depending on the configured and available features, WorkEddy supports worker notice or authorization, reduced-identification workflows, controlled access, retention management, and human review.'],
              ] as $item
            ): ?>
              <div class="proof-item">
                <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                <div>
                  <h3><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                  <p><?= htmlspecialchars($item['copy'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="site-section bg-white">
    <div class="container">
      <div class="row g-4">
        <?php foreach (
          [
            ['title' => 'Human-reviewed technology', 'copy' => 'Technology-assisted features may identify visible posture and movement patterns for reviewer consideration. A qualified user confirms the task context, method inputs, score interpretation, and recommended response before findings are used.'],
            ['title' => 'Corrective action that can be tracked', 'copy' => 'Risk factors connect to recommended controls, responsible owners, status, evidence, and follow-up review.'],
            ['title' => 'Public health value', 'copy' => 'De-identified prevention evidence can support pilots, research, employer learning, grant applications, dashboards, and occupational health improvement.'],
            ['title' => 'Sustainable work design', 'copy' => 'Protecting workers, redesigning harmful tasks, and reducing preventable harm belong to a more sustainable future of work.'],
          ] as $item
        ): ?>
          <div class="col-md-6 col-xl-3">
            <div class="about-feature h-100">
              <h3><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h3>
              <p><?= htmlspecialchars($item['copy'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>