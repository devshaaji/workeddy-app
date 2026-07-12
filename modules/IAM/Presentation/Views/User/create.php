<?php
$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Create User';
$pagePurpose = 'Prepare a staff or service account with identity and role-based access.';
$pageActions = [
    ['label' => 'Back to Users', 'url' => '/users', 'class' => 'btn btn-outline-secondary'],
];
$pageScripts = ['js/iam.js'];
$can = $can ?? [];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<form id="iam-user-create-form" action="/api/v1/iam/users" method="post" autocomplete="off" data-iam-form="user-create">
    <div class="row g-4">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title mb-0">Identity Information</h3>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label" for="full_name">Full name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Full name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email address</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="name@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone number</label>
                            <input type="text" id="phone" name="phone" class="form-control" placeholder="08000000000">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title mb-0">Access Setup</h3>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label" for="role_slug">Role</label>
                            <select id="role_slug" name="role_slug" class="form-select">
                                <option value="">Select role</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password">Temporary password</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Temporary password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="confirm_password">Confirm temporary password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat temporary password">
                        </div>
                    </div>
                </div>
            </div>


            <div class="card-body">
                <div id="iam-user-create-feedback" class="d-none mb-4" data-form-feedback></div>
                <div class="d-flex flex-wrap justify-content-end gap-2">
                    <a href="/users" class="btn btn-outline-secondary">Cancel</a>
                    <?php if (!empty($can['createUsers'])): ?><button type="submit" class="btn btn-primary">Create User</button><?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</form>