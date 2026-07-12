(function () {
    'use strict';

    var page = document.querySelector('.methodology-tree-page');
    if (!page) {
        return;
    }

    var branches = Array.prototype.slice.call(page.querySelectorAll('.methodology-tree-branch'));
    var sections = branches
        .map(function (branch) {
            var id = (branch.getAttribute('href') || '').replace('#', '');
            var target = id ? document.getElementById(id) : null;
            return target ? { branch: branch, target: target } : null;
        })
        .filter(Boolean);

    if (sections.length === 0) {
        return;
    }

    var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var activeIndex = -1;
    var ticking = false;

    // Smooth-scroll to a section when its nav branch is clicked.
    branches.forEach(function (branch) {
        branch.addEventListener('click', function (event) {
            var id = (branch.getAttribute('href') || '').replace('#', '');
            var target = id ? document.getElementById(id) : null;
            if (!target) {
                return;
            }
            event.preventDefault();
            target.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
            history.replaceState(null, '', '#' + id);
        });
    });

    var setActive = function (activeIndex) {
        if (activeIndex === -1) {
            return;
        }

        sections.forEach(function (entry, index) {
            entry.branch.classList.toggle('is-passed', index < activeIndex);
            entry.branch.classList.toggle('is-active', index === activeIndex);
        });

        if (sections[activeIndex] && sections[activeIndex].branch) {
            history.replaceState(null, '', sections[activeIndex].branch.getAttribute('href') || window.location.pathname);
        }
    };

    var getActiveIndex = function () {
        var scrollPosition = window.pageYOffset || document.documentElement.scrollTop || 0;
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        var trackingLine = scrollPosition + Math.max(180, viewportHeight * 0.34);
        var nextIndex = 0;

        sections.forEach(function (entry, index) {
            var rect = entry.target.getBoundingClientRect();
            var top = rect.top + scrollPosition;
            var bottom = rect.bottom + scrollPosition;
            if (top <= trackingLine && bottom > trackingLine) {
                nextIndex = index;
            } else if (top <= trackingLine) {
                nextIndex = index;
            }
        });

        return nextIndex;
    };

    var syncActiveState = function () {
        ticking = false;
        var nextIndex = getActiveIndex();
        if (nextIndex !== activeIndex) {
            activeIndex = nextIndex;
            setActive(activeIndex);
        }
    };

    var requestSync = function () {
        if (ticking) {
            return;
        }
        ticking = true;
        window.requestAnimationFrame(syncActiveState);
    };

    window.addEventListener('scroll', requestSync, { passive: true });
    window.addEventListener('resize', requestSync);
    requestSync();

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(requestSync, {
            rootMargin: '0px',
            threshold: [0, 0.25, 0.5, 0.75, 1]
        });

        sections.forEach(function (entry) {
            observer.observe(entry.target);
        });
    }
})();
