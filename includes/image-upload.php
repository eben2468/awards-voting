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
 * No GD or Imagick required - the browser does the resizing and cropping, and
 * assets/js/image-compress.js shrinks oversized files before they upload. When a
 * large file still arrives here, GD compresses it if the host has it.
 */

if (!defined('IMAGE_UPLOAD_LOADED')) {
    define('IMAGE_UPLOAD_LOADED', true);

    // Only these image types are ever written to disk
    define('IMAGE_ALLOWED_MIMES', 'image/jpeg,image/png,image/webp,image/gif');
    define('IMAGE_MAX_BYTES', 5 * 1024 * 1024);   // Larger images are compressed
    define('IMAGE_COMPRESS_SIDE', 3000);          // Longest side after compressing, px
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
     * Load image bytes into GD, turned the right way up.
     *
     * @return GdImage|null Null when the image cannot be read or is too big
     */
    function decodeImageUpright($bytes, array $info) {
        // Decoding needs roughly 5 bytes per pixel, so refuse rather than crash
        @ini_set('memory_limit', '512M');
        $limit = ini_get('memory_limit');
        $unit = strtoupper(substr((string) $limit, -1));
        $limitBytes = (int) $limit * ($unit === 'G' ? 1073741824 : ($unit === 'M' ? 1048576 : ($unit === 'K' ? 1024 : 1)));
        if ($limitBytes > 0 && $info[0] * $info[1] * 5 > $limitBytes - memory_get_usage()) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }

        // Phones store rotation as a tag, which GD ignores
        if ($info['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
            $angles = [3 => 180, 6 => -90, 8 => 90];
            $orientation = (int) ($exif['Orientation'] ?? 1);
            if (isset($angles[$orientation])) {
                $rotated = imagerotate($source, $angles[$orientation], 0);
                if ($rotated !== false) {
                    imagedestroy($source);
                    $source = $rotated;
                }
            }
        }

        return $source;
    }

    /**
     * Shrink an image with GD until it fits. Used when a large file reaches the
     * server without being compressed in the browser.
     *
     * @return string|null JPEG bytes, or null when this host cannot do it
     */
    function compressImageBytes($bytes, $maxBytes) {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            return null;
        }

        $source = decodeImageUpright($bytes, $info);
        if ($source === null) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $fit = min(1, IMAGE_COMPRESS_SIDE / max($width, $height));

        foreach ([[1, 88], [1, 76], [0.8, 76], [0.65, 72], [0.5, 70], [0.35, 66]] as $attempt) {
            $newWidth = max(1, (int) round($width * $fit * $attempt[0]));
            $newHeight = max(1, (int) round($height * $fit * $attempt[0]));

            $canvas = imagecreatetruecolor($newWidth, $newHeight);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            ob_start();
            imagejpeg($canvas, null, $attempt[1]);
            $result = ob_get_clean();
            imagedestroy($canvas);

            if ($result !== false && strlen($result) <= $maxBytes) {
                imagedestroy($source);
                return $result;
            }
        }

        imagedestroy($source);
        return null;
    }

    /**
     * Is this pixel part of the empty margin? Clear pixels always are. On an image
     * with a solid background, so is anything close to the corner's colour.
     */
    function isBlankPixel($pixel, $corner) {
        $clear = 115; // GD alpha runs from 0 (solid) to 127 (clear)
        if ((($pixel >> 24) & 0x7F) >= $clear) {
            return true;
        }
        if ((($corner >> 24) & 0x7F) >= $clear) {
            return false;
        }

        return abs((($pixel >> 16) & 0xFF) - (($corner >> 16) & 0xFF)) <= 16
            && abs((($pixel >> 8) & 0xFF) - (($corner >> 8) & 0xFF)) <= 16
            && abs(($pixel & 0xFF) - ($corner & 0xFF)) <= 16;
    }

    /**
     * Cut the empty margins off a stored image, keeping any transparency.
     * If this host cannot do it, the image simply stays as it was uploaded.
     *
     * @return bool True when the file was changed
     */
    function trimImageFile($path) {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }

        $bytes = @file_get_contents($path);
        $info = $bytes ? @getimagesizefromstring($bytes) : false;
        // A GIF may be animated, and GD would keep only its first frame
        if ($info === false || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return false;
        }

        $image = decodeImageUpright($bytes, $info);
        if ($image === null) {
            return false;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $width = imagesx($image);
        $height = imagesy($image);
        $corner = imagecolorat($image, 0, 0);

        $rowIsBlank = function ($y) use ($image, $corner, $width) {
            for ($x = 0; $x < $width; $x++) {
                if (!isBlankPixel(imagecolorat($image, $x, $y), $corner)) {
                    return false;
                }
            }
            return true;
        };
        $columnIsBlank = function ($x, $from, $to) use ($image, $corner) {
            for ($y = $from; $y <= $to; $y++) {
                if (!isBlankPixel(imagecolorat($image, $x, $y), $corner)) {
                    return false;
                }
            }
            return true;
        };

        $top = 0;
        $bottom = $height - 1;
        $left = 0;
        $right = $width - 1;
        while ($top < $bottom && $rowIsBlank($top)) {
            $top++;
        }
        while ($bottom > $top && $rowIsBlank($bottom)) {
            $bottom--;
        }
        while ($left < $right && $columnIsBlank($left, $top, $bottom)) {
            $left++;
        }
        while ($right > $left && $columnIsBlank($right, $top, $bottom)) {
            $right--;
        }

        $newWidth = $right - $left + 1;
        $newHeight = $bottom - $top + 1;
        $nothingToCut = $newWidth === $width && $newHeight === $height;
        // A nearly empty result means the image was blank, so leave it alone
        if ($nothingToCut || $newWidth < IMAGE_MIN_DIMENSION || $newHeight < IMAGE_MIN_DIMENSION) {
            imagedestroy($image);
            return false;
        }

        $cropped = imagecrop($image, ['x' => $left, 'y' => $top, 'width' => $newWidth, 'height' => $newHeight]);
        imagedestroy($image);
        if ($cropped === false) {
            return false;
        }

        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);

        ob_start();
        if ($info['mime'] === 'image/png') {
            $encoded = imagepng($cropped);
        } elseif ($info['mime'] === 'image/webp') {
            $encoded = function_exists('imagewebp') && imagewebp($cropped, null, 90);
        } else {
            $encoded = imagejpeg($cropped, null, 90);
        }
        $result = ob_get_clean();
        imagedestroy($cropped);

        return $encoded && $result !== '' && @file_put_contents($path, $result) !== false;
    }

    /**
     * Validate raw image bytes and write them to disk.
     *
     * @param string $bytes      Raw binary image data
     * @param string $targetDir  Directory to write into (created if needed)
     * @param string $stem       Filename stem, without extension
     * @param string $error      Filled in on failure
     * @param int    $maxBytes   Larger images are compressed to fit
     * @return string|null       The stored filename, or null on failure
     */
    function storeImageBytes($bytes, $targetDir, $stem, &$error, $maxBytes = IMAGE_MAX_BYTES) {
        if ($bytes === '' || $bytes === false || $bytes === null) {
            $error = 'The image was empty.';
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
            $error = 'Unsupported image type (' . e($info['mime']) . '). Use JPG, PNG, WEBP or GIF.';
            return null;
        }

        if (strlen($bytes) > $maxBytes || max($info[0], $info[1]) > IMAGE_MAX_DIMENSION) {
            $compressed = compressImageBytes($bytes, $maxBytes);
            if ($compressed === null) {
                $error = 'That image is too large to process here. Please try again in an up-to-date browser, or use a smaller copy.';
                return null;
            }
            $bytes = $compressed;
            $info = getimagesizefromstring($bytes);
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
    function storeCroppedImage($dataUrl, $targetDir, $stem, &$error, $maxBytes = IMAGE_MAX_BYTES) {
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

        return storeImageBytes($bytes, $targetDir, $stem, $error, $maxBytes);
    }

    /**
     * Store a classic $_FILES upload.
     *
     * @return string|null Stored filename, or null
     */
    function storeUploadedImage(array $file, $targetDir, $stem, &$error, $maxBytes = IMAGE_MAX_BYTES) {
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
        return storeImageBytes($bytes, $targetDir, $stem, $error, $maxBytes);
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
     * @param int    $maxBytes   Larger images are compressed to fit
     * @return string|null       Stored filename, or null when nothing was sent
     */
    function storeImageFromForm($fieldName, $targetDir, $stem, &$error, $maxBytes = IMAGE_MAX_BYTES) {
        $error = '';

        $cropped = $_POST[$fieldName . '_cropped'] ?? '';
        if (is_string($cropped) && $cropped !== '') {
            return storeCroppedImage($cropped, $targetDir, $stem, $error, $maxBytes);
        }

        if (isset($_FILES[$fieldName]) && ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            return storeUploadedImage($_FILES[$fieldName], $targetDir, $stem, $error, $maxBytes);
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
