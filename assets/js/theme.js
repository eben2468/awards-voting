/**
 * Public site motion: scroll reveals and the how-it-works tab state.
 * Everything here is decoration. The site works the same without it.
 */
(function () {
    var items = document.querySelectorAll('.rv');

    if (!('IntersectionObserver' in window)) {
        items.forEach(function (el) { el.classList.add('in'); });
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('in');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    items.forEach(function (el) { observer.observe(el); });

    // A hidden tab has no size, so reveal its steps when it is opened
    document.querySelectorAll('[data-bs-toggle="pill"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            var pane = document.querySelector(tab.getAttribute('data-bs-target'));
            if (pane) {
                pane.querySelectorAll('.rv').forEach(function (el) { el.classList.add('in'); });
            }
        });
    });
})();

// Keep the chosen event in view when the results list scrolls on small screens
(function () {
    var list = document.querySelector('.results-side .list-group');
    var active = list && list.querySelector('.active');
    if (active) {
        list.scrollTop = active.offsetTop - list.offsetTop;
    }
})();
