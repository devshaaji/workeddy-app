/**
 * WorkEddy v2 — App Shell Runtime
 *
 * Initialises the authenticated layout shell:
 *   • Sidebar mobile toggle / overlay
 *   • Organization-scoped link resolution
 *   • Notification dropdown + API fetch
 *   • Submenu (sidebar dropdown) toggle
 *   • Logout data-attribute handler
 *
 * Loaded by app.php AFTER bootstrap.bundle and app.js.
 * All dependencies (App.api, App.utils, App.notify) come from app.js.
 */
(function (window, document) {
    'use strict';

    var shellHelpers = {
        templateName: document.documentElement.getAttribute('data-template') || 'vertical-menu-template',
        getStoredTheme: function () {
            try {
                return window.localStorage.getItem('templateCustomizer-' + this.templateName + '--Theme');
            } catch (_) {
                return null;
            }
        },
        setStoredTheme: function (theme) {
            try {
                window.localStorage.setItem('templateCustomizer-' + this.templateName + '--Theme', theme);
            } catch (_) {
                /* ignore storage failures */
            }
        },
        getPreferredTheme: function () {
            var storedTheme = this.getStoredTheme();
            if (storedTheme) {
                return storedTheme;
            }
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        },
        resolveTheme: function (theme) {
            if (theme === 'system') {
                return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            return theme === 'dark' ? 'dark' : 'light';
        },
        setTheme: function (theme) {
            document.documentElement.setAttribute('data-bs-theme', this.resolveTheme(theme));
        },
        showActiveTheme: function (theme) {
            var navTheme = document.querySelector('#nav-theme');
            var themeText = document.querySelector('#nav-theme-text');
            var themeIcon = document.querySelector('.theme-icon-active');
            var activeButton = document.querySelector('[data-bs-theme-value="' + theme + '"]');

            document.querySelectorAll('[data-bs-theme-value]').forEach(function (button) {
                button.classList.remove('active');
                button.setAttribute('aria-pressed', 'false');
            });

            if (!activeButton) {
                return;
            }

            activeButton.classList.add('active');
            activeButton.setAttribute('aria-pressed', 'true');

            if (themeIcon) {
                var iconName = theme === 'dark' ? 'moon-fill' : (theme === 'system' ? 'display' : 'sun-fill');
                themeIcon.className = 'bi bi-' + iconName + ' icon-base icon-md theme-icon-active';
            }

            if (navTheme && themeText) {
                navTheme.setAttribute('aria-label', themeText.textContent + ' (' + theme + ')');
            }
        },
        refreshRuntimeColors: function () {
            if (!window.config || !window.Helpers || !window.Helpers.getCssVar) {
                return;
            }

            window.config.colors = window.config.colors || {};
            window.config.colors.primary = window.Helpers.getCssVar('primary');
            window.config.colors.secondary = window.Helpers.getCssVar('secondary');
            window.config.colors.success = window.Helpers.getCssVar('success');
            window.config.colors.info = window.Helpers.getCssVar('info');
            window.config.colors.warning = window.Helpers.getCssVar('warning');
            window.config.colors.danger = window.Helpers.getCssVar('danger');
            window.config.colors.dark = window.Helpers.getCssVar('dark');
            window.config.colors.black = window.Helpers.getCssVar('pure-black');
            window.config.colors.white = window.Helpers.getCssVar('white');
            window.config.colors.cardColor = window.Helpers.getCssVar('paper-bg');
            window.config.colors.bodyBg = window.Helpers.getCssVar('body-bg');
            window.config.colors.bodyColor = window.Helpers.getCssVar('body-color');
            window.config.colors.headingColor = window.Helpers.getCssVar('heading-color');
            window.config.colors.textMuted = window.Helpers.getCssVar('secondary-color');
            window.config.colors.borderColor = window.Helpers.getCssVar('border-color');
        }
    };
    window.ShellHelpers = shellHelpers;

    // ── DOMContentLoaded guard ────────────────────────────────────────────────
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
            return;
        }
        fn();
    }

    ready(function () {

        // ── Sidebar Mobile Toggle ────────────────────────────────────────────
        var themeButtons = document.querySelectorAll('[data-bs-theme-value]');
        var themeIcon = document.getElementById('nav-theme-icon');

        function applyTheme(theme) {
            var storedTheme = theme === 'dark' || theme === 'system' ? theme : 'light';
            var effectiveTheme = shellHelpers.resolveTheme(storedTheme);
            shellHelpers.setTheme(storedTheme);
            shellHelpers.showActiveTheme(storedTheme);

            if (themeIcon) {
                themeIcon.className = effectiveTheme === 'dark'
                    ? 'bi bi-moon-fill icon-base icon-md theme-icon-active'
                    : 'bi bi-sun-fill icon-base icon-md theme-icon-active';
            }
            shellHelpers.setStoredTheme(storedTheme);
            shellHelpers.refreshRuntimeColors();
        }

        var savedTheme = shellHelpers.getPreferredTheme();
        applyTheme(savedTheme);

        themeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                applyTheme(button.getAttribute('data-bs-theme-value') || 'light');
            });
        });

        if (window.matchMedia) {
            var systemThemeMedia = window.matchMedia('(prefers-color-scheme: dark)');
            var handleSystemThemeChange = function () {
                if (shellHelpers.getStoredTheme() === 'system') {
                    applyTheme('system');
                }
            };
            if (systemThemeMedia.addEventListener) {
                systemThemeMedia.addEventListener('change', handleSystemThemeChange);
            } else if (systemThemeMedia.addListener) {
                systemThemeMedia.addListener(handleSystemThemeChange);
            }
        }

        var sidebar = document.getElementById('layout-menu');
        var overlay = document.getElementById('layoutOverlay');
        var toggle = document.getElementById('sidebarToggle');
        var desktopToggle = document.getElementById('sidebarDesktopToggle');
        var html = document.documentElement;
        var desktopBreakpoint = window.matchMedia ? window.matchMedia('(min-width: 1200px)') : null;

        function isDesktop() {
            return desktopBreakpoint ? desktopBreakpoint.matches : window.innerWidth >= 1200;
        }

        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('layout-menu-expanded');
            if (html) html.classList.remove('layout-menu-expanded');
            if (overlay) overlay.classList.remove('show');
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                if (isDesktop()) {
                    html.classList.toggle('layout-menu-collapsed');
                    return;
                }
                if (sidebar) sidebar.classList.toggle('layout-menu-expanded');
                if (html) html.classList.toggle('layout-menu-expanded');
                if (overlay) overlay.classList.toggle('show');
            });
        }
        if (desktopToggle) {
            desktopToggle.addEventListener('click', function () {
                if (isDesktop()) {
                    html.classList.toggle('layout-menu-collapsed');
                    return;
                }
                if (sidebar) sidebar.classList.toggle('layout-menu-expanded');
                if (html) html.classList.toggle('layout-menu-expanded');
                if (overlay) overlay.classList.toggle('show');
            });
        }
        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        // ── Org UUID Link Resolution ─────────────────────────────────────────
        var orgMeta = document.querySelector('meta[name="org-uuid"]');
        var orgUuid = orgMeta ? orgMeta.getAttribute('content') : '';
        if (orgUuid) {
            document.querySelectorAll('.we-org-link').forEach(function (link) {
                var path = link.getAttribute('href');
                if (path && path.charAt(0) === '#') {
                    var page = path.substring(1);
                    link.href = '/organizations/' + orgUuid + '/' + page;
                }
            });
        }

        // ── Sidebar Submenu Toggle (Sneat-style) ────────────────────────────
        document.querySelectorAll('.menu-toggle').forEach(function (toggleLink) {
            toggleLink.addEventListener('click', function (e) {
                e.preventDefault();
                var parentItem = toggleLink.closest('.menu-item');
                if (!parentItem) return;
                var sub = parentItem.querySelector('.menu-sub');
                if (!sub) return;
                var currentlyOpen = !sub.classList.contains('d-none');
                sub.classList.toggle('d-none', currentlyOpen);
                parentItem.classList.toggle('open', !currentlyOpen);
                var chevron = toggleLink.querySelector('.menu-chevron');
                if (chevron) chevron.classList.toggle('rotated', !currentlyOpen);
            });
        });

        // ── Logout Handler ───────────────────────────────────────────────────
        document.querySelectorAll('[data-app-logout]').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.App && App.notify) {
                    App.notify.info('Signing out…');
                }
            });
        });

        // ── Notifications Dropdown ───────────────────────────────────────────
        var notifToggle = document.getElementById('notifToggle');
        var notifMenu = document.getElementById('notifMenu');
        var notifBody = document.getElementById('notifBody');
        var notifBadge = document.getElementById('notifBadge');
        var notifMarkAll = document.getElementById('notifMarkAllRead');
        var notifLoading = document.getElementById('notifLoading');
        var dropdownOpen = false;

        function toggleNotifDropdown(event) {
            event.stopPropagation();
            dropdownOpen = !dropdownOpen;
            if (notifMenu) notifMenu.classList.toggle('show', dropdownOpen);
            if (dropdownOpen && notifBody && !notifBody.dataset.loaded) {
                fetchNotifications();
            }
        }

        function closeNotifDropdown() {
            dropdownOpen = false;
            if (notifMenu) notifMenu.classList.remove('show');
        }

        if (notifToggle) {
            notifToggle.addEventListener('click', toggleNotifDropdown);
        }

        document.addEventListener('click', function (event) {
            var dd = document.getElementById('notificationsDropdown');
            if (dropdownOpen && dd && !dd.contains(event.target)) {
                closeNotifDropdown();
            }
        });

        function fetchNotifications() {
            if (!notifBody) return;
            notifBody.dataset.loaded = '1';
            if (notifLoading) notifLoading.style.display = '';

            if (typeof App !== 'undefined' && App.api) {
                App.api.get('/api/v1/notifications').then(function (res) {
                    var items = [];
                    if (res.ok && Array.isArray(res.data)) {
                        items = res.data;
                    } else if (res.ok && res.data && Array.isArray(res.data.notifications)) {
                        items = res.data.notifications;
                    }

                    var unread = items.filter(function (n) { return !parseInt(n.is_read); }).length;
                    if (notifBadge) {
                        notifBadge.textContent = unread > 9 ? '9+' : String(unread);
                        notifBadge.classList.toggle('d-none', unread === 0);
                    }
                    if (notifMarkAll) notifMarkAll.classList.toggle('d-none', unread === 0);

                    if (items.length === 0) {
                        notifBody.innerHTML =
                            '<div class="text-center py-4 text-muted">' +
                            '<i class="bi bi-bell-slash d-block mb-1" style="font-size:1.5rem"></i>' +
                            '<span class="small">No notifications yet</span></div>';
                        return;
                    }

                    var html = '';
                    items.forEach(function (n) {
                        var isUnread = !parseInt(n.is_read);
                        var iconCls = notifIconClass(n.type);
                        var icon = notifIcon(n.type);
                        var link = n.link || '#';
                        var esc = (typeof App !== 'undefined' && App.utils) ? App.utils.escapeHtml : function (s) { return s; };
                        var title = esc(n.title || '');
                        var body = n.body
                            ? '<small class="mb-1 d-block text-body">' + esc(n.body) + '</small>'
                            : '';
                        var time = timeAgo(n.created_at);

                        html +=
                            '<a class="dropdown-item list-group-item list-group-item-action dropdown-notifications-item' + (isUnread ? '' : ' marked-as-read') + '" href="' + link + '">' +
                            '<div class="d-flex">' +
                            '<div class="flex-shrink-0 me-3">' +
                            '<div class="avatar ' + iconCls + '">' +
                            '<i class="bi ' + icon + '"></i>' +
                            '</div>' +
                            '</div>' +
                            '<div class="flex-grow-1">' +
                            '<h6 class="small mb-0">' + title + '</h6>' +
                            body +
                            '<small class="text-body-secondary">' + time + '</small>' +
                            '</div>' +
                            '<div class="flex-shrink-0 dropdown-notifications-actions">' +
                            '<a href="javascript:void(0)" class="dropdown-notifications-read"><span class="badge badge-dot"></span></a>' +
                            '<a href="javascript:void(0)" class="dropdown-notifications-archive"><span class="bi bi-x-lg"></span></a>' +
                            '</div>' +
                            '</div>' +
                            '</a>';
                    });

                    notifBody.innerHTML = html;
                })['catch'](function () {
                    if (notifBody) {
                        notifBody.innerHTML = '<div class="text-center py-4 text-muted">Failed to load notifications.</div>';
                    }
                });
            }
        }

        // ── Notification helpers (exposed globally for page scripts) ─────────
        function notifIconClass(type) {
            var map = { success: 'bg-success', danger: 'bg-danger', warning: 'bg-warning', info: 'bg-info', primary: 'bg-primary' };
            return map[type] || 'bg-secondary';
        }

        function notifIcon(type) {
            var map = { success: 'bi-check-circle', danger: 'bi-x-circle', warning: 'bi-exclamation-circle', info: 'bi-info-circle', primary: 'bi-star' };
            return map[type] || 'bi-bell';
        }

        function timeAgo(dateStr) {
            if (!dateStr) return '';
            var d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            var now = new Date();
            var diff = Math.floor((now - d) / 1000);
            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
        }

        window.we = window.we || {};
        window.we.notifIconClass = notifIconClass;
        window.we.notifIcon = notifIcon;
        window.we.timeAgo = timeAgo;
    });

            function getDashboardMessages() {
             const hour = new Date().getHours();

            let greeting;
            let warmMessage;

            if (hour >= 5 && hour < 12) {
                greeting = "Good morning";
                warmMessage = "Review pending assessments and corrective actions.";
            } else if (hour >= 12 && hour < 17) {
                greeting = "Good afternoon";
                warmMessage = "Check high-risk tasks and reviewer queue.";
            } else if (hour >= 17 && hour < 21) {
                greeting = "Good evening";
                warmMessage = "Review today's completed assessments and follow-ups.";
            } else {
                greeting = "Good night";
                warmMessage = "Plan tomorrow's ergonomic assessments.";
            }

            return [greeting, warmMessage];
         }

        window.dashboardMessages = getDashboardMessages();
        

})(window, document);
