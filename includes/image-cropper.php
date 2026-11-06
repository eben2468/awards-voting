<?php
/**
 * Image Cropper Field
 * Awards Voting System (VoteHubGH)
 *
 * A reusable "pick an image, then crop the face" form control built on
 * Cropper.js. The crop happens in the browser, so the server needs no GD /
 * Imagick and receives a correctly framed, right-sized image.
 *
 * Usage inside a form (the form needs enctype="multipart/form-data"):
 *
 *     require_once '../../includes/image-cropper.php';
 *     ...
 *     renderImageCropField([
 *         'name'     => 'photo',
 *         'label'    => 'Nominee Photo',
 *         'existing' => $photoUrl,       // current image, or null
 *         'help'     => 'Crop to the face.',
 *     ]);
 *     ...
 *     renderImageCropperAssets();        // once, just before </body>
 *
 * On the receiving side use storeImageFromForm('photo', $dir, $slug, $error)
 * from includes/image-upload.php.
 */

if (!defined('IMAGE_CROPPER_LOADED')) {
    define('IMAGE_CROPPER_LOADED', true);

    $GLOBALS['__cropper_fields'] = [];

    /**
     * Render one cropper-backed image field.
     *
     * Options:
     *   name          Field name (required). Cropper posts "<name>_cropped".
     *   label         Visible label.
     *   existing      URL of the current image, or null.
     *   help          Small helper text under the control.
     *   aspect        Default aspect ratio: 1 (square), 0.8 (4:5), 0 (free).
     *   outputWidth   Width of the exported image in pixels.
     *   shape         'circle' or 'square' - preview styling only.
     *   required      Whether an image must be chosen.
     */
    function renderImageCropField(array $options = []) {
        $name = $options['name'] ?? 'image';
        $id = preg_replace('/[^A-Za-z0-9_]/', '_', $options['id'] ?? $name);

        $label       = $options['label'] ?? 'Image';
        $existing    = $options['existing'] ?? null;
        $help        = $options['help'] ?? 'Choose a photo, then drag and zoom to frame the face.';
        $aspect      = array_key_exists('aspect', $options) ? (float) $options['aspect'] : 1.0;
        $outputWidth = (int) ($options['outputWidth'] ?? 800);
        $shape       = $options['shape'] ?? 'square';
        $required    = !empty($options['required']);

        $GLOBALS['__cropper_fields'][] = [
            'id'          => $id,
            'name'        => $name,
            'aspect'      => $aspect,
            'outputWidth' => $outputWidth,
        ];

        $hasExisting = !empty($existing);
        ?>
        <div class="crop-field" data-crop-field="<?php echo e($id); ?>">
            <label class="form-label" for="<?php echo e($id); ?>_file">
                <?php echo e($label); ?>
                <?php if ($required): ?><span class="text-danger">*</span><?php endif; ?>
            </label>

            <div class="crop-field-body">
                <!-- Preview -->
                <div class="crop-preview crop-preview-<?php echo e($shape); ?>"
                     id="<?php echo e($id); ?>_previewWrap">
                    <img id="<?php echo e($id); ?>_preview"
                         class="crop-preview-img<?php echo $hasExisting ? '' : ' d-none'; ?>"
                         src="<?php echo $hasExisting ? e($existing) : ''; ?>"
                         data-original-src="<?php echo $hasExisting ? e($existing) : ''; ?>"
                         alt="">
                    <div class="crop-preview-empty<?php echo $hasExisting ? ' d-none' : ''; ?>"
                         id="<?php echo e($id); ?>_empty">
                        <i class="bi bi-person-bounding-box"></i>
                        <span>No photo</span>
                    </div>
                </div>

                <!-- Controls -->
                <div class="crop-field-controls">
                    <input type="file"
                           id="<?php echo e($id); ?>_file"
                           name="<?php echo e($name); ?>"
                           class="crop-file-input"
                           accept="image/jpeg,image/png,image/webp,image/gif">

                    <!-- Where the cropped result is stored -->
                    <input type="hidden"
                           id="<?php echo e($id); ?>_cropped"
                           name="<?php echo e($name); ?>_cropped"
                           value="">

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary btn-sm crop-choose-btn"
                                data-target="<?php echo e($id); ?>">
                            <i class="bi bi-upload me-1"></i>Choose Photo
                        </button>

                        <button type="button"
                                class="btn btn-outline-secondary btn-sm crop-recrop-btn<?php echo $hasExisting ? '' : ' d-none'; ?>"
                                id="<?php echo e($id); ?>_recrop"
                                data-target="<?php echo e($id); ?>">
                            <i class="bi bi-crop me-1"></i>Re-crop
                        </button>

                        <button type="button"
                                class="btn btn-outline-danger btn-sm crop-clear-btn d-none"
                                id="<?php echo e($id); ?>_clear"
                                data-target="<?php echo e($id); ?>"
                                title="Discard the photo you just cropped">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Discard
                        </button>
                    </div>

                    <div class="form-text mt-2"><?php echo e($help); ?></div>
                    <div class="crop-status small text-success mt-1 d-none"
                         id="<?php echo e($id); ?>_status">
                        <i class="bi bi-check-circle-fill me-1"></i>Photo cropped and ready to save.
                    </div>
                    <div class="crop-error small text-danger mt-1 d-none"
                         id="<?php echo e($id); ?>_error"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Output the shared cropper modal, styles and script. Call once per page,
     * after all renderImageCropField() calls (just before </body>).
     */
    function renderImageCropperAssets() {
        $fields = $GLOBALS['__cropper_fields'] ?? [];
        if (empty($fields)) {
            return;
        }
        ?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
        <script src="<?php echo assetUrl('assets/js/image-compress.js'); ?>"></script>

        <style>
            /* ---- Field layout ---- */
            .crop-field-body {
                display: flex;
                gap: 1rem;
                align-items: flex-start;
            }
            .crop-field-controls { flex: 1 1 auto; min-width: 0; }
            .crop-file-input {
                position: absolute;
                width: 1px; height: 1px;
                opacity: 0; pointer-events: none;
            }

            .crop-preview {
                flex: 0 0 auto;
                width: 110px; height: 110px;
                background: #f1f5f9;
                border: 2px dashed #cbd5e1;
                overflow: hidden;
                display: flex; align-items: center; justify-content: center;
                position: relative;
            }
            .crop-preview-square { border-radius: .75rem; }
            .crop-preview-circle { border-radius: 50%; }
            .crop-preview-img { width: 100%; height: 100%; object-fit: cover; }
            .crop-preview-empty {
                text-align: center; color: #94a3b8; font-size: .7rem; line-height: 1.2;
            }
            .crop-preview-empty i { display: block; font-size: 1.6rem; margin-bottom: .2rem; }

            /* ---- Modal ---- */
            .crop-modal-stage {
                background: #0f172a;
                max-height: 60vh;
                min-height: 240px;
            }
            .crop-modal-stage img { display: block; max-width: 100%; }
            .cropper-view-box, .cropper-face { border-radius: 0; }
            .crop-tools .btn { --bs-btn-padding-y: .35rem; }
            .crop-guide {
                background: #eff6ff;
                border-left: 4px solid #3b82f6;
                font-size: .82rem;
            }

            @media (max-width: 575.98px) {
                .crop-field-body { flex-direction: column; align-items: stretch; }
                .crop-preview { width: 96px; height: 96px; align-self: center; }
                .crop-tools { justify-content: center !important; }

                /* The modal goes full-screen on a phone (modal-fullscreen-sm-down).
                   Let the crop stage grow into whatever space is left between the
                   hint bar and the toolbar, instead of stopping at a fixed height
                   and leaving a dead white band underneath. */
                #imageCropModal .modal-content { height: 100%; }

                #imageCropModal .modal-body {
                    display: flex;
                    flex-direction: column;
                    padding: 0;
                    max-height: none;
                    overflow: hidden;
                }

                #imageCropModal .crop-modal-stage {
                    flex: 1 1 auto;
                    max-height: none;
                    min-height: 180px;
                }
            }
        </style>

        <!-- Shared crop modal -->
        <div class="modal fade" id="imageCropModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-crop me-2"></i>Frame the Photo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-0">
                        <div class="crop-guide px-3 py-2">
                            <i class="bi bi-info-circle me-1"></i>
                            Drag to move, pinch or scroll to zoom. Keep the face centred and fill the frame.
                        </div>
                        <div class="crop-modal-stage">
                            <img id="cropStageImage" alt="">
                        </div>
                    </div>

                    <div class="modal-footer flex-column align-items-stretch gap-2">
                        <div class="d-flex flex-wrap gap-2 justify-content-between crop-tools w-100">
                            <div class="btn-group btn-group-sm" role="group" aria-label="Aspect ratio">
                                <button type="button" class="btn btn-outline-secondary" data-crop-aspect="1">
                                    <i class="bi bi-square me-1"></i>Square
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-crop-aspect="0.8">
                                    <i class="bi bi-person me-1"></i>Portrait
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-crop-aspect="0">
                                    <i class="bi bi-aspect-ratio me-1"></i>Free
                                </button>
                            </div>

                            <div class="btn-group btn-group-sm" role="group" aria-label="Adjust">
                                <button type="button" class="btn btn-outline-secondary" data-crop-action="zoom-in" title="Zoom in">
                                    <i class="bi bi-zoom-in"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-crop-action="zoom-out" title="Zoom out">
                                    <i class="bi bi-zoom-out"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-crop-action="rotate-left" title="Rotate left">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-crop-action="rotate-right" title="Rotate right">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-crop-action="reset" title="Reset">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary flex-fill" id="cropApplyBtn">
                                <i class="bi bi-check-lg me-1"></i>Apply Crop
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function () {
            var FIELDS = <?php echo json_encode($fields); ?>;
            var MAX_SOURCE_BYTES = 12 * 1024 * 1024; // larger originals are shrunk first

            var modalEl = document.getElementById('imageCropModal');
            var stageImg = document.getElementById('cropStageImage');
            var applyBtn = document.getElementById('cropApplyBtn');
            var cropper = null;
            var activeField = null;
            var lastObjectUrl = null;
            var cropApplied = false;

            if (!modalEl || typeof Cropper === 'undefined' || typeof bootstrap === 'undefined') {
                // Cropper or Bootstrap unavailable: the plain file inputs still work,
                // so make them visible rather than leaving the user stuck.
                FIELDS.forEach(function (f) {
                    var input = document.getElementById(f.id + '_file');
                    if (input) {
                        input.classList.remove('crop-file-input');
                        input.classList.add('form-control');
                    }
                });
                return;
            }

            var modal = new bootstrap.Modal(modalEl);

            function fieldConfig(id) {
                for (var i = 0; i < FIELDS.length; i++) {
                    if (FIELDS[i].id === id) return FIELDS[i];
                }
                return null;
            }

            function showError(id, message) {
                var box = document.getElementById(id + '_error');
                if (!box) return;
                box.textContent = message;
                box.classList.toggle('d-none', !message);
            }

            function destroyCropper() {
                if (cropper) { cropper.destroy(); cropper = null; }
            }

            // Kept separate from destroyCropper(): the blob URL is still being
            // displayed while the modal is open, so revoking it there would
            // pull the image out from under Cropper before it could load.
            function releaseObjectUrl() {
                if (lastObjectUrl) { URL.revokeObjectURL(lastObjectUrl); lastObjectUrl = null; }
            }

            function openCropper(id, src) {
                var cfg = fieldConfig(id);
                if (!cfg) return;
                activeField = cfg;

                destroyCropper();
                stageImg.src = src;
                modal.show();
            }

            // Build the Cropper only once the modal is fully visible, otherwise it
            // measures a hidden (zero-size) container and renders blank.
            function buildCropper() {
                if (!activeField || cropper) return;
                var ratio = parseFloat(activeField.aspect);
                cropper = new Cropper(stageImg, {
                    aspectRatio: ratio > 0 ? ratio : NaN,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 0.9,
                    background: false,
                    responsive: true,
                    checkOrientation: true,   // respect EXIF rotation from phones
                    movable: true,
                    zoomable: true,
                    guides: true,
                    center: true
                });
                markActiveAspect(ratio);
            }

            modalEl.addEventListener('shown.bs.modal', function () {
                if (!activeField) return;
                // Cropper measures the image, so it must be decoded first.
                if (stageImg.complete && stageImg.naturalWidth > 0) {
                    buildCropper();
                } else {
                    stageImg.addEventListener('load', buildCropper, { once: true });
                    stageImg.addEventListener('error', function () {
                        showError(activeField.id, 'That image could not be opened. Please try another file.');
                        modal.hide();
                    }, { once: true });
                }
            });

            modalEl.addEventListener('hidden.bs.modal', function () {
                // Cancelled without applying: drop the chosen file so the form
                // cannot quietly submit an uncropped original.
                if (activeField && !cropApplied) {
                    var input = document.getElementById(activeField.id + '_file');
                    if (input) { input.value = ''; }
                }
                cropApplied = false;
                destroyCropper();
                stageImg.src = '';
                releaseObjectUrl();
            });

            function markActiveAspect(ratio) {
                modalEl.querySelectorAll('[data-crop-aspect]').forEach(function (btn) {
                    var v = parseFloat(btn.getAttribute('data-crop-aspect'));
                    var on = (v === ratio) || (v === 0 && !(ratio > 0));
                    btn.classList.toggle('active', on);
                    btn.classList.toggle('btn-secondary', on);
                    btn.classList.toggle('btn-outline-secondary', !on);
                });
            }

            // ---- Toolbar ----
            modalEl.querySelectorAll('[data-crop-aspect]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!cropper) return;
                    var v = parseFloat(this.getAttribute('data-crop-aspect'));
                    cropper.setAspectRatio(v > 0 ? v : NaN);
                    markActiveAspect(v > 0 ? v : NaN);
                });
            });

            modalEl.querySelectorAll('[data-crop-action]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!cropper) return;
                    switch (this.getAttribute('data-crop-action')) {
                        case 'zoom-in':      cropper.zoom(0.1); break;
                        case 'zoom-out':     cropper.zoom(-0.1); break;
                        case 'rotate-left':  cropper.rotate(-90); break;
                        case 'rotate-right': cropper.rotate(90); break;
                        case 'reset':        cropper.reset(); break;
                    }
                });
            });

            // ---- Apply ----
            applyBtn.addEventListener('click', function () {
                if (!cropper || !activeField) return;

                var id = activeField.id;
                var width = parseInt(activeField.outputWidth, 10) || 800;
                var ratio = parseFloat(activeField.aspect);

                var opts = {
                    width: width,
                    imageSmoothingQuality: 'high',
                    fillColor: '#ffffff'
                };
                // Only pin the height when a fixed ratio is in force, so "Free"
                // crops keep whatever shape the user drew.
                if (ratio > 0) {
                    opts.height = Math.round(width / ratio);
                }

                var canvas = cropper.getCroppedCanvas(opts);
                if (!canvas) {
                    showError(id, 'Could not read the crop. Please try again.');
                    return;
                }

                var dataUrl = canvas.toDataURL('image/jpeg', 0.9);

                document.getElementById(id + '_cropped').value = dataUrl;

                var preview = document.getElementById(id + '_preview');
                preview.src = dataUrl;
                preview.classList.remove('d-none');
                document.getElementById(id + '_empty').classList.add('d-none');
                document.getElementById(id + '_status').classList.remove('d-none');
                document.getElementById(id + '_recrop').classList.remove('d-none');
                document.getElementById(id + '_clear').classList.remove('d-none');
                showError(id, '');

                // The cropped data URL is what gets submitted, so the original
                // file no longer needs to travel with the form.
                var fileInput = document.getElementById(id + '_file');
                if (fileInput) { fileInput.value = ''; }

                cropApplied = true;
                modal.hide();
            });

            // ---- Per-field wiring ----
            FIELDS.forEach(function (cfg) {
                var id = cfg.id;
                var fileInput = document.getElementById(id + '_file');
                var chooseBtn = document.querySelector('.crop-choose-btn[data-target="' + id + '"]');
                var recropBtn = document.getElementById(id + '_recrop');
                var clearBtn = document.getElementById(id + '_clear');
                var preview = document.getElementById(id + '_preview');

                if (chooseBtn && fileInput) {
                    chooseBtn.addEventListener('click', function () { fileInput.click(); });
                }

                if (fileInput) {
                    fileInput.addEventListener('change', function () {
                        var file = this.files && this.files[0];
                        if (!file) return;

                        if (!/^image\//.test(file.type)) {
                            showError(id, 'Please choose an image file.');
                            this.value = '';
                            return;
                        }
                        showError(id, '');

                        function crop(source) {
                            if (lastObjectUrl) { URL.revokeObjectURL(lastObjectUrl); }
                            lastObjectUrl = URL.createObjectURL(source);
                            openCropper(id, lastObjectUrl);
                        }

                        if (file.size > MAX_SOURCE_BYTES) {
                            window.compressImageFile(file, MAX_SOURCE_BYTES).then(function (smaller) {
                                crop(smaller || file);
                            });
                            return;
                        }

                        crop(file);
                    });
                }

                // Re-crop uses whatever is currently previewed
                if (recropBtn && preview) {
                    recropBtn.addEventListener('click', function () {
                        if (!preview.src) {
                            if (fileInput) fileInput.click();
                            return;
                        }
                        openCropper(id, preview.src);
                    });
                }

                // "Discard" throws away the newly cropped photo and puts the
                // previously saved one back, so the form saves no change.
                if (clearBtn) {
                    clearBtn.addEventListener('click', function () {
                        var original = preview.getAttribute('data-original-src') || '';

                        document.getElementById(id + '_cropped').value = '';
                        if (fileInput) fileInput.value = '';

                        preview.src = original;
                        preview.classList.toggle('d-none', original === '');
                        document.getElementById(id + '_empty').classList.toggle('d-none', original !== '');
                        document.getElementById(id + '_status').classList.add('d-none');
                        if (recropBtn) { recropBtn.classList.toggle('d-none', original === ''); }
                        clearBtn.classList.add('d-none');
                        showError(id, '');
                    });
                }
            });
        })();
        </script>
        <?php
    }
}
