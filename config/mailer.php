<?php
/**
 * Gmail / SMTP Mailer
 * Awards Voting System
 *
 * A dependency-free SMTP client (no Composer / PHPMailer required) used to send
 * transactional email through Gmail (smtp.gmail.com) or any other SMTP server.
 *
 * Configuration is stored in the `settings` table and managed from
 * Admin > Settings > Email (SMTP) Settings.
 *
 * Gmail requires an "App Password" (Google Account > Security > 2-Step
 * Verification > App passwords). Your normal Gmail password will NOT work.
 */

if (!defined('MAILER_LOADED')) {
    define('MAILER_LOADED', true);

    // Default Gmail settings used when nothing is configured yet
    define('MAIL_DEFAULT_HOST', 'smtp.gmail.com');
    define('MAIL_DEFAULT_PORT', 587);
    define('MAIL_DEFAULT_SECURE', 'tls');

    /**
     * Read the mail configuration from the settings table.
     * Falls back to sensible Gmail defaults.
     *
     * @param bool $fresh Skip the in-request cache (used right after saving settings)
     * @return array
     */
    function getMailConfig($fresh = false) {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }

        $get = function ($key, $default = null) {
            if (function_exists('getSetting')) {
                return getSetting($key, $default);
            }
            return $default;
        };

        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'support@votehubgh.com';
        $siteName   = defined('SITE_NAME') ? SITE_NAME : 'VoteHubGH';
        $username   = $get('smtp_username', '');

        $cached = [
            'enabled'    => (string) $get('smtp_enabled', '0') === '1',
            'host'       => $get('smtp_host', MAIL_DEFAULT_HOST),
            'port'       => (int) $get('smtp_port', MAIL_DEFAULT_PORT),
            'secure'     => $get('smtp_secure', MAIL_DEFAULT_SECURE), // tls | ssl | none
            'username'   => $username,
            'password'   => $get('smtp_password', ''),
            'from_email' => $get('smtp_from_email', '') ?: ($username ?: $adminEmail),
            'from_name'  => $get('smtp_from_name', '') ?: $siteName,
        ];

        return $cached;
    }

    /**
     * Minimal SMTP client supporting SSL (465) and STARTTLS (587) with AUTH LOGIN.
     */
    class SimpleSmtpMailer
    {
        private $host;
        private $port;
        private $secure;
        private $username;
        private $password;
        private $timeout = 25;
        private $socket = null;
        private $error = '';
        private $transcript = [];

        public function __construct(array $config)
        {
            $this->host     = $config['host'] ?? MAIL_DEFAULT_HOST;
            $this->port     = (int) ($config['port'] ?? MAIL_DEFAULT_PORT);
            $this->secure   = strtolower($config['secure'] ?? MAIL_DEFAULT_SECURE);
            $this->username = $config['username'] ?? '';
            $this->password = $config['password'] ?? '';
            if (!empty($config['timeout'])) {
                $this->timeout = (int) $config['timeout'];
            }
        }

        public function getError()
        {
            return $this->error;
        }

        public function getTranscript()
        {
            return $this->transcript;
        }

        /**
         * Send one message.
         *
         * @param string $fromEmail
         * @param string $fromName
         * @param string $to
         * @param string $subject
         * @param string $htmlBody
         * @param array  $options  replyTo, replyToName, cc (array), bcc (array), textBody
         * @return bool
         */
        public function send($fromEmail, $fromName, $to, $subject, $htmlBody, array $options = [])
        {
            $recipients = array_merge([$to], $options['cc'] ?? [], $options['bcc'] ?? []);
            $recipients = array_values(array_filter(array_unique($recipients)));

            if (empty($recipients)) {
                $this->error = 'No recipients specified.';
                return false;
            }

            if (!$this->connect()) {
                return false;
            }

            try {
                if (!$this->command('MAIL FROM:<' . $fromEmail . '>', [250])) {
                    return false;
                }

                foreach ($recipients as $recipient) {
                    if (!$this->command('RCPT TO:<' . $recipient . '>', [250, 251])) {
                        return false;
                    }
                }

                if (!$this->command('DATA', [354])) {
                    return false;
                }

                $data = $this->buildMessage($fromEmail, $fromName, $to, $subject, $htmlBody, $options);
                $this->write($data . "\r\n.");

                if (!$this->expect([250])) {
                    return false;
                }

                $this->command('QUIT', [221]);
                return true;
            } finally {
                $this->disconnect();
            }
        }

        private function connect()
        {
            $remote = $this->host . ':' . $this->port;
            if ($this->secure === 'ssl') {
                $remote = 'ssl://' . $remote;
            }

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]);

            $errno = 0;
            $errstr = '';
            $this->socket = @stream_socket_client(
                $remote,
                $errno,
                $errstr,
                $this->timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$this->socket) {
                $this->error = 'Could not connect to ' . $remote . ' (' . $errno . ': ' . $errstr . '). '
                    . 'Check that your host allows outgoing SMTP connections on this port.';
                return false;
            }

            stream_set_timeout($this->socket, $this->timeout);

            if (!$this->expect([220])) {
                $this->disconnect();
                return false;
            }

            $hostname = $this->localHostname();

            if (!$this->command('EHLO ' . $hostname, [250])) {
                // Some servers only speak HELO
                if (!$this->command('HELO ' . $hostname, [250])) {
                    $this->disconnect();
                    return false;
                }
            }

            if ($this->secure === 'tls') {
                if (!$this->command('STARTTLS', [220])) {
                    $this->disconnect();
                    return false;
                }

                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
                }

                if (!@stream_socket_enable_crypto($this->socket, true, $crypto)) {
                    $this->error = 'Failed to start TLS encryption. Make sure the PHP openssl extension is enabled.';
                    $this->disconnect();
                    return false;
                }

                // Re-issue EHLO over the encrypted channel
                if (!$this->command('EHLO ' . $hostname, [250])) {
                    $this->disconnect();
                    return false;
                }
            }

            if ($this->username !== '') {
                if (!$this->authenticate()) {
                    $this->disconnect();
                    return false;
                }
            }

            return true;
        }

        private function authenticate()
        {
            if (!$this->command('AUTH LOGIN', [334])) {
                return false;
            }
            if (!$this->command(base64_encode($this->username), [334])) {
                return false;
            }
            if (!$this->command(base64_encode($this->password), [235])) {
                $this->error = 'SMTP authentication failed. For Gmail you must use a 16-character '
                    . 'App Password (Google Account > Security > 2-Step Verification > App passwords), '
                    . 'not your normal Gmail password. Server said: ' . $this->error;
                return false;
            }
            return true;
        }

        private function localHostname()
        {
            $host = $_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? '');
            $host = preg_replace('/:\d+$/', '', (string) $host);
            if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
                return '[127.0.0.1]';
            }
            return $host;
        }

        private function command($command, array $expectedCodes)
        {
            $this->write($command);
            return $this->expect($expectedCodes);
        }

        private function write($data)
        {
            $this->transcript[] = '> ' . (stripos($data, 'AUTH') === 0 ? 'AUTH ...' : substr($data, 0, 120));
            fwrite($this->socket, $data . "\r\n");
        }

        private function expect(array $codes)
        {
            $response = '';
            while (($line = fgets($this->socket, 1024)) !== false) {
                $response .= $line;
                // The last line of a multi-line reply has a space in position 4
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
                $info = stream_get_meta_data($this->socket);
                if ($info['timed_out']) {
                    break;
                }
            }

            $this->transcript[] = '< ' . trim(substr($response, 0, 200));
            $code = (int) substr($response, 0, 3);

            if (!in_array($code, $codes, true)) {
                $this->error = trim($response) !== '' ? trim($response) : 'No response from SMTP server (connection timed out).';
                return false;
            }

            return true;
        }

        private function disconnect()
        {
            if (is_resource($this->socket)) {
                @fclose($this->socket);
            }
            $this->socket = null;
        }

        private function buildMessage($fromEmail, $fromName, $to, $subject, $htmlBody, array $options)
        {
            $boundary = 'vh_' . bin2hex(random_bytes(12));
            $textBody = $options['textBody'] ?? trim(html_entity_decode(strip_tags(
                preg_replace('/<(br|\/p|\/div|\/tr|\/h[1-6])\s*\/?>/i', "\n", $htmlBody)
            ), ENT_QUOTES, 'UTF-8'));

            $headers = [];
            $headers[] = 'Date: ' . date('r');
            $headers[] = 'From: ' . $this->encodeHeaderValue($fromName) . ' <' . $fromEmail . '>';
            $headers[] = 'To: ' . $to;

            if (!empty($options['cc'])) {
                $headers[] = 'Cc: ' . implode(', ', $options['cc']);
            }
            if (!empty($options['replyTo'])) {
                $replyName = !empty($options['replyToName'])
                    ? $this->encodeHeaderValue($options['replyToName']) . ' '
                    : '';
                $headers[] = 'Reply-To: ' . $replyName . '<' . $options['replyTo'] . '>';
            }

            $headers[] = 'Subject: ' . $this->encodeHeaderValue($subject);
            $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->messageIdDomain($fromEmail) . '>';
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'X-Mailer: VoteHubGH Mailer';
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

            $body  = '--' . $boundary . "\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($textBody), 76, "\r\n");
            $body .= "\r\n--" . $boundary . "\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($htmlBody), 76, "\r\n");
            $body .= "\r\n--" . $boundary . '--';

            return implode("\r\n", $headers) . "\r\n\r\n" . $body;
        }

        private function encodeHeaderValue($value)
        {
            if (preg_match('/[^\x20-\x7E]/', $value)) {
                return '=?UTF-8?B?' . base64_encode($value) . '?=';
            }
            return $value;
        }

        private function messageIdDomain($fromEmail)
        {
            $parts = explode('@', $fromEmail);
            return $parts[1] ?? 'votehubgh.com';
        }
    }

    /**
     * Send an email through SMTP when configured, otherwise fall back to mail().
     *
     * @param string $to
     * @param string $subject
     * @param string $htmlBody
     * @param array  $options  replyTo, replyToName, cc, bcc, fromName
     * @return bool
     */
    function sendMailMessage($to, $subject, $htmlBody, array $options = [])
    {
        $config = getMailConfig(!empty($options['freshConfig']));
        $GLOBALS['__last_mail_error'] = '';
        $subject = plainText($subject);

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $GLOBALS['__last_mail_error'] = 'Invalid recipient address: ' . $to;
            error_log('Mailer: invalid recipient address ' . $to);
            return false;
        }

        if ($config['enabled'] && !empty($config['username'])) {
            $mailer = new SimpleSmtpMailer($config);
            $fromName = $options['fromName'] ?? $config['from_name'];
            $sent = $mailer->send($config['from_email'], $fromName, $to, $subject, $htmlBody, $options);

            if ($sent) {
                error_log('SMTP email sent to ' . $to . ' | Subject: ' . $subject);
            } else {
                error_log('SMTP send failed to ' . $to . ' | ' . $mailer->getError());
                $GLOBALS['__last_mail_error'] = $mailer->getError();
            }

            return $sent;
        }

        // ---- Fallback: PHP mail() ----
        $fromEmail = $config['from_email'];
        $fromName  = $options['fromName'] ?? $config['from_name'];

        $headers = [
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: VoteHubGH Mailer',
        ];
        if (!empty($options['replyTo'])) {
            $headers[] = 'Reply-To: <' . $options['replyTo'] . '>';
        }
        if (!empty($options['cc'])) {
            $headers[] = 'Cc: ' . implode(', ', $options['cc']);
        }
        if (!empty($options['bcc'])) {
            $headers[] = 'Bcc: ' . implode(', ', $options['bcc']);
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === 'localhost' || $host === '127.0.0.1') {
            error_log('LOCAL ENVIRONMENT (no SMTP configured): email to ' . $to . ' | Subject: ' . $subject . ' was NOT sent.');
            $GLOBALS['__last_mail_error'] = 'SMTP is not configured, and PHP mail() does not work on localhost. '
                . 'Configure Gmail SMTP in Admin > Settings > Email (SMTP) Settings.';
            return false;
        }

        $result = @mail($to, $subject, $htmlBody, implode("\r\n", $headers), '-f ' . $fromEmail);
        if (!$result) {
            $GLOBALS['__last_mail_error'] = 'PHP mail() returned false. Configure Gmail SMTP for reliable delivery.';
        }
        return $result;
    }

    /**
     * Return the error message from the most recent failed send (if any).
     */
    function getLastMailError()
    {
        return $GLOBALS['__last_mail_error'] ?? '';
    }

    /**
     * Wrap content in the branded VoteHubGH HTML email shell.
     *
     * @param string $heading    Big heading in the coloured header
     * @param string $bodyHtml   Inner HTML
     * @param string $preheader  Hidden preview text shown in the inbox list
     * @return string
     */
    function buildEmailTemplate($heading, $bodyHtml, $preheader = '')
    {
        $siteName = defined('SITE_NAME') ? SITE_NAME : 'VoteHubGH';
        $siteUrl  = defined('SITE_URL') ? SITE_URL : '#';
        $year     = date('Y');

        return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . e($heading) . '</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f7f6; font-family:Arial, Helvetica, sans-serif; color:#333333;">
<div style="display:none; max-height:0; overflow:hidden; opacity:0;">' . e($preheader) . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f7f6; padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 4px 14px rgba(0,0,0,0.08);">
    <tr>
        <td style="background:linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); background-color:#1e3c72; padding:28px 24px; text-align:center;">
            <h1 style="color:#ffffff; margin:0; font-size:22px; font-weight:bold;">' . e($heading) . '</h1>
            <p style="color:rgba(255,255,255,0.85); margin:8px 0 0; font-size:13px;">' . e($siteName) . '</p>
        </td>
    </tr>
    <tr>
        <td style="padding:28px 24px; font-size:15px; line-height:1.6; color:#444444;">
            ' . $bodyHtml . '
        </td>
    </tr>
    <tr>
        <td style="background-color:#f4f7f6; padding:18px 24px; text-align:center; color:#888888; font-size:12px;">
            <p style="margin:0;">This is an automated message from <a href="' . e($siteUrl) . '" style="color:#2a5298; text-decoration:none;">' . e($siteName) . '</a>.</p>
            <p style="margin:6px 0 0;">&copy; ' . $year . ' ' . e($siteName) . '. All rights reserved.</p>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>';
    }

    /**
     * Render a large, easy-to-read verification code block for emails.
     */
    function buildCodeBlock($code)
    {
        return '<div style="margin:24px 0; text-align:center;">
            <div style="display:inline-block; background-color:#f1f5ff; border:2px dashed #2a5298; border-radius:10px; padding:18px 34px;">
                <span style="font-size:34px; letter-spacing:10px; font-weight:bold; color:#1e3c72; font-family:Courier New, monospace;">' . e($code) . '</span>
            </div>
        </div>';
    }

    /**
     * Render a call-to-action button for emails.
     */
    function buildEmailButton($url, $label)
    {
        return '<div style="text-align:center; margin:26px 0;">
            <a href="' . e($url) . '" style="background-color:#2a5298; color:#ffffff; padding:14px 32px; text-decoration:none; border-radius:6px; font-weight:bold; display:inline-block; font-size:15px;">' . e($label) . '</a>
        </div>';
    }
}
