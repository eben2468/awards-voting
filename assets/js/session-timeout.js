/**
 * Signs an idle user out, with a warning first.
 *
 * Only real interaction counts as activity. The server keeps its own timer as
 * well, so closing this tab or blocking scripts cannot keep a session alive.
 */
(function () {
    'use strict';

    var script = document.currentScript;
    var timeout = Number(script.dataset.timeout) * 1000;
    var pingUrl = script.dataset.ping;
    var logoutUrl = script.dataset.logout;
    if (!timeout) return;

    var STORE_KEY = 'votehub-last-activity';
    var warnFor = Math.min(60000, timeout / 3);
    var pingEvery = Math.min(60000, timeout / 3);
    var lastActivity = Date.now();
    var lastPing = Date.now();
    var bar = null;

    // Activity in another tab of this site counts too
    function sharedActivity() {
        try {
            return Math.max(lastActivity, Number(localStorage.getItem(STORE_KEY)) || 0);
        } catch (error) {
            return lastActivity;
        }
    }

    function ping() {
        lastPing = Date.now();
        fetch(pingUrl, { method: 'POST', credentials: 'same-origin' }).catch(function () {});
    }

    function hideBar() {
        if (bar) {
            bar.remove();
            bar = null;
        }
    }

    function showBar(seconds) {
        if (!bar) {
            bar = document.createElement('div');
            bar.setAttribute('role', 'alert');
            bar.style.cssText = 'position:fixed;left:50%;bottom:1rem;transform:translateX(-50%);z-index:2000;display:flex;align-items:center;gap:.75rem;width:max-content;max-width:calc(100vw - 2rem);padding:.7rem .85rem;border-radius:10px;background:#0e1b3d;color:#fff;font-size:.9375rem;box-shadow:0 14px 34px -12px rgba(14,27,61,.6)';

            var text = document.createElement('span');
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = 'Stay signed in';
            button.style.cssText = 'flex:none;min-height:40px;padding:.3rem .8rem;border:0;border-radius:6px;background:#e0a526;color:#0e1b3d;font-weight:600';
            button.addEventListener('click', recordActivity);

            bar.append(text, button);
            document.body.appendChild(bar);
        }
        bar.firstChild.textContent = 'Signing you out in ' + seconds + 's because you have been inactive.';
    }

    function recordActivity() {
        var now = Date.now();
        var wasWarning = bar !== null;
        lastActivity = now;
        try {
            localStorage.setItem(STORE_KEY, String(now));
        } catch (error) {
            // Private browsing can block storage; this tab still works alone
        }
        hideBar();
        if (wasWarning || now - lastPing >= pingEvery) ping();
    }

    ['mousedown', 'mousemove', 'keydown', 'touchstart', 'scroll'].forEach(function (name) {
        window.addEventListener(name, recordActivity, { passive: true });
    });

    setInterval(function () {
        var idle = Date.now() - sharedActivity();
        if (idle >= timeout) {
            window.location.href = logoutUrl;
        } else if (idle >= timeout - warnFor) {
            showBar(Math.ceil((timeout - idle) / 1000));
        } else {
            hideBar();
        }
    }, 1000);
})();
