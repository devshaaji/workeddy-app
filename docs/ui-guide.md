# WorkEddy v2 UI Guide

Audience: developers and agents building authenticated module pages in `v2`.

Stack: Sneat Bootstrap 5.3 shell, `Public Sans`, Bootstrap Icons, vanilla JS through `window.App`.

## 1. Source of truth

The Sneat reference template is in `template/`. Use it to verify:

- layout classes
- navbar and sidebar structure
- dropdown and modal markup patterns
- container spacing and footer structure

Runtime files:

- `public/assets/css/core.css`
- `public/assets/css/app.css`
- `public/assets/js/app.js`
- `public/assets/js/shell.js`
- `shared/Views/Layouts/app.php`
- `shared/Views/Partials/sidebar.php`
- `shared/Views/Partials/navbar.php`

## 2. Layout contract

Authenticated pages must keep the Sneat vertical-menu shell contract.

Required `html` contract:

- `layout-navbar-fixed`
- `layout-menu-fixed`
- `layout-compact`
- `data-template="vertical-menu-template"`
- `data-assets-path="/assets/"`

Required wrapper structure:

```html
<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">
    <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme"></aside>
    <div class="layout-overlay" id="layoutOverlay"></div>
    <div class="layout-page">
      <nav id="layout-navbar" class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"></nav>
      <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">
          <!-- page content -->
        </div>
        <footer class="content-footer footer bg-footer-theme"></footer>
      </div>
    </div>
  </div>
</div>
```

## 3. Typography and tokens

Use the variables already defined in `app.css`.

Core tokens:

- `--we-primary`
- `--we-primary-dark`
- `--we-primary-light`
- `--we-bg`
- `--we-surface`
- `--we-border`
- `--we-text`
- `--we-text-muted`
- `--we-heading`
- `--we-radius-sm`
- `--we-radius`
- `--we-radius-lg`
- `--we-radius-xl`
- `--we-shadow-xs`
- `--we-shadow-sm`
- `--we-shadow`

Rules:

- do not hardcode colors when a token exists
- use `Public Sans` as the shell font
- keep spacing and card treatment aligned with Sneat

## 4. Page structure

Typical authenticated page:

```php
<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Module Title';
$pagePurpose = 'Operations';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Module Title', 'url' => '/module'],
];
$pageActions = [
    [
        'label' => 'Create',
        'url' => '/module/create',
        'class' => 'btn btn-primary',
        'icon' => 'plus-lg',
    ],
];

require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="card">
    <div class="card-body">
        <!-- module content -->
    </div>
</div>
```

Use `page_header.php` for heading, breadcrumbs, and actions.

## 5. Sidebar pattern

Single item:

```php
<li class="menu-item<?= $activeNav('my-module') ? ' active' : '' ?>">
    <a class="menu-link" href="/my-module">
        <i class="menu-icon bi bi-box"></i>
        <div data-i18n="My Module">My Module</div>
    </a>
</li>
```

Dropdown:

```php
<?php $moduleOpen = $activeAny(['my-module']); ?>
<li class="menu-item<?= $moduleOpen ? ' active open' : '' ?>">
    <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base bi bi-box"></i>
        <div data-i18n="My Module">My Module</div>
    </a>
    <ul class="menu-sub<?= $moduleOpen ? '' : ' d-none' ?>">
        <li class="menu-item">
            <a class="menu-link" href="/my-module">All Items</a>
        </li>
    </ul>
</li>
```

Rules:

- submenu trigger must be `.menu-link.menu-toggle`
- submenu container must be `.menu-sub`
- open parents carry `.open`
- active items carry `.active`

## 6. Navbar pattern

Navbar uses these sections:

- search trigger
- tenant switcher
- shortcuts dropdown
- theme dropdown
- notifications dropdown
- user dropdown

Keep these hooks intact:

- `navbar-nav-right`
- `dropdown-shortcuts`
- `dropdown-notifications`
- `dropdown-user`
- `data-bs-theme-value`

## 7. `window.App` usage

Never bypass `window.App` for shared page behavior.

### API

```js
App.api.get(url, params?, opts?)
App.api.post(url, data?, opts?)
App.api.put(url, data?, opts?)
App.api.patch(url, data?, opts?)
App.api.delete(url, data?, opts?)
```

### Notifications

```js
App.notify.success('Saved.');
App.notify.error('Failed.');
App.notify.warning('Check this.');
App.notify.info('Processing...');
```

### UI helpers

```js
App.ui.setButtonLoading(button, true);
App.ui.showAlert('danger', 'Something failed', '#alertBox');
App.ui.clearAlert('#alertBox');
App.ui.showEmptyState('#results', 'No records found.');
App.ui.updateStatusBadge('#statusBadge', 'active');
```

### Forms

```js
App.forms.bindAjaxForm(formEl, {
  url: '/api/v1/module/records',
  method: 'POST',
  submitBtn: '#submitBtn'
});
```

### Tables

```js
var table = App.tables.createAdvanced({
  card: '#recordsCard',
  tbody: '#recordsBody',
  endpoint: '/api/v1/module/records',
  colspan: 4,
  emptyTitle: 'No records found',
  emptySubtitle: 'Create the first record to continue.',
  renderRow: function (record) {
    return '<tr><td>' + App.utils.escapeHtml(record.name) + '</td></tr>';
  }
});
```

### Modals

```js
App.modals.open('#editModal');
App.modals.close('#editModal');
App.modals.reset('#editModal');
App.modals.confirm({
  title: 'Delete record',
  text: 'This action cannot be undone.',
  confirmText: 'Delete',
  icon: 'warning',
  onConfirm: function () {}
});
```

## 8. Shared component rules

Preferred shared output:

- badges: `badge bg-label-*`
- cards: `card`, `card-header`, `card-body`, `card-footer`
- dropdown actions: Bootstrap dropdown with `dropdown-menu-end`
- modals: standard Bootstrap modal markup
- empty states: Bootstrap spacing and text utilities

Do not emit these old patterns in shared code:

- `btn-2`
- `alert-important`
- `bg-*-lt`
- `modal-blur`
- `modal-status`
- Tabler icon button markup

## 9. File load order

Authenticated shell load order:

1. `core.css`
2. icon font stylesheet
3. `app.css`
4. `bootstrap.bundle.min.js`
5. `chart.umd.min.js`
6. `app.js`
7. `shell.js`
8. page scripts from `$pageScripts`

## 10. Must and must not

Must:

- use `App.api.*` for requests
- use `App.notify.*` for user feedback
- use `App.forms.bindAjaxForm()` for AJAX forms
- use Bootstrap 5 and Sneat shell classes
- escape user content with `App.utils.escapeHtml()`
- keep page scripts in `public/assets/js/modules/`
- keep page content inside `container-xxl flex-grow-1 container-p-y`

Must not:

- no Alpine directives
- no Tabler CSS classes or variables
- no raw `fetch()` in page modules
- no jQuery AJAX
- no inline view `<script>` blocks
- no inline event handlers like `onclick=""`
- no hardcoded colors when a token exists

## 11. Verification checklist

Before finishing shell or shared UI work, verify:

- shell still renders `layout-content-navbar`
- sidebar still uses `layout-menu menu-vertical menu bg-menu-theme`
- navbar still exposes shortcuts, notifications, and user dropdowns
- app helpers emit Sneat-compatible classes
- active sidebar states still resolve from route and view context
