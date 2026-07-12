<?php

/** @var string $profileTab */
$profileTab = $profileTab ?? 'overview';
?>

<div class="mb-4">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link<?= $profileTab === 'overview' ? ' active' : '' ?>" href="/profile">
                Overview
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link<?= $profileTab === 'security' ? ' active' : '' ?>" href="/profile/security">
                Security
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link<?= $profileTab === 'sessions' ? ' active' : '' ?>" href="/profile/sessions">
                Sessions
            </a>
        </li>
    </ul>
</div>
