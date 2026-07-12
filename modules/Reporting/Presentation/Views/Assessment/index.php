<?php declare(strict_types=1); ?>
<?php
$pageTitle = 'Ergonomic Assessment Report';
$reportScoreStatus = (string) ($report_score_status ?? 'reviewer_confirmed');
$scoreReady = $reportScoreStatus === 'reviewer_confirmed';
$riskScoreValue = (float) ($risk_score ?? 0.0);
$riskBarWidth = $scoreReady ? (($riskScoreValue / 15) * 100) : 0;
$aiAdvisory = is_array($ai_advisory ?? null) ? $ai_advisory : [];
$bodyRegionScores = is_array($body_region_scores ?? null) ? $body_region_scores : [];
$riskFactors = is_array($risk_factors ?? null) ? $risk_factors : [];
$recommendations = is_array($recommendations ?? null) ? $recommendations : [];
$highestRegionScore = $bodyRegionScores === [] ? 0 : (float) max($bodyRegionScores);
?>

<div class="container-xxl flex-grow-1 pb-4">
    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-4">
                <div>
                    <span class="badge bg-label-info mb-2">Assessment report</span>
                    <h5 class="mb-2">Reviewed ergonomic record with task context, body-region exposure, and corrective direction.</h5>
                    <p class="text-muted mb-0">Use the approved score and reviewer context here as the reporting source of truth.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="/reporting/pilot-summary" class="btn btn-outline-secondary">Back to Pilot Summary</a>
                    <a href="/api/v1/reporting/assessment/<?= htmlspecialchars($uuid) ?>/pdf" class="btn btn-primary" target="_blank">Download PDF</a>
                    <a href="/api/v1/reporting/assessment/<?= htmlspecialchars($uuid) ?>/csv" class="btn btn-outline-secondary" target="_blank">Export CSV</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-widget-separator-wrapper">
            <div class="card-body card-widget-separator">
                <div class="row gy-4 gy-sm-1">
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0"><?= htmlspecialchars((string) ($risk_level ?? '--')) ?></h4>
                                <p class="mb-0">Risk level</p>
                            </div>
                            <div class="avatar me-sm-6">
                                <span class="avatar-initial rounded bg-label-danger text-heading"><i class="bi bi-activity"></i></span>
                            </div>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none me-6">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0"><?= $scoreReady ? number_format($riskScoreValue, 0) . '/15' : '--' ?></h4>
                                <p class="mb-0">Reviewed score</p>
                            </div>
                            <div class="avatar me-lg-6">
                                <span class="avatar-initial rounded bg-label-primary text-heading"><i class="bi bi-speedometer2"></i></span>
                            </div>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0"><?= count($bodyRegionScores) ?></h4>
                                <p class="mb-0">Regions scored</p>
                            </div>
                            <div class="avatar me-sm-6">
                                <span class="avatar-initial rounded bg-label-warning text-heading"><i class="bi bi-person-lines-fill"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0"><?= number_format($highestRegionScore, 0) ?>/5</h4>
                                <p class="mb-0">Peak region score</p>
                            </div>
                            <div class="avatar">
                                <span class="avatar-initial rounded bg-label-success text-heading"><i class="bi bi-arrow-up-right"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Assessment Summary</h5>
                    <p class="text-muted small mb-0">Core context carried into exports and downstream corrective action reporting.</p>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small text-muted">Assessment UUID</div>
                            <div class="fw-semibold text-break"><?= htmlspecialchars((string) $uuid) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Organization</div>
                            <div class="fw-semibold"><?= htmlspecialchars((string) ($organization ?? 'WorkEddy')) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Worksite</div>
                            <div class="fw-semibold"><?= htmlspecialchars((string) ($worksite ?? '--')) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Assessment date</div>
                            <div class="fw-semibold"><?= htmlspecialchars((string) ($date ?? '--')) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Task</div>
                            <div class="fw-semibold"><?= htmlspecialchars((string) ($task ?? '--')) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Assessor</div>
                            <div class="fw-semibold"><?= htmlspecialchars((string) ($assessor ?? '--')) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Reviewer</div>
                            <div class="fw-semibold"><?= htmlspecialchars((string) ($reviewer ?? '--')) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Score publication</div>
                            <div class="fw-semibold"><?= $scoreReady ? 'Reviewer confirmed' : 'Pending reviewer confirmation' ?></div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-xl-4">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">AI Advisory</h5>
                    <p class="text-muted small mb-0">Support signal only. Reporting stays anchored to reviewed outcomes.</p>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="badge <?= !empty($aiAdvisory['available']) ? 'bg-label-success' : 'bg-label-secondary' ?>">
                            <?= !empty($aiAdvisory['available']) ? 'Available' : 'Unavailable' ?>
                        </span>
                    </div>
                    <p class="text-muted mb-3"><?= htmlspecialchars((string) ($aiAdvisory['message'] ?? 'AI advisory unavailable.')) ?></p>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Model version</span>
                        <strong><?= htmlspecialchars((string) ($aiAdvisory['model_version'] ?? '--')) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span class="text-muted">Confidence</span>
                        <strong><?= isset($aiAdvisory['confidence']) ? number_format((float) $aiAdvisory['confidence'], 2) : '--' ?></strong>
                    </div>
                    <div class="mt-3">
                        <div class="small text-muted mb-1">Score progress</div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-danger" style="width: <?= max(0, min(100, $riskBarWidth)) ?>%;"></div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Body Region Exposure</h5>
                    <p class="text-muted small mb-0">Segment scores out of 5 for the reviewed task cycle.</p>
                </div>
                <div class="card-body">
                    <?php foreach ($bodyRegionScores as $part => $score): ?>
                        <?php
                        $fillClass = $score >= 4 ? 'bg-danger' : ($score >= 3 ? 'bg-warning' : 'bg-success');
                        $pct = min(100, ((float) $score / 5) * 100);
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-semibold"><?= htmlspecialchars(ucfirst((string) $part)) ?></span>
                                <span class="text-muted"><?= htmlspecialchars((string) $score) ?> / 5</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar <?= $fillClass ?>" style="width: <?= $pct ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($bodyRegionScores === []): ?>
                        <div class="text-muted">No body-region score data available for this report.</div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <div class="col-xl-6">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Risk Factors</h5>
                    <p class="text-muted small mb-0">Observed contributors driving the published risk position.</p>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php foreach ($riskFactors as $factor): ?>
                            <div class="col-12">
                                <div class="border rounded-3 px-3 py-2 h-100">
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars((string) $factor) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($riskFactors === []): ?>
                            <div class="col-12 text-muted">No explicit risk factors were recorded.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <section class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Corrective Direction</h5>
                    <p class="text-muted small mb-0">Recommendations ready to hand off into corrective action tracking.</p>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($recommendations as $recommendation): ?>
                            <div class="col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="d-flex align-items-start gap-2">
                                        <span class="badge bg-label-success mt-1">Action</span>
                                        <div class="fw-semibold"><?= htmlspecialchars((string) $recommendation) ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($recommendations === []): ?>
                            <div class="col-12 text-muted">No recommendations are attached to this report.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
