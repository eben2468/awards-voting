/**
 * Admin Messages page.
 * The server re-checks every recipient and fills in each message, so what the
 * preview shows here is a guide, not what gets trusted.
 */
(function () {
    'use strict';

    const cfg = JSON.parse(document.getElementById('messageConfig').textContent);
    const PAGE_SIZE = 100;
    const $ = id => document.getElementById(id);

    const ui = {
        audience: $('audience'), event: $('eventFilter'), search: $('contactSearch'), note: $('contactNote'),
        list: $('contactList'), more: $('showMore'), all: $('selectAll'), allLabel: $('selectAllLabel'),
        selected: $('selectedCount'), manual: $('manualNumbers'), manualInfo: $('manualInfo'),
        message: $('message'), chips: $('suggestions'), count: $('charCount'), preview: $('preview'),
        previewLabel: $('previewLabel'), send: $('sendBtn'), sendLabel: $('sendLabel'), notice: $('notice'),
        test: $('testNumber'), history: $('history'),
    };

    const state = { contacts: [], visible: [], shown: PAGE_SIZE, selected: new Set(), templates: cfg.templates, sending: false, stop: false, loadId: 0 };
    const sample = { name: 'Ama', event: 'Your Event', nominee: 'Your Nominee', code: 'AB123', amount: 'GHS 5.00', link: cfg.siteUrl };
    const modal = new bootstrap.Modal($('sendModal'));

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function notify(text, type) {
        ui.notice.textContent = text;
        ui.notice.className = 'alert mb-0 mt-3 alert-' + (type || 'info');
        ui.notice.hidden = !text;
    }

    async function post(payload) {
        const response = await fetch(cfg.api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': $('global_csrf_token').value },
            body: JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({ status: 'error', message: 'The server sent an unexpected reply.' }));
        if (data.status !== 'success') throw new Error(data.message || 'Something went wrong.');
        return data;
    }

    function formatPhone(phone) {
        return '+' + phone;
    }

    /* --- Message text ---------------------------------------------------- */
    function fillMessage(text, contact) {
        return text.replace(/\{(name|event|nominee|code|amount|link)\}/g, (match, tag) => {
            const value = tag === 'name' ? (contact.name || '').trim().split(/\s+/)[0] : contact[tag];
            return value || cfg.fallbacks[tag];
        });
    }

    function smsParts(text) {
        const isGsm = /^[A-Za-z0-9 \r\n@£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#¤%&'()*+,\-./:;<=>?¡ÄÖÑÜ§¿äöñüà^{}\\[~\]|€]*$/.test(text);
        const length = isGsm ? text.length + (text.match(/[\^{}\\[~\]|€]/g) || []).length : [...text].length;
        const single = isGsm ? 160 : 70;
        const multi = isGsm ? 153 : 67;
        return { length, parts: length === 0 ? 0 : (length <= single ? 1 : Math.ceil(length / multi)), isGsm };
    }

    function previewContact() {
        const first = state.contacts.find(contact => state.selected.has(contact.phone));
        if (first) return { contact: first, label: 'Preview for ' + (first.name || formatPhone(first.phone)) };
        return { contact: sample, label: 'Example preview' };
    }

    function refreshComposer() {
        const text = ui.message.value;
        const { contact, label } = previewContact();
        const filled = fillMessage(text, contact);
        const { parts, isGsm } = smsParts(filled);

        ui.preview.textContent = filled || 'Your message will appear here.';
        ui.previewLabel.textContent = label;
        ui.count.textContent = filled.length + ' characters, ' + parts + (parts === 1 ? ' SMS' : ' SMS each') + (isGsm ? '' : ' (emoji or special characters shorten each SMS)');

        const total = recipientCount();
        ui.send.disabled = state.sending || total === 0 || !text.trim();
        ui.sendLabel.textContent = total === 0 ? 'Choose contacts to send' : 'Review and send to ' + total.toLocaleString() + (total === 1 ? ' person' : ' people');
    }

    /* --- Suggestions ----------------------------------------------------- */
    function chip(title, text, onDelete) {
        const wrap = el('span', 'msg-chip');
        const button = el('button', '', title);
        button.type = 'button';
        button.addEventListener('click', () => {
            ui.message.value = text;
            refreshComposer();
            ui.message.focus();
        });
        wrap.appendChild(button);
        if (onDelete) {
            const remove = el('button', 'msg-chip-remove');
            remove.type = 'button';
            remove.setAttribute('aria-label', 'Delete ' + title);
            remove.appendChild(el('i', 'bi bi-x'));
            remove.addEventListener('click', onDelete);
            wrap.appendChild(remove);
        }
        return wrap;
    }

    function renderSuggestions() {
        ui.chips.replaceChildren();
        const audience = ui.audience.value;
        const best = cfg.suggestions.filter(item => item.for.includes(audience));
        const rest = cfg.suggestions.filter(item => !item.for.includes(audience));

        best.forEach(item => ui.chips.appendChild(chip(item.title, item.text)));
        state.templates.forEach(item => {
            const saved = chip(item.title, item.text, () => deleteTemplate(item));
            saved.classList.add('is-saved');
            ui.chips.appendChild(saved);
        });
        rest.forEach(item => {
            const other = chip(item.title, item.text);
            other.classList.add('is-other');
            ui.chips.appendChild(other);
        });
    }

    async function deleteTemplate(item) {
        if (!confirm('Delete "' + item.title + '"?')) return;
        try {
            state.templates = (await post({ action: 'delete_template', id: item.id })).templates;
            renderSuggestions();
        } catch (error) {
            notify(error.message, 'danger');
        }
    }

    /* --- Contacts -------------------------------------------------------- */
    async function loadContacts() {
        const loadId = ++state.loadId;
        state.selected.clear();
        ui.search.value = '';
        ui.list.replaceChildren(el('p', 'msg-empty', 'Loading contacts...'));
        ui.note.hidden = true;

        const params = new URLSearchParams({ action: 'contacts', audience: ui.audience.value, event_id: ui.event.value });
        try {
            const response = await fetch(cfg.api + '?' + params);
            const data = await response.json();
            if (loadId !== state.loadId) return;
            if (data.status !== 'success') throw new Error(data.message);

            state.contacts = data.contacts;
            const notes = [];
            if (data.left_out_paid) notes.push(data.left_out_paid + ' left out because they have since paid.');
            if (data.left_out_blocked) notes.push(data.left_out_blocked + ' left out from your do not contact list.');
            if (data.truncated) notes.push('Showing contacts from the most recent records only.');
            ui.note.textContent = notes.join(' ');
            ui.note.hidden = notes.length === 0;
        } catch (error) {
            if (loadId !== state.loadId) return;
            state.contacts = [];
            ui.note.textContent = 'Contacts could not be loaded. Refresh the page and try again.';
            ui.note.hidden = false;
        }

        ui.event.disabled = ui.audience.value === 'users' || ui.audience.value === 'organizers';
        applySearch();
        renderSuggestions();
    }

    function applySearch() {
        const term = ui.search.value.trim().toLowerCase();
        state.visible = !term ? state.contacts : state.contacts.filter(contact =>
            [contact.name, contact.phone, contact.event, contact.nominee, contact.code].join(' ').toLowerCase().includes(term));
        state.shown = PAGE_SIZE;
        renderContacts();
    }

    function contactRow(contact) {
        const row = el('label', 'msg-row');
        const box = el('input', 'form-check-input');
        box.type = 'checkbox';
        box.checked = state.selected.has(contact.phone);
        box.addEventListener('change', () => {
            if (box.checked) state.selected.add(contact.phone); else state.selected.delete(contact.phone);
            updateSelection();
        });

        const who = el('span', 'msg-row-who');
        who.append(el('strong', '', contact.name || 'Guest'), el('small', '', formatPhone(contact.phone)));

        const detail = el('span', 'msg-row-detail');
        const lines = [contact.nominee, contact.event].filter(Boolean);
        detail.textContent = lines.join(' · ');

        row.append(box, who, detail);
        return row;
    }

    function renderContacts() {
        ui.list.replaceChildren();
        if (state.visible.length === 0) {
            ui.list.appendChild(el('p', 'msg-empty', ui.search.value ? 'Nobody matches that search.' : 'Nobody in this group yet.'));
        }
        state.visible.slice(0, state.shown).forEach(contact => ui.list.appendChild(contactRow(contact)));
        ui.more.hidden = state.visible.length <= state.shown;
        ui.more.textContent = 'Show more (' + (state.visible.length - state.shown).toLocaleString() + ' left)';
        updateSelection();
    }

    function updateSelection() {
        const visibleSelected = state.visible.filter(contact => state.selected.has(contact.phone)).length;
        ui.selected.textContent = recipientCount().toLocaleString();
        ui.allLabel.textContent = 'Select all ' + state.visible.length.toLocaleString();
        ui.all.checked = state.visible.length > 0 && visibleSelected === state.visible.length;
        ui.all.indeterminate = visibleSelected > 0 && visibleSelected < state.visible.length;
        ui.all.disabled = state.visible.length === 0;
        refreshComposer();
    }

    /* --- Numbers typed in by hand ---------------------------------------- */
    function normalize(raw) {
        let digits = raw.replace(/[^0-9]/g, '');
        if (digits.length === 10 && digits[0] === '0') digits = '233' + digits.slice(1);
        else if (digits.length === 9) digits = '233' + digits;
        return /^\d{10,15}$/.test(digits) ? digits : null;
    }

    function manualNumbers() {
        const valid = new Set();
        const invalid = [];
        ui.manual.value.split(/[\s,;]+/).filter(Boolean).forEach(raw => {
            const phone = normalize(raw);
            if (!phone) invalid.push(raw);
            else if (!state.selected.has(phone)) valid.add(phone);
        });
        return { valid: [...valid], invalid };
    }

    function recipientCount() {
        return state.selected.size + manualNumbers().valid.length;
    }

    function refreshManual() {
        const { valid, invalid } = manualNumbers();
        ui.manualInfo.textContent = !ui.manual.value.trim()
            ? 'Separate numbers with commas, spaces or new lines.'
            : valid.length + ' ready' + (invalid.length ? ', ' + invalid.length + ' not valid: ' + invalid.slice(0, 5).join(', ') : '');
        ui.manualInfo.classList.toggle('text-danger', invalid.length > 0);
        updateSelection();
    }

    /* --- Sending --------------------------------------------------------- */
    function campaignId() {
        return [...crypto.getRandomValues(new Uint8Array(8))].map(byte => byte.toString(16).padStart(2, '0')).join('');
    }

    function buildBatches() {
        const batches = [];
        const phones = [...state.selected];
        const manual = manualNumbers().valid;
        for (let i = 0; i < phones.length; i += cfg.batchSize) batches.push({ phones: phones.slice(i, i + cfg.batchSize), manual: [] });
        for (let i = 0; i < manual.length; i += cfg.batchSize) batches.push({ phones: [], manual: manual.slice(i, i + cfg.batchSize) });
        return batches;
    }

    function showConfirm() {
        const total = recipientCount();
        const { contact, label } = previewContact();
        const filled = fillMessage(ui.message.value, contact);
        const units = smsParts(filled).parts * total;

        $('sendTitle').textContent = 'Send this message?';
        $('factRecipients').textContent = total.toLocaleString();
        $('factUnits').textContent = units.toLocaleString();
        $('factPreviewLabel').textContent = label;
        $('factPreview').textContent = filled;
        $('confirmStep').hidden = false;
        $('progressStep').hidden = true;
        $('modalCancel').hidden = false;
        $('modalCancel').textContent = 'Cancel';
        $('modalConfirm').hidden = false;
        $('modalConfirm').textContent = 'Send to ' + total.toLocaleString();
        $('modalConfirm').disabled = false;
        modal.show();
    }

    function warnBeforeLeaving(event) {
        event.preventDefault();
        event.returnValue = '';
    }

    function setProgress(done, total, sent, failed) {
        $('progressBar').style.width = (total ? Math.round(done / total * 100) : 100) + '%';
        $('progressText').textContent = sent.length.toLocaleString() + ' sent' + (failed.length ? ', ' + failed.length.toLocaleString() + ' failed' : '') + ' of ' + total.toLocaleString();
    }

    async function runSend() {
        const batches = buildBatches();
        const total = batches.reduce((sum, batch) => sum + batch.phones.length + batch.manual.length, 0);
        const message = ui.message.value.trim();
        const campaign = campaignId();
        const sent = [];
        const failed = [];
        let error = null;
        let reason = '';

        state.sending = true;
        state.stop = false;
        window.addEventListener('beforeunload', warnBeforeLeaving);

        $('sendTitle').textContent = 'Sending...';
        $('confirmStep').hidden = true;
        $('progressStep').hidden = false;
        $('failedList').hidden = true;
        $('modalConfirm').hidden = true;
        $('modalCancel').textContent = 'Stop sending';
        $('modalCancel').disabled = false;
        setProgress(0, total, sent, failed);

        for (const batch of batches) {
            if (state.stop) break;
            try {
                const result = await post({
                    action: 'send', message, campaign,
                    audience: ui.audience.value, event_id: ui.event.value,
                    phones: batch.phones, manual: batch.manual,
                });
                sent.push(...result.sent);
                failed.push(...result.failed);
                reason = reason || result.reason;
                result.sent.forEach(phone => state.selected.delete(phone));
            } catch (err) {
                error = err;
                break;
            }
            setProgress(sent.length + failed.length, total, sent, failed);
        }

        state.sending = false;
        window.removeEventListener('beforeunload', warnBeforeLeaving);

        const remaining = total - sent.length - failed.length;
        $('sendTitle').textContent = error ? 'Sending stopped' : (state.stop && remaining ? 'Stopped' : 'Finished');
        $('progressText').textContent = sent.length.toLocaleString() + ' accepted for delivery, ' + failed.length.toLocaleString() + ' failed'
            + (remaining ? ', ' + remaining.toLocaleString() + ' not sent' : '') + '.' + (error ? ' ' + error.message : '');

        if (failed.length) {
            $('failedList').textContent = 'Failed: ' + failed.slice(0, 10).map(formatPhone).join(', ') + (failed.length > 10 ? ' and ' + (failed.length - 10) + ' more' : '') + (reason ? '. ' + reason : '');
            $('failedList').hidden = false;
        }

        // Anyone already reached leaves the recipient list, so a second click cannot double-send
        const sentSet = new Set(sent);
        const leftover = ui.manual.value.split(/[\s,;]+/).filter(Boolean).filter(raw => !sentSet.has(normalize(raw)));
        ui.manual.value = [...leftover, ...failed.filter(phone => !leftover.some(raw => normalize(raw) === phone))].join('\n');
        failed.forEach(phone => state.selected.delete(phone));

        $('modalCancel').hidden = true;
        $('modalConfirm').hidden = false;
        $('modalConfirm').textContent = 'Done';
        $('modalConfirm').disabled = false;
        renderContacts();
        refreshManual();
        loadHistory();
    }

    $('modalConfirm').addEventListener('click', () => {
        if ($('modalConfirm').textContent === 'Done') {
            modal.hide();
            return;
        }
        $('modalConfirm').disabled = true;
        runSend();
    });

    $('modalCancel').addEventListener('click', () => {
        if (state.sending) {
            state.stop = true;
            $('modalCancel').disabled = true;
            $('modalCancel').textContent = 'Stopping...';
        } else {
            modal.hide();
        }
    });

    ui.send.addEventListener('click', showConfirm);

    $('sendTest').addEventListener('click', async () => {
        const phone = normalize(ui.test.value);
        if (!phone) return notify('Enter a valid phone number for the test.', 'warning');
        if (!ui.message.value.trim()) return notify('Write a message first.', 'warning');

        const button = $('sendTest');
        button.disabled = true;
        try {
            const result = await post({ action: 'send', test: true, campaign: campaignId(), message: ui.message.value.trim(), phones: [], manual: [phone] });
            if (result.sent.length) notify('Test accepted for delivery to ' + formatPhone(phone) + '.', 'success');
            else notify(result.reason || 'The test could not be sent.', 'danger');
            loadHistory();
        } catch (error) {
            notify(error.message, 'danger');
        }
        button.disabled = false;
    });

    /* --- History --------------------------------------------------------- */
    async function loadHistory() {
        try {
            const data = await (await fetch(cfg.api + '?action=history')).json();
            renderHistory(data.history || []);
        } catch (error) {
            ui.history.replaceChildren(el('p', 'msg-empty', 'History could not be loaded.'));
        }
    }

    function renderHistory(items) {
        ui.history.replaceChildren();
        if (items.length === 0) {
            ui.history.appendChild(el('p', 'msg-empty', 'Messages you send will be listed here.'));
            return;
        }
        items.forEach(item => {
            const row = el('div', 'msg-history-row');
            const body = el('div', 'msg-history-body');
            const audience = item.test ? 'Test' : (cfg.audiences[item.audience] || 'Typed numbers');
            const when = new Date(item.when.replace(' ', 'T')).toLocaleString(undefined, { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });
            body.append(
                el('strong', '', audience + ': ' + item.sent.toLocaleString() + ' sent' + (item.failed.length ? ', ' + item.failed.length + ' failed' : '')),
                el('small', '', when + ' by ' + item.by),
                el('p', '', item.message),
            );

            const actions = el('div', 'msg-history-actions');
            const reuse = el('button', 'btn btn-outline-secondary btn-sm', 'Use again');
            reuse.type = 'button';
            reuse.addEventListener('click', () => {
                ui.message.value = item.message;
                refreshComposer();
                ui.message.scrollIntoView({ block: 'center' });
            });
            actions.appendChild(reuse);

            if (item.failed.length) {
                const retry = el('button', 'btn btn-outline-primary btn-sm', 'Retry failed');
                retry.type = 'button';
                retry.addEventListener('click', () => {
                    ui.message.value = item.message;
                    ui.manual.value = item.failed.join('\n');
                    ui.manual.closest('details').open = true;
                    refreshManual();
                    ui.manual.scrollIntoView({ block: 'center' });
                });
                actions.appendChild(retry);
            }

            row.append(body, actions);
            ui.history.appendChild(row);
        });
    }

    /* --- Automatic vote SMS ---------------------------------------------- */
    function showVoteSmsStatus(enabled) {
        $('voteSmsStatus').textContent = enabled
            ? 'On. Voters get a confirmation SMS after each successful payment. Ticket buyers always get theirs, because it carries their entry code.'
            : 'Off. Voters will not get a confirmation SMS, on the website or USSD. Ticket buyers still get theirs, because it carries their entry code.';
    }

    $('voteSmsToggle').addEventListener('change', async event => {
        const toggle = event.target;
        const enabled = toggle.checked;
        toggle.disabled = true;
        try {
            await post({ action: 'vote_sms', enabled });
            showVoteSmsStatus(enabled);
        } catch (error) {
            toggle.checked = !enabled;
            $('voteSmsStatus').textContent = 'Not saved: ' + error.message;
        }
        toggle.disabled = false;
    });

    /* --- Wiring ---------------------------------------------------------- */
    ui.audience.addEventListener('change', loadContacts);
    ui.event.addEventListener('change', loadContacts);
    ui.search.addEventListener('input', applySearch);
    ui.message.addEventListener('input', refreshComposer);
    ui.manual.addEventListener('input', refreshManual);

    ui.more.addEventListener('click', () => {
        state.shown += PAGE_SIZE;
        renderContacts();
    });

    ui.all.addEventListener('change', () => {
        state.visible.forEach(contact => {
            if (ui.all.checked) state.selected.add(contact.phone); else state.selected.delete(contact.phone);
        });
        renderContacts();
    });

    $('tags').addEventListener('click', event => {
        const tag = event.target.dataset.tag;
        if (!tag) return;
        const start = ui.message.selectionStart;
        ui.message.setRangeText(tag, start, ui.message.selectionEnd, 'end');
        ui.message.focus();
        refreshComposer();
    });

    $('saveToggle').addEventListener('click', () => {
        $('saveRow').hidden = !$('saveRow').hidden;
        if (!$('saveRow').hidden) $('saveTitle').focus();
    });

    $('saveTemplate').addEventListener('click', async () => {
        try {
            const data = await post({ action: 'save_template', title: $('saveTitle').value, text: ui.message.value });
            state.templates = data.templates;
            $('saveTitle').value = '';
            $('saveRow').hidden = true;
            renderSuggestions();
            notify('Message saved.', 'success');
        } catch (error) {
            notify(error.message, 'danger');
        }
    });

    $('saveBlocked').addEventListener('click', async () => {
        const info = $('blockedInfo');
        try {
            const data = await post({ action: 'save_blocked', numbers: $('blockedNumbers').value });
            $('blockedNumbers').value = data.numbers.join('\n');
            $('blockedCount').textContent = data.numbers.length;
            info.textContent = 'Saved.' + (data.invalid.length ? ' Not valid, so left out: ' + data.invalid.slice(0, 5).join(', ') : '');
            loadContacts();
        } catch (error) {
            info.textContent = error.message;
        }
    });

    showVoteSmsStatus($('voteSmsToggle').checked);
    renderSuggestions();
    refreshComposer();
    loadContacts();
    loadHistory();
})();
