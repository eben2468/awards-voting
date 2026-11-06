/**
 * Tappable choices for the vote and ticket pop-ups.
 *
 * Each tile group mirrors a real <select>, which stays in the form with its
 * value and "required" rule. The payment code keeps reading those selects, so
 * this file only changes how a choice is made, never what gets submitted.
 */
(function () {
    'use strict';

    // Works from any folder the site is installed in
    var LOGOS = new URL('../img/payment/', document.currentScript.src).href;

    var METHODS = {
        MTN: { label: 'MTN MoMo', logos: ['mtn.svg'], className: 'is-mtn' },
        TELECEL: { label: 'Telecel Cash', logos: ['telecel.png'] },
        AT: { label: 'AT Money', logos: ['at.png'] },
        CARD: { label: 'Card', logos: ['visa.svg', 'mastercard.svg'], className: 'is-card' }
    };

    function make(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function selectFor(group) {
        return document.getElementById(group.dataset.payFor);
    }

    function clearError(field) {
        if (!field) return;
        field.classList.remove('has-error');
        var error = field.querySelector('.pay-error');
        if (error) error.hidden = true;
    }

    function tileFace(option) {
        var face = make('span', 'pay-face');
        var method = METHODS[option.value];

        if (method) {
            var mark = make('span', 'pay-mark ' + (method.className || ''));
            method.logos.forEach(function (file) {
                var logo = make('img');
                logo.src = LOGOS + file;
                logo.alt = '';
                mark.appendChild(logo);
            });
            face.append(mark, make('span', 'pay-name', method.label));
        } else {
            var price = parseFloat(option.dataset.price || 0).toFixed(2);
            face.append(make('span', 'pay-name', option.dataset.name || option.textContent), make('span', 'pay-price', 'GHS ' + price));
        }
        return face;
    }

    function render(group) {
        var select = selectFor(group);
        var options = Array.prototype.filter.call(select.options, function (option) { return option.value !== ''; });

        group.replaceChildren.apply(group, options.map(function (option) {
            var tile = make('label', 'pay-tile');
            var radio = make('input');
            radio.type = 'radio';
            radio.name = group.dataset.payFor + 'Tile';
            radio.value = option.value;
            radio.checked = select.value === option.value;
            radio.addEventListener('change', function () {
                select.value = option.value;
                clearError(group.closest('.pay-field'));
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
            tile.append(radio, tileFace(option));
            return tile;
        }));

        // One ticket type leaves nothing to choose
        if ('payAuto' in group.dataset && options.length === 1 && !select.value) {
            group.querySelector('input').click();
        }
    }

    function sync(group) {
        var select = selectFor(group);
        group.querySelectorAll('input').forEach(function (radio) {
            radio.checked = radio.value === select.value;
        });
    }

    function syncQuick(input) {
        var quick = input.closest('.pay-field').querySelector('.pay-quick');
        if (!quick) return;
        quick.querySelectorAll('button').forEach(function (button) {
            button.classList.toggle('is-active', button.dataset.qty === input.value);
        });
    }

    document.querySelectorAll('[data-pay-for]').forEach(function (group) {
        render(group);
        new MutationObserver(function () { render(group); }).observe(selectFor(group), { childList: true });
    });

    document.querySelectorAll('.pay-quick').forEach(function (quick) {
        var input = quick.closest('.pay-field').querySelector('input[type="number"]');
        quick.addEventListener('click', function (event) {
            var button = event.target.closest('[data-qty]');
            if (!button) return;
            input.value = button.dataset.qty;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            syncQuick(input);
        });
        input.addEventListener('input', function () { syncQuick(input); });
        input.closest('.pay-field').addEventListener('click', function () { syncQuick(input); });
    });

    // Accept a number however it is typed or pasted, and keep just the local part
    document.querySelectorAll('.pay-phone').forEach(function (input) {
        input.addEventListener('input', function () {
            var digits = input.value.replace(/\D/g, '');
            if (digits.indexOf('233') === 0 && digits.length > 10) digits = digits.slice(3);
            input.value = digits.slice(0, 10);
            clearError(input.closest('.pay-field'));
        });
    });

    /**
     * Show the dial code on the waiting screen, for anyone who gets no prompt.
     */
    window.showPayApproval = function (code) {
        document.querySelectorAll('[data-approval]').forEach(function (note) {
            note.hidden = !code;
            if (!code) return;
            var link = note.querySelector('[data-approval-link]');
            link.textContent = 'Dial ' + code;
            link.href = 'tel:' + code.replace(/#/g, '%23');
        });
    };

    // The payment code resets the selects when a pop-up opens
    document.addEventListener('show.bs.modal', function (event) {
        window.showPayApproval('');
        event.target.querySelectorAll('[data-pay-for]').forEach(sync);
        event.target.querySelectorAll('.pay-field').forEach(clearError);
        event.target.querySelectorAll('.pay-quick').forEach(function (quick) {
            syncQuick(quick.closest('.pay-field').querySelector('input[type="number"]'));
        });
    });

    // Show the same inline message for a hidden select or a wrong phone number
    document.addEventListener('invalid', function (event) {
        if (!event.target.matches || !event.target.matches('.pay-native, .pay-phone')) return;
        event.preventDefault();
        var field = event.target.closest('.pay-field');
        field.classList.add('has-error');
        field.querySelector('.pay-error').hidden = false;
        field.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }, true);
})();
