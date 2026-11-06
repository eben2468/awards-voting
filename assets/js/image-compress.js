/**
 * Shrinks oversized images in the browser before they upload, so large photos
 * are accepted instead of refused. This needs nothing on the server, and it also
 * keeps uploads under the host's size limits.
 *
 * Add data-compress-max="<bytes>" to a file input to switch it on.
 */
(function () {
    'use strict';

    var LONGEST_SIDE = 3000;
    var ATTEMPTS = [[1, 0.88], [1, 0.76], [0.8, 0.76], [0.65, 0.72], [0.5, 0.7], [0.35, 0.66]];

    function loadImage(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var image = new Image();
            image.onload = function () { resolve({ image: image, url: url }); };
            image.onerror = function () { URL.revokeObjectURL(url); reject(new Error('unreadable')); };
            image.src = url;
        });
    }

    function encode(image, scale, quality) {
        return new Promise(function (resolve) {
            var canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
            canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
            var context = canvas.getContext('2d');
            // JPEG has no transparency, and would otherwise turn it black
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(image, 0, 0, canvas.width, canvas.height);
            canvas.toBlob(resolve, 'image/jpeg', quality);
        });
    }

    /**
     * Resolves with a JPEG File no larger than maxBytes, or null if it cannot be done.
     */
    async function compressImageFile(file, maxBytes) {
        var loaded;
        try {
            loaded = await loadImage(file);
        } catch (error) {
            return null;
        }

        var image = loaded.image;
        var fit = Math.min(1, LONGEST_SIDE / Math.max(image.naturalWidth, image.naturalHeight));

        try {
            for (var i = 0; i < ATTEMPTS.length; i++) {
                var blob = await encode(image, fit * ATTEMPTS[i][0], ATTEMPTS[i][1]);
                if (blob && blob.size <= maxBytes) {
                    var name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                    return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
                }
            }
        } finally {
            URL.revokeObjectURL(loaded.url);
        }
        return null;
    }

    window.compressImageFile = compressImageFile;

    function formatSize(bytes) {
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function statusFor(input) {
        var status = input.parentNode.querySelector('.image-compress-status');
        if (!status) {
            status = document.createElement('div');
            status.className = 'form-text image-compress-status';
            status.setAttribute('role', 'status');
            input.insertAdjacentElement('afterend', status);
        }
        return status;
    }

    function setSubmitting(input, busy) {
        if (!input.form) return;
        input.form.querySelectorAll('[type="submit"]').forEach(function (button) {
            button.disabled = busy;
        });
    }

    async function handleChange(event) {
        var input = event.target;
        var limit = Number(input.dataset.compressMax);
        var file = input.files && input.files[0];
        var status = statusFor(input);

        status.textContent = '';
        if (!file || !limit || file.size <= limit || !/^image\//.test(file.type)) return;

        status.textContent = 'This image is ' + formatSize(file.size) + '. Compressing...';
        setSubmitting(input, true);

        var smaller = await compressImageFile(file, limit);
        var replaced = false;

        if (smaller && window.DataTransfer) {
            try {
                var transfer = new DataTransfer();
                transfer.items.add(smaller);
                input.files = transfer.files;
                replaced = input.files[0] === smaller || input.files[0].size === smaller.size;
            } catch (error) {
                replaced = false;
            }
        }

        status.textContent = replaced
            ? 'Compressed from ' + formatSize(file.size) + ' to ' + formatSize(smaller.size) + '.'
            : 'This image could not be compressed here, so the server will try when you save.';
        setSubmitting(input, false);
    }

    document.addEventListener('change', function (event) {
        if (event.target.matches && event.target.matches('input[type="file"][data-compress-max]')) {
            handleChange(event);
        }
    });
})();
