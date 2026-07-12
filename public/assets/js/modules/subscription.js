/**
 * subscription.js — Subscription Module Frontend Engine
 */
(function () {
    'use strict';

    if (!window.App) { return; }

    function e(v) { return App.utils.escapeHtml(v === null || v === undefined ? '' : String(v)); }

    // ── 1. Index Page Logic ──────────────────────────────────────────────────
    var indexPage = document.getElementById('subscriptionIndexPage');
    if (indexPage) {
        var tbody = document.getElementById('subscriptionsBody');
        var planFilter = document.getElementById('filter-plan');
        var statusFilter = document.getElementById('filter-status');
        var allSubscriptions = [];

        var renderSubscriptions = function () {
            var selectedPlan = planFilter ? planFilter.value : '';
            var selectedStatus = statusFilter ? statusFilter.value : '';

            var filtered = allSubscriptions.filter(function (sub) {
                var matchPlan = !selectedPlan || sub.plan_code === selectedPlan;
                var matchStatus = !selectedStatus || sub.status === selectedStatus;
                return matchPlan && matchStatus;
            });

            if (!filtered.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">' +
                    '<i class="bi bi-rocket-takeoff fs-3 d-block mb-2 opacity-50"></i>' +
                    'No subscriptions found matching the filters.</td></tr>';
                return;
            }

            tbody.innerHTML = filtered.map(function (sub) {
                var statusClass = 'secondary';
                if (sub.status === 'active') statusClass = 'success';
                else if (sub.status === 'suspended') statusClass = 'warning';
                else if (sub.status === 'expired') statusClass = 'danger';

                var renewBadge = sub.auto_renew ? 
                    '<span class="badge bg-label-success">Yes</span>' : 
                    '<span class="badge bg-label-secondary">No</span>';

                var actions = [
                    { label: 'View Details', onclick: 'window.location.href="/subscriptions/' + sub.uuid + '"' }
                ];

                if (sub.status === 'active') {
                    actions.push({ label: 'Suspend', onclick: 'SubscriptionIndex.suspend("' + sub.uuid + '")' });
                    actions.push({ label: 'Cancel', class: 'text-danger', onclick: 'SubscriptionIndex.cancel("' + sub.uuid + '")' });
                } else if (sub.status === 'suspended' || sub.status === 'cancelled') {
                    actions.push({ label: 'Reactivate', onclick: 'SubscriptionIndex.reactivate("' + sub.uuid + '")' });
                }

                return '<tr>' +
                    '<td><a href="/subscriptions/' + sub.uuid + '" class="fw-medium text-truncate d-inline-block" style="max-width: 180px;">' + e(sub.organization_uuid) + '</a></td>' +
                    '<td><span class="badge bg-label-primary">' + e(sub.plan_name) + '</span></td>' +
                    '<td><span class="badge bg-label-' + statusClass + '">' + e(sub.status.toUpperCase()) + '</span></td>' +
                    '<td class="text-capitalize">' + e(sub.billing_cycle) + '</td>' +
                    '<td>' + renewBadge + '</td>' +
                    '<td>' + e(sub.expiry_date || 'Never') + '</td>' +
                    '<td class="text-end">' + App.tables.actionDropdown(actions) + '</td>' +
                    '</tr>';
            }).join('');
        };

        var loadSubscriptions = function () {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading…</td></tr>';
            App.api.get('/api/v1/subscriptions').then(function (res) {
                if (res.ok && Array.isArray(res.data.subscriptions)) {
                    allSubscriptions = res.data.subscriptions;
                    renderSubscriptions();
                } else {
                    App.notify.error('Failed to load subscriptions.');
                }
            });
        };

        window.SubscriptionIndex = {
            suspend: function (uuid) {
                App.modals.confirm({
                    title: 'Suspend Subscription',
                    message: 'Are you sure you want to suspend this subscription? The organization will lose access to premium features.',
                    confirmText: 'Suspend',
                    confirmClass: 'btn-warning',
                    onConfirm: function () {
                        App.api.post('/api/v1/subscriptions/' + uuid + '/suspend', { reason: 'Suspended via dashboard' }).then(function (res) {
                            if (res.ok) {
                                App.notify.success('Subscription suspended.');
                                loadSubscriptions();
                            } else {
                                App.notify.error(res.message || 'Operation failed.');
                            }
                        });
                    }
                });
            },
            cancel: function (uuid) {
                App.modals.confirm({
                    title: 'Cancel Subscription',
                    message: 'Are you sure you want to cancel this subscription? Action cannot be undone.',
                    confirmText: 'Cancel',
                    confirmClass: 'btn-danger',
                    onConfirm: function () {
                        App.api.post('/api/v1/subscriptions/' + uuid + '/cancel', { reason: 'Cancelled via dashboard' }).then(function (res) {
                            if (res.ok) {
                                App.notify.success('Subscription cancelled.');
                                loadSubscriptions();
                            } else {
                                App.notify.error(res.message || 'Operation failed.');
                            }
                        });
                    }
                });
            },
            reactivate: function (uuid) {
                App.api.post('/api/v1/subscriptions/' + uuid + '/reactivate').then(function (res) {
                    if (res.ok) {
                        App.notify.success('Subscription reactivated.');
                        loadSubscriptions();
                    } else {
                        App.notify.error(res.message || 'Operation failed.');
                    }
                });
            }
        };

        if (planFilter) { planFilter.addEventListener('change', renderSubscriptions); }
        if (statusFilter) { statusFilter.addEventListener('change', renderSubscriptions); }

        loadSubscriptions();
    }

    // ── 2. Detail Page Logic ─────────────────────────────────────────────────
    var detailPage = document.getElementById('subscriptionDetailPage');
    if (detailPage) {
        var subUuid = detailPage.getAttribute('data-uuid');

        var btnSuspend = document.getElementById('btn-suspend-subscription');
        var btnCancel = document.getElementById('btn-cancel-subscription');
        var btnReactivate = document.getElementById('btn-reactivate-subscription');
        var planSelectionButtons = document.querySelectorAll('.btn-select-plan');

        if (btnSuspend) {
            btnSuspend.addEventListener('click', function () {
                App.modals.confirm({
                    title: 'Suspend Subscription',
                    message: 'Are you sure you want to suspend this subscription? The organization will lose access to premium features.',
                    confirmText: 'Suspend',
                    confirmClass: 'btn-warning',
                    onConfirm: function () {
                        App.api.post('/api/v1/subscriptions/' + subUuid + '/suspend', { reason: 'Suspended by administrative action' }).then(function (res) {
                            if (res.ok) {
                                App.notify.success('Subscription suspended.');
                                window.location.reload();
                            } else {
                                App.notify.error(res.message || 'Suspension failed.');
                            }
                        });
                    }
                });
            });
        }

        if (btnCancel) {
            btnCancel.addEventListener('click', function () {
                App.modals.confirm({
                    title: 'Cancel Subscription',
                    message: 'Are you sure you want to cancel this subscription? The organization will lose premium tier features immediately or at the end of the term.',
                    confirmText: 'Cancel',
                    confirmClass: 'btn-danger',
                    onConfirm: function () {
                        App.api.post('/api/v1/subscriptions/' + subUuid + '/cancel', { reason: 'Cancelled by administrator' }).then(function (res) {
                            if (res.ok) {
                                App.notify.success('Subscription cancelled.');
                                window.location.reload();
                            } else {
                                App.notify.error(res.message || 'Cancellation failed.');
                            }
                        });
                    }
                });
            });
        }

        if (btnReactivate) {
            btnReactivate.addEventListener('click', function () {
                App.api.post('/api/v1/subscriptions/' + subUuid + '/reactivate').then(function (res) {
                    if (res.ok) {
                        App.notify.success('Subscription reactivated.');
                        window.location.reload();
                    } else {
                        App.notify.error(res.message || 'Reactivation failed.');
                    }
                });
            });
        }

        planSelectionButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var newPlanCode = btn.getAttribute('data-plan');
                App.ui.setButtonLoading(btn, true);
                App.api.post('/api/v1/subscriptions/' + subUuid + '/change-plan', { plan_code: newPlanCode }).then(function (res) {
                    App.ui.setButtonLoading(btn, false, 'Select Plan');
                    if (res.ok) {
                        App.notify.success('Subscription plan changed successfully.');
                        App.modals.close('#changePlanModal');
                        window.location.reload();
                    } else {
                        App.notify.error(res.message || 'Failed to change plan.');
                    }
                });
            });
        });
    }

    // ── 3. Settings Page Logic ───────────────────────────────────────────────
    var settingsPage = document.getElementById('subscriptionSettingsPage');
    if (settingsPage) {
        var settingsForm = document.getElementById('form-subscription-settings');
        if (settingsForm) {
            App.forms.bindAjaxForm(settingsForm, {
                url: '/api/v1/subscriptions/settings',
                method: 'POST',
                submitBtn: '#btn-save-settings',
                onSuccess: function (res) {
                    App.notify.success(res.message || 'Settings saved successfully.');
                },
                onError: function (res) {
                    App.notify.error(res.message || 'Failed to save settings.');
                }
            });
        }
    }
})();
