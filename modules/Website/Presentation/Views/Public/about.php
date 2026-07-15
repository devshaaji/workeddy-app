<header class="about-msg-section">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <div class="about-title-container">
          <span class="badge badge-soft-primary rounded-pill px-3 py-2 mb-3">Our Company</span>
          <h1 class="about-msg-title">WorkEddy turns high-strain work into visible prevention action.</h1>
          <p class="fs-5 text-muted mb-0 page-lede">We help organizations examine how work tasks may contribute to ergonomic risk, incorporate worker feedback, assign corrective actions, verify follow-through, and document changes in assessed risk while protecting worker dignity and privacy.</p>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="about-signal-panel">
          <div>
            <span class="about-signal-kicker">Core promise</span>
            <strong>Identify risk. Improve the task. Reassess the work. Protect worker dignity.</strong>
          </div>
          <div class="about-signal-metrics">
            <span><i class="bi bi-camera-video"></i> Task review</span>
            <span><i class="bi bi-clipboard2-pulse"></i> Risk logic</span>
            <span><i class="bi bi-check2-circle"></i> Action tracking</span>
            <span><i class="bi bi-shield-lock"></i> Privacy controls</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>

<main>
  <section class="site-section bg-white">
    <div class="container">
      <div class="row g-5 align-items-start">
        <div class="col-lg-6">
          <span class="section-kicker">About WorkEddy</span>
          <h2 class="section-title">Built for demanding work that is repetitive, fast-paced, hot, physical, or overlooked.</h2>
        </div>
        <div class="col-lg-6">
          <p class="fs-5 text-muted mb-4">WorkEddy was created to move workplace safety from injury response to evidence-based prevention. Many organizations know when workers are hurting, but they do not always have a clear, trustworthy way to connect task-level strain to corrective action and documented improvement.</p>
          <p class="text-muted mb-0">WorkEddy brings authorized task observation, structured ergonomic assessment, body-region information, worker feedback, corrective-action tracking, follow-up reassessment, and privacy controls into a connected prevention workflow.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="site-section about-workflow-section">
    <div class="container">
      <div class="row g-5 align-items-center">
        <div class="col-lg-5">
          <span class="section-kicker">What We Do</span>
          <h2 class="section-title">A prevention-evidence workflow from risk identification to de-identified learning.</h2>
          <p class="text-muted mb-4">WorkEddy supports task-level review by helping safety teams capture or document work tasks, assess ergonomic risk, identify affected body regions, incorporate worker feedback, recommend corrective actions, assign responsibility, verify completion, and compare before-and-after results.</p>

          <a href="/why-us" class="btn btn-primary">See why WorkEddy is different <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
        <div class="col-lg-7">
          <div class="about-visual-wrapper">
            <div class="about-visual-offset"></div>
            <figure class="content-visual-frame mb-0">
              <img src="/assets/img/about/prevention-evidence-workflow.png" alt="WorkEddy prevention evidence workflow from risk identification to de-identified learning">
              <figcaption>Risk identification, corrective action, verification, and learning stay connected.</figcaption>
            </figure>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="site-section bg-white">
    <div class="container">
      <div class="text-center mx-auto mb-5" style="max-width: 760px;">
        <span class="section-kicker">Platform Focus</span>
        <h2 class="section-title mb-3">Prevention work that stays tied to the task.</h2>
        <p class="text-muted mb-0">WorkEddy is not another checklist. It is a prevention-evidence platform designed to help organizations identify risks earlier, act faster, protect worker trust, and learn from corrections that make work safer.</p>
      </div>
      <div class="row g-4">
        <?php foreach (
          [
            ['icon' => 'bi-activity', 'title' => 'Identify risk earlier', 'copy' => 'Support earlier identification of task-level ergonomic risk factors, including awkward posture, repetition, forceful exertion, and manual material-handling concerns'],
            ['icon' => 'bi-person-heart', 'title' => 'Include worker voice', 'copy' => 'Connect discomfort, task difficulty, and suggested changes to prevention decisions.'],
            ['icon' => 'bi-tools', 'title' => 'Assign corrective action', 'copy' => 'Translate risk findings into responsible owners, due dates, evidence, and follow-up.'],
            ['icon' => 'bi-bar-chart-line', 'title' => 'Document improvement', 'copy' => 'Compare before-and-after results and create de-identified prevention evidence.'],
          ] as $item
        ): ?>
          <div class="col-md-6 col-xl-3">
            <div class="about-feature h-100">
              <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
              <h3><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h3>
              <p><?= htmlspecialchars($item['copy'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>