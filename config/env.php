<?php
/**
 * Environment settings loader.
 *
 * Reads KEY=value lines from the .env file in the project root. Values stay in
 * a private array rather than $_ENV or putenv(), so they never appear in
 * phpinfo() output or in anything a child process can read.
 */

if (!function_exists('env')) {
    function loadEnvFile($path) {
        $values = [];
        if (!is_readable($path)) {
            error_log('Environment file not found or not readable: ' . $path);
            return $values;
        }

        // Windows editors may add a byte order mark and CRLF line endings
        $content = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($path));

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim(preg_replace('/^export\s+/', '', trim($key)));
            $value = trim($value);

            // Only full-line comments are supported, so a # inside a password is safe
            $length = strlen($value);
            if ($length >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$length - 1] === $value[0]) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * Get a setting. A real server environment variable wins over the .env file.
     */
    function env($key, $default = null) {
        static $values = null;
        if ($values === null) {
            $values = loadEnvFile(dirname(__DIR__) . '/.env');
        }

        $server = getenv($key);
        if ($server !== false) {
            return $server;
        }

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }
}
