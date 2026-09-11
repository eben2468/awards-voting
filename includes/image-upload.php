<?php
/**
 * Image Upload Helper
 * Awards Voting System (VoteHubGH)
 *
 * Stores nominee photos, event banners and avatars safely.
 *
 * Two input paths are supported:
 *   1. A browser-cropped image arriving as a base64 data URL (preferred - the
 *      visitor framed the face themselves in the cropper).
 *   2. A plain <input type="file"> upload, used when JavaScript is unavailable.
 *
 * Security note: the saved file extension is derived from the bytes of the image
 * itself (via getimagesizefromstring), never from the name or Content-Type the
 * browser sent. Trusting the uploaded filename allows "photo.php" to be saved
 * into a web-served folder and executed.
 *
 * No GD or Imagick required - the browser does the resizing and cropping.
 */

if (!defined('IMAGE_UPLOAD_LOADED')) {
    define('IMAGE_UPLOAD_LOADED', true);

    // Only these image types are ever written to disk
    define('IMAGE_ALLOWED_MIMES', 'image/jpeg,image/png,image/webp,image/gif');
    define('IMAGE_MAX_BYTES', 6 * 1024 * 1024);   // 6 MB after decoding
    define('IMAGE_MIN_DIMENSION', 40);            // px
    define('IMAGE_MAX_DIMENSION', 6000);          // px

    /**
     * Map a detected MIME type to a safe file extension.
     *
     * @param string $mime
     * @return string|null
     */
    function imageExtensionForMime($mime) {
        switch (strtolower($mime)) {
            case 'image/jpeg':
            case 'image/jpg':
                return 'jpg';
            case 'image/png':
                return 'png';
            case 'image/webp':
                return 'webp';
            case 'image/gif':
                return 'gif';
        }
        return null;
    }

    /**
     * Turn a name into a safe filename stem.
     */
    function safeFilenameStem($text, $fallback = 'image') {
        $stem = strtolower((string) $text);
        $stem = preg_replace('/[^a-z0-9]+/', '-', $stem);
        $stem = trim((string) $stem, '-');
        if ($stem === '') {
            $stem = $fallback;
        }
        return substr($stem, 0, 60);
    }

    /**
     * Validate raw image bytes and write them to disk.
     *
     * @param string $bytes      Raw binary image data
     * @param string $targetDir  Directory to write into (created if needed)
     * @param string $stem       Filename stem, without extension
     * @param string $error      Filled in on failure
     * @return string|null       The stored filename, or null on failure
     */
    function storeImageBytes($bytes, $targetDir, $stem, &$error) {
        if ($bytes === '' || $bytes === false || $bytes === null) {
            $error = 'The image was empty.';
            return null;
        }

        if (strlen($bytes) > IMAGE_MAX_BYTES) {
            $error = 'That image is too large (max ' . round(IMAGE_MAX_BYTES / 1048576) . 'MB).';
            return null;
        }

        // Inspect the actual bytes; this does not need the GD extension
        $info = @getimagesizefromstring($bytes);
        if ($info === false || empty($info['mime'])) {
            $error = 'That file is not a readable image.';
            return null;
        }

        $allowed = explode(',', IMAGE_ALLOWED_MIMES);
        if (!in_array(strtolower($info['mime']), $allowed, true)) {
            $error = 'Unsupported image type (' . htmlspecialchars($info['mime']) . '). Use JPG, PNG, WEBP or GIF.';
            return null;
        }

        list($width, $height) = $info;
        if ($width < IMAGE_MIN_DIMENSION || $height < IMAGE_MIN_DIMENSION) {
            $error = 'That image is too small (minimum ' . IMAGE_MIN_DIMENSION . 'x' . IMAGE_MIN_DIMENSION . ' pixels).';
            return null;
        }
        if ($width > IMAGE_MAX_DIMENSION || $height > IMAGE_MAX_DIMENSION) {
            $error = 'That image is too big (maximum ' . IMAGE_MAX_DIMENSION . ' pixels on a side).';
            return null;
        }

        // Extension comes from the detected type, never from user input
        $extension = imageExtensionForMime($info['mime']);
        if ($extension === null) {
            $error = 'Unsupported image type.';
            return null;
        }

        $targetDir = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
            $error = 'The upload folder could not be created.';
            return null;
        }
        if (!is_writable($targetDir)) {
            $error = 'The upload folder is not writable on the server.';
            return null;
        }

        $filename = safeFilenameStem($stem) . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $extension;

        if (@file_put_contents($targetDir . $filename, $bytes) === false) {
            $error = 'The image could not be saved to the server.';
            return null;
        }

        @chmod($targetDir . $filename, 0644);

        return $filename;
    }

    /**
     * Decode a base64 data URL produced by the cropper and store it.
     *
     * @return string|null Stored filename, or null
     */
    function storeCroppedImage($dataUrl, $targetDir, $stem, &$error) {
        if (!is_string($dataUrl) || $dataUrl === '') {
            $error = 'No cropped image was submitted.';
            return null;
        }

        if (!preg_match('#^data:(image/(?:jpeg|jpg|png|webp));base64,#i', $dataUrl, $m)) {
            $error = 'The cropped image was not in a recognised format.';
            return null;
        }

        $payload = substr($dataUrl, strlen($m[0]));
        // Browsers may URL-encode the payload when it travels through a form
        $payload = str_replace(' ', '+', $payload);
        $bytes = base64_decode($payload, true);

        if ($bytes === false) {
            $error = 'The cropped image could not be decoded.';
            return null;
        }

        return storeImageBytes($bytes, $targetDir, $stem, $error);
    }

    /**
     * Store a classic $_FILES upload.
     *
     * @return string|null Stored filename, or null
     */
    function storeUploadedImage(array $file, $targetDir, $stem, &$error) {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error'] ?? UPLOAD_ERR_NO_FILE) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error = 'That image is larger than the server allows ('
                        . ini_get('upload_max_filesize') . ').';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error = 'No image was selected.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error = 'The upload was interrupted. Please try again.';
                    break;
                default:
                    $error = 'The image could not be uploaded.';
            }
            return null;
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            $error = 'Invalid upload.';
            return null;
        }

        $bytes = @file_get_contents($file['tmp_name']);
        return storeImageBytes($bytes, $targetDir, $stem, $error);
    }

    /**
     * The one call the forms use: prefer the cropped version, fall back to the
     * raw file upload, and return null when neither was provided.
     *
     * @param string $fieldName  Base field name, e.g. 'photo'
     *                           (the cropper posts '<field>_cropped')
     * @param string $targetDir  Where to write
     * @param string $stem       Filename stem (usually the record's slug)
     * @param string $error      Filled in on failure
     * @return string|null       Stored filename, or null when nothing was sent
     */
    function storeImageFromForm($fieldName, $targetDir, $stem, &$error) {
        $error = '';

        $cropped = $_POST[$fieldName . '_cropped'] ?? '';
        if (is_string($cropped) && $cropped !== '') {
            return storeCroppedImage($cropped, $targetDir, $stem, $error);
        }

        if (isset($_FILES[$fieldName]) && ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            return storeUploadedImage($_FILES[$fieldName], $targetDir, $stem, $error);
        }

        // Nothing submitted: not an error, the caller keeps the existing image
        return null;
    }

    /**
     * Delete a previously stored image, ignoring anything suspicious.
     */
    function deleteStoredImage($targetDir, $filename) {
        if (!is_string($filename) || $filename === '') {
            return;
        }
        // Refuse to follow paths out of the intended folder
        if (basename($filename) !== $filename) {
            return;
        }
        $path = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
