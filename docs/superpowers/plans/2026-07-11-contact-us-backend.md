# Modernizing Public Website Forms Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a secure, asynchronous POST endpoint for contact form submissions, integrate it with the Notification module, bind the frontend form using App.forms.bindAjaxForm, and resolve all test suite regressions.

**Architecture:** A new controller action in `PageController` validates input and sends a custom email notification using `NotificationServiceInterface`. The frontend form is bound to AJAX, and the whitelist for public route authorization is updated to allow this POST route.

**Tech Stack:** PHP, Bootstrap 5, AJAX, Fetch API, WorkEddy V2 Core (Sneat Admin Layout).

## Global Constraints
- Avoid legacy terminal treatments or placeholders.
- Follow modular monolith design contracts.
- Ensure all tests pass.

---

### Task 1: Fix Core Dependency and Constructor Test Regressions

**Files:**
- Modify: `modules/IAM/Application/GetUserUseCase.php` (Check constructor signature of Membership repository)
- Modify: `modules/Task/Domain/Task.php` (Or test files to pass correct constructor arguments)
- Test: Run phpunit on IAM and WorkerVoice modules

- [ ] **Step 1: Inspect GetUserUseCase constructor and references**
  Check the type hints in `GetUserUseCase.php` and verify why `SingleRoleRepository` is being passed instead of `IOrganizationMembershipRepository` in `IamTenantUserViewTest`.

- [ ] **Step 2: Inspect Task.php constructor and test instantiation**
  Verify Task class constructor signature and make sure the tests instantiating it pass the correct arguments (e.g. `$assessmentModel` or default value).

- [ ] **Step 3: Run the test suite and verify current errors are resolved**
  Run: `vendor/bin/phpunit` (or local phpunit command)
  Expected: Existing 5 errors and 4 failures are resolved.

- [ ] **Step 4: Commit**
  ```bash
  git add modules/
  git commit -m "fix: resolve test regressions in task and IAM modules"
  ```

### Task 2: Create Email Templates for Contact Submission

**Files:**
- Create: `modules/Notification/Templates/email/website.contact_submission.email.php`
- Create: `modules/Notification/Templates/email/website.contact_submission.email.subject.php`

- [ ] **Step 1: Write contact submission email template subject**
  Create `modules/Notification/Templates/email/website.contact_submission.email.subject.php` with content:
  ```php
  New Contact Inquiry from <?= htmlspecialchars((string) ($name ?? ''), ENT_QUOTES, 'UTF-8') ?>
  ```

- [ ] **Step 2: Write contact submission email template body**
  Create `modules/Notification/Templates/email/website.contact_submission.email.php` with content:
  ```php
  <p>You have received a new contact submission from the website.</p>
  <p><strong>Name:</strong> <?= htmlspecialchars((string) ($name ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
  <p><strong>Organization:</strong> <?= htmlspecialchars((string) ($organization ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
  <p><strong>Email Address:</strong> <?= htmlspecialchars((string) ($email ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
  <p><strong>Role or Title:</strong> <?= htmlspecialchars((string) ($role ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
  <p><strong>Industry:</strong> <?= htmlspecialchars((string) ($industry ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
  <p><strong>Reason for Contact:</strong> <?= htmlspecialchars((string) ($reason ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
  <hr>
  <p><strong>Message:</strong></p>
  <p><?= nl2br(htmlspecialchars((string) ($message ?? ''), ENT_QUOTES, 'UTF-8')) ?></p>
  ```

- [ ] **Step 3: Commit**
  ```bash
  git add modules/Notification/Templates/email/
  git commit -m "feat: add contact submission email templates"
  ```

### Task 3: Implement Post route and Controller submit handler

**Files:**
- Modify: `modules/Website/Presentation/routes.php`
- Modify: `modules/Website/Presentation/Controllers/PageController.php`
- Modify: `modules/Website/ServiceProvider.php`
- Modify: `tests/IAM/RouteAuthorizationCoverageTest.php`

- [ ] **Step 1: Register POST route in routes.php**
  Add POST route for `/contact-us/submit` to `PageController::submitContactForm`.
  
- [ ] **Step 2: Add POST route to whitelist in RouteAuthorizationCoverageTest.php**
  Add `'POST /contact-us/submit'` to the public routes array in `RouteAuthorizationCoverageTest.php`.

- [ ] **Step 3: Update Website ServiceProvider to inject NotificationServiceInterface**
  Import `WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface` and inject it into the `PageController` instantiation definition.

- [ ] **Step 4: Implement submitContactForm in PageController**
  Add the validation, sanitization, and notification dispatch logic to `PageController.php`.

- [ ] **Step 5: Commit**
  ```bash
  git add modules/ Website/ Presentation/ tests/
  git commit -m "feat: implement contact form submission backend handling"
  ```

### Task 4: Modernize Frontend View & AJAX Form Binding

**Files:**
- Modify: `modules/Website/Presentation/Views/Public/contact-us.php`
- Create: `public/assets/js/contact-us.js`

- [ ] **Step 1: Add image and form validation wrapper to contact-us.php**
  Update `contact-us.php` layout to include the generated `contact-collaboration-pathways.png` image and set up the form attributes for AJAX.

- [ ] **Step 2: Implement contact-us.js for frontend binding**
  Create `public/assets/js/contact-us.js` and use `App.forms.bindAjaxForm` to handle form submission asynchronously.

- [ ] **Step 3: Update contactUs action in PageController to load required assets**
  Ensure `/assets/js/app.js` and `/assets/js/contact-us.js` are loaded as `pageJs` on the Contact Us view.

- [ ] **Step 4: Verify test suite and update assertions**
  Run PHPUnit tests for the Website design and verify the page correctly processes submits.

- [ ] **Step 5: Commit**
  ```bash
  git add public/ modules/ Website/ Presentation/ Views/ Public/
  git commit -m "feat: modernize frontend contact us view and bind AJAX form handler"
  ```
