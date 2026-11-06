/**
 * Homepage: the countdown, the Now Showing marquee and the scroll-linked stage.
 * The page reads correctly without this: the numbers and first poster are in the HTML.
 */
(function () {
    var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* --- Countdown --------------------------------------------------------- */
    var flip = document.querySelector('.flip');

    if (flip) {
        var deadline = Number(flip.dataset.deadline) * 1000;
        var tiles = {};
        flip.querySelectorAll('.flip-tile').forEach(function (tile) {
            tiles[tile.dataset.unit] = tile;
            if (!calm) {
                tile.addEventListener('animationend', function () { tile.classList.remove('is-ticking'); });
            }
        });

        var show = function (unit, value) {
            var text = String(value).padStart(2, '0');
            if (tiles[unit].textContent !== text) {
                tiles[unit].textContent = text;
                if (!calm) tiles[unit].classList.add('is-ticking');
            }
        };

        var tick = function () {
            if (document.hidden) return;

            var left = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
            show('days', Math.floor(left / 86400));
            show('hours', Math.floor((left % 86400) / 3600));
            show('minutes', Math.floor((left % 3600) / 60));
            show('seconds', left % 60);
        };

        tick();
        setInterval(tick, 1000);
    }

    /* --- Stage ------------------------------------------------------------- */
    var hero = document.querySelector('.hx');
    if (!hero) return;

    // The curtains open once per visit
    try {
        sessionStorage.setItem('vh_intro', '1');
    } catch (error) { /* private window: they simply open each time */ }

    /* --- Now Showing marquee ------------------------------------------------ */
    var marquee = hero.querySelector('[data-marquee]');
    var slides = marquee ? marquee.querySelectorAll('.mq-slide') : [];
    var infos = marquee ? marquee.querySelectorAll('.mq-info') : [];
    var thumbs = marquee ? marquee.querySelectorAll('.mq-thumb') : [];
    var current = 0;
    var holding = false;
    var progress = 0;

    function goTo(index) {
        current = index;

        slides.forEach(function (slide, i) {
            slide.classList.toggle('is-on', i === index);
            slide.tabIndex = i === index ? 0 : -1;
        });
        infos.forEach(function (info, i) {
            info.classList.toggle('is-on', i === index);
            info.querySelector('a').tabIndex = i === index ? 0 : -1;
        });
        thumbs.forEach(function (thumb, i) {
            thumb.classList.toggle('is-on', i === index);
        });
    }

    thumbs.forEach(function (thumb) {
        thumb.addEventListener('click', function (event) {
            event.preventDefault();
            goTo(Number(thumb.dataset.slide));
        });
    });

    if (marquee && slides.length > 1) {
        marquee.addEventListener('pointerenter', function () { holding = true; });
        marquee.addEventListener('pointerleave', function () { holding = false; });
        marquee.addEventListener('focusin', function () { holding = true; });
        marquee.addEventListener('focusout', function () { holding = false; });

        if (!calm) {
            setInterval(function () {
                if (document.hidden || holding || progress > 0.02) return;
                goTo((current + 1) % slides.length);
            }, 5000);
        }
    }

    if (calm) return;

    /* --- Scroll ------------------------------------------------------------ */
    var root = document.documentElement;
    var pin = hero.parentElement;
    var pinDistance = 0;
    var heroHeight = 0;
    var pageDistance = 1;
    var queued = false;

    function clamp(value) {
        return Math.min(1, Math.max(0, value));
    }

    function smooth(value) {
        return value * value * (3 - 2 * value);
    }

    function measure() {
        heroHeight = hero.offsetHeight;
        pinDistance = pin.offsetHeight - heroHeight;
        pageDistance = Math.max(1, root.scrollHeight - window.innerHeight);
    }

    function paint() {
        queued = false;
        var y = window.scrollY;
        var pinned = pinDistance > 1;

        progress = clamp(y / (pinned ? pinDistance : heroHeight));
        hero.style.setProperty('--p', progress.toFixed(3));
        // The curtains draw closed over the last part of the pinned scroll
        var closed = pinned ? smooth(clamp((progress - 0.35) / 0.65)) : 0;
        hero.style.setProperty('--c', closed.toFixed(3));
        hero.classList.toggle('is-closed', closed > 0.94);
        hero.classList.toggle('is-resting', closed > 0.94 || y > pinDistance + heroHeight);
        root.style.setProperty('--read', clamp(y / pageDistance).toFixed(4));
    }

    function queuePaint() {
        if (!queued) {
            queued = true;
            requestAnimationFrame(paint);
        }
    }

    measure();
    paint();
    window.addEventListener('scroll', queuePaint, { passive: true });
    window.addEventListener('resize', function () {
        measure();
        queuePaint();
    });
    // Images and fonts change the page height after the first measure
    window.addEventListener('load', function () {
        measure();
        queuePaint();
    });
})();
