<?php
declare(strict_types=1);

$actionId = (string) (($routeParams['actionId'] ?? '') ?: '');
$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Upload Corrective Evidence';
$pagePurpose = 'Attach implementation proof before verifier sign-off.';
$breadcrumbs = [
    ['label' => 'Corrective Actions', 'url' => '/corrective-actions'],
    ['label' => 'Evidence', 'url' => null],
];
$pageActions = [
    ['label' => 'Action detail', 'url' => '/corrective-actions/' . $actionId, 'class' => 'btn btn-outline-secondary', 'icon' => 'arrow-left'],
];
$pageScripts = ['js/modules/corrective-action.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 py-4" data-ca-page="evidence" data-action-id="<?= htmlspecialchars($actionId, ENT_QUOTES, 'UTF-8') ?>">
    <section class="row g-4">
        <div class="col-lg-7">
            <article class="card" style="border-radius: var(--we-radius-xl); box-shadow: var(--we-shadow-sm)">
                <div class="card-header">
                    <h5 class="mb-0">Evidence package</h5>
                </div>
                <form class="card-body" id="caEvidenceForm" enctype="multipart/form-data" novalidate>
                    <div class="mb-3">
                        <label for="caEvidenceFile" class="form-label">Evidence file</label>
                        <input id="caEvidenceFile" name="evidence" class="form-control" type="file" required>
                        <div class="form-text">Use photos, short videos, receipts, training sheets, or installation proof.</div>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="caEvidenceType" class="form-label">Evidence type</label>
                        <select id="caEvidenceType" name="evidenceType" class="form-select">
                            <option value="photo">Photo</option>
                            <option value="video">Video</option>
                            <option value="receipt">Receipt</option>
                            <option value="document">Document</option>
                            <option value="note">Field note</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="caEvidenceNotes" class="form-label">Implementation notes</label>
                        <textarea id="caEvidenceNotes" name="notes" class="form-control" rows="5" placeholder="What changed, who completed it, and what verifier should inspect"></textarea>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button id="caEvidenceSubmit" class="btn btn-primary cursor-pointer" type="submit">
                            <i class="bi bi-cloud-upload me-1"></i>Upload evidence
                        </button>
                        <a class="btn btn-outline-secondary" href="/corrective-actions/<?= htmlspecialchars($actionId, ENT_QUOTES, 'UTF-8') ?>">Back to detail</a>
                    </div>
                </form>
            </article>
        </div>
        <div class="col-lg-5">
            <article class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-body">
                    <span class="badge bg-label-info mb-3">Verifier checklist</span>
                    <h5>Good evidence answers four questions</h5>
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex gap-2 mb-3"><i class="bi bi-check-circle text-success"></i><span>What control was installed or changed?</span></li>
                        <li class="d-flex gap-2 mb-3"><i class="bi bi-check-circle text-success"></i><span>Where is it located and who owns it?</span></li>
                        <li class="d-flex gap-2 mb-3"><i class="bi bi-check-circle text-success"></i><span>Does it match the accepted recommendation?</span></li>
                        <li class="d-flex gap-2"><i class="bi bi-check-circle text-success"></i><span>Is follow-up assessment needed after use?</span></li>
                    </ul>
                </div>
            </article>
            <article class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-header">
                    <h5 class="mb-0">Current evidence</h5>
                </div>
                <div class="card-body" id="caEvidenceList"></div>
            </article>
        </div>
    </section>
</div>
