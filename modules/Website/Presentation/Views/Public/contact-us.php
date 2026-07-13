<main>
  <section class="site-section contact-form-section mt-10">
    <div class="container">
      <div class="row g-5 align-items-start">
        <div class="col-lg-5">
          <span class="section-kicker">Start the Conversation</span>
          <h2 class="section-title">Tell us what kind of prevention work you want to explore.</h2>
          <p class="text-muted mb-4">Contact WorkEddy to request a pilot, schedule a product conversation, discuss research collaboration, ask about privacy and worker trust, or explore partnership opportunities.</p>
        </div>
        <div class="col-lg-7">
          <div id="contact-feedback" class="d-none mb-3"></div>
          <form id="contact-form" class="contact-form needs-validation" action="/contact-us/submit" method="post" novalidate>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="contact-name" class="form-label">Name</label>
                <input type="text" class="form-control" id="contact-name" name="name" required>
              </div>
              <div class="col-md-6">
                <label for="contact-organization" class="form-label">Organization</label>
                <input type="text" class="form-control" id="contact-organization" name="organization" required>
              </div>
              <div class="col-md-6">
                <label for="contact-email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="contact-email" name="email" required>
              </div>
              <div class="col-md-6">
                <label for="contact-role" class="form-label">Role or Title</label>
                <input type="text" class="form-control" id="contact-role" name="role">
              </div>
              <div class="col-md-6">
                <label for="contact-industry" class="form-label">Industry</label>
                <input type="text" class="form-control" id="contact-industry" name="industry">
              </div>
              <div class="col-md-6">
                <label for="contact-reason" class="form-label">Reason for Contact</label>
                <select class="form-select" id="contact-reason" name="reason" required>
                  <option value="">Select a reason</option>
                  <option>Request a Pilot</option>
                  <option>Schedule a Demo</option>
                  <option>Research Collaboration</option>
                  <option>Employer Inquiry</option>
                  <option>Privacy or Worker Trust Question</option>
                  <option>Partnership Opportunity</option>
                  <option>General Question</option>
                </select>
              </div>
              <div class="col-12">
                <label for="contact-message" class="form-label">Message</label>
                <textarea class="form-control" id="contact-message" name="message" rows="6" required></textarea>
              </div>
              <div class="col-12 d-flex flex-column flex-sm-row gap-3 align-items-sm-center">
                <button type="submit" class="btn btn-primary">Send Message <i class="bi bi-send ms-1"></i></button>
                <a href="mailto:pilots@workeddy.com" class="btn btn-outline-primary">Request a Pilot</a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>

  <section class="site-section bg-white border-top">
    <div class="container text-center">
      <h2 class="h3 fw-bold text-dark mb-3">Let's build safer work before pain becomes routine.</h2>
      <a href="mailto:hello@workeddy.com" class="btn btn-primary">Talk to WorkEddy</a>
    </div>
  </section>
</main>
