# WorkEddy JS Foundation (app.js)

`app.js` serves as the centralized JavaScript framework for the WorkEddy platform, exposing all utility modules under the global `window.App` namespace.

**CRITICAL COMPLIANCE FOR DEVELOPERS & AI AGENTS:** 
Do NOT implement API request handling, form serialization, validation messages, notification toasts, confirmation dialogs, table selection/sorting/pagination, date/currency formatting, or PDF generation from scratch. **You must reuse the utilities documented below.**

---

## 1. Loading Order
To ensure proper initialization, script files must be loaded in the following order:
1. Vendor dependencies (jQuery, Bootstrap bundle, Tabler, Notyf etc.)
2. `app.js` (Instantiates `window.App`)
3. Page-specific or module scripts (e.g. `auth.js`)

---

## 2. API Reference

### `App.api` (Fetch Client)
Wrapper around standard `fetch` with automated base URL routing, CSRF token attachment, query parameter compilation, and unified response envelope formatting.

- **`App.api.request(method, url, data, opts)`**
  Sends an AJAX request. Returns a promise resolving to a normalized response envelope: `{ ok, status, code, message, data, errors, raw }`.
  - `method`: HTTP Verb (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`).
  - `url`: Absolute or relative path. Relative paths starting with `/` automatically resolve relative to the current context's base URL.
  - `data`: Body payload (automatically serialized as JSON or handled as `FormData` if file inputs are present). For `GET` requests, parameters are compiled into a query string.
  - `opts`: Custom options. Supports `headers` (Object), `timeout` (Integer), `raw` (Boolean - returns raw fetch response), `skipAuthRedirect` (Boolean - bypasses auto-redirect to `/login` on `401 Unauthorized`).

- **`App.api.get(url, params, opts)`**: Shorthand for GET request.
- **`App.api.post(url, data, opts)`**: Shorthand for POST request.
- **`App.api.put(url, data, opts)`**: Shorthand for PUT request.
- **`App.api.patch(url, data, opts)`**: Shorthand for PATCH request.
- **`App.api.delete(url, data, opts)`**: Shorthand for DELETE request.

---

### `App.notify` (Toast Alerts)
Toasts alerts with auto-fallback. Uses the `Notyf` library if loaded, falls back to Bootstrap toasts, and uses simple top-level alerts if no vendor style is present.

- **`App.notify.success(message)`**: Shows a success notification.
- **`App.notify.error(message)`**: Shows an error notification.
- **`App.notify.warning(message)`**: Shows a warning notification.
- **`App.notify.info(message)`**: Shows an informational notification.

---

### `App.ui` (Interface Helpers)
Methods for handling standard Bootstrap elements, loading states, and state badges.

- **`App.ui.setButtonLoading(button, isLoading, [text])`**
  Toggles loading status on a button element (adds spinner class, disables button, and caches original HTML to restore state).
- **`App.ui.showAlert(type, message, target)`**
  Displays a dismissible Bootstrap alert inside a target container.
  - `type`: `'success' | 'danger' | 'warning' | 'info'`.
  - `message`: Text content.
  - `target`: Element or CSS selector string.
- **`App.ui.clearAlert(target)`**: Removes alert nodes from the target container.
- **`App.ui.showEmptyState(target, [message])`**: Inserts a generic "No records found" screen with folder SVG.
- **`App.ui.updateStatusBadge(target, status)`**
  Standardizes color coding for status indicator labels (automatically handles mapping and applies Bootstrap label colors).
  - Supported Statuses: `active`/`approved`/`paid` (success), `pending` (warning), `draft`/`inactive` (secondary), `rejected`/`overdue`/`failed`/`cancelled`/`suspended` (danger), `processing`/`partial`/`sent` (info).

---

### `App.forms` (AJAX Forms & Validation)
Standardizes inputs, validation styling, and submission.

- **`App.forms.bindAjaxForm(form, opts)`**
  Intercepts standard HTML form submit, serializes values, handles loader state, clears old errors, posts to the endpoint, and renders validation bubbles on failure.
  - `opts.method` / `opts.url`: Overrides attributes on the form tag.
  - `opts.useFormData`: Forces submission as `FormData` instead of JSON.
  - `opts.alertTarget`: Element or selector to mount overall form alerts.
  - `opts.beforeSend(form)`: Callback before request. Return `false` to abort submission.
  - `opts.onSuccess(response)` / `opts.onError(response)` / `opts.onComplete(response)`: Lifecycle callback hooks.
  - `opts.resetOnSuccess`: Resets form inputs upon successful submission.
  - `opts.successMsg`: Toast message displayed on success.
- **`App.forms.serialize(form)`**: Extracts input values into a structured object, supporting array fields (`name="field[]"`).
- **`App.forms.showValidationErrors(form, errors)`**: Renders `.is-invalid` classes and appends `.invalid-feedback` labels under inputs. Returns unmatched form-level errors.
- **`App.forms.clearValidationErrors(form)`**: Resets input classes and clears error labels.
- **`App.forms.enhanceSelects(container)`** / **`App.forms.refreshSelect(select)`**: Renders styled Select elements.

---

### `App.tables` (Dynamic Advanced Tables)
Automates table sorting, local or remote filtering, pagination, and multi-row selection checks.

- **`App.tables.createAdvanced(opts)`**
  Instantiates and returns a new `AdvancedTable` controller.
  - **Options (`opts`):**
    * `el`: Table wrapper container element.
    * `tbody`: Table body element.
    * `selectAllEl` / `bulkBarEl` / `selectedCountEl`: Bulk actions DOM nodes.
    * `url`: Server endpoint to pull records (used if table loads data remotely).
    * `data`: Array of local records (used if loading data locally).
    * `pageSize`: Default records per page.
    * `renderRow(record, controller)`: Function returning HTML string for a row.
    * `afterRender(controller)`: Hook triggered after table drawing.
  - **`AdvancedTable` Controller Methods:**
    * `load()`: Reloads data from the remote URL endpoint.
    * `render()`: Redraws records in body based on pagination and sorting.
    * `getSelectedKeys()`: Returns an array of keys (values) of currently checked rows.
    * `clearSelection()`: Deselects all rows and hides the bulk bar.
- **`App.tables.actionDropdown(innerHtml)`**: Returns a standard styled row context menu template.
- **`App.tables.extractRecords(response)`**: Normalizes list records from API responses.

---

### `App.modals` (Bootstrap Modals)
Programmatic control over Bootstrap modals.

- **`App.modals.open(selector)`**: Shows a Bootstrap Modal dialog.
- **`App.modals.close(selector)`**: Hides a Bootstrap Modal dialog.
- **`App.modals.reset(selector)`**: Clears forms and alerts within a Modal.
- **`App.modals.confirm(opts)`**
  Spawns a styled confirm modal.
  - `opts.title` / `opts.text`: Message text.
  - `opts.confirmText` / `opts.cancelText`: Label texts.
  - `opts.icon`: `'warning' | 'error' | 'success' | 'info' | 'question'`.
  - `opts.onConfirm` / `opts.onCancel`: Promise/callback handlers.

---

### `App.utils` (Utility Helpers)
Shared string, formatters, and PDF handlers.

- **`App.utils.escapeHtml(val)`**: Safe HTML string escaper.
- **`App.utils.formatCurrency(val, [currency])`**: Formats monetary values. Defaults to `NGN` (₦).
- **`App.utils.formatDate(val)`**: Formats string/timestamp/date objects to `DD MMM YYYY`.
- **`App.utils.formatBytes(bytes)`**: Human-readable file sizes (e.g. `2.5 MB`).
- **`App.utils.formatDuration(seconds)`**: Human-readable durations (e.g. `1h 24m`).
- **`App.utils.buildQuery(params)`**: Converts objects to URL query strings.
- **`App.utils.getCsrfToken()`**: Returns the current CSRF token from metadata tags.
- **`App.utils.downloadElementPdf(target, [opts])`**: Exports HTML element to a PDF download.
- **`App.utils.printElementPdf(target, [opts])`**: Prints HTML element content to a PDF document.
- **`App.utils.downloadInvoicePdf(target, reference, [opts])`**: Downloads invoice PDF with custom default options.
- **`App.utils.printToPdf(target, [opts])`**: Alias to print element to PDF.
- **`App.utils.bindPdfDownloadButtons(root)`**: Automates click triggers for elements decorated with `data-pdf-target`.
- **`App.utils.pageSpinner()`**: Manages full-screen overlay spin indicators.

---

## 3. Legacy Dialog Fallback Methods

Defined globally on `window`:

- **`window.appAlert(message, [options])`**: Generates a standard notice modal. Falls back to native browser `alert()` if Bootstrap is missing.
- **`window.appConfirm(message, [options])`**: Generates a confirm dialog. Falls back to native `confirm()` if Bootstrap is missing.
