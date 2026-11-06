/**
 * Fill in approximate locations after the audit log has loaded, so a slow lookup
 * never holds up the page.
 */
(async function () {
    'use strict';

    var cells = Array.from(document.querySelectorAll('[data-ip-location]'));
    var ips = Array.from(new Set(cells.map(function (cell) { return cell.dataset.ipLocation; })));
    var url = document.currentScript.dataset.url;

    for (var i = 0; i < ips.length; i += 15) {
        var locations = {};
        try {
            var response = await fetch(url + '?ips=' + encodeURIComponent(ips.slice(i, i + 15).join(',')));
            locations = (await response.json()).locations || {};
        } catch (error) {
            locations = {};
        }

        cells.forEach(function (cell) {
            var place = locations[cell.dataset.ipLocation];
            if (place) cell.textContent = 'Near ' + place;
        });
    }

    cells.forEach(function (cell) {
        if (cell.textContent === 'Looking up...') cell.textContent = 'Location unavailable';
    });
})();
