<?php
// ============================================================
//  ARAMS 1.0 — mail configuration checks (standalone, no database, no network).
//  Run:  php arams-main/tests/test_mail_config.php      (exit 1 on any failure)
//
//  Guards three properties:
//    1. Unconfigured mail fails safely: no exception, nothing sent, no server detail.
//    2. Defaults are the secure ones.
//    3. The mailer never goes back to loading a credential file.
// ============================================================

$failures = 0;
function check(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'ok   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures++;
    }
}

$vars = ['ARAMS_MAIL_USERNAME', 'ARAMS_MAIL_PASSWORD', 'ARAMS_MAIL_HOST', 'ARAMS_MAIL_PORT',
         'ARAMS_MAIL_SECURE', 'ARAMS_MAIL_FROM', 'ARAMS_MAIL_FROM_NAME', 'ARAMS_MAIL_ALLOW_INSECURE'];
$reset = function () use ($vars): void {
    foreach ($vars as $v) {
        putenv($v);                       // unsets
        unset($_SERVER[$v], $_ENV[$v]);
    }
};
$reset();

require_once __DIR__ . '/../includes/mailer.php';

// 1. Not configured -------------------------------------------------------
check('unconfigured: no settings are returned', aramsMailConfig() === null);

$result = null;
try {
    $result = aramsSendMail('someone@example.test', 'Subject', '<p>Body</p>');
} catch (Throwable $e) {
    $result = null;
}
check('unconfigured: sending does not throw', $result !== null);
check('unconfigured: sending reports failure', is_array($result) && $result['success'] === false);
check('unconfigured: error text reveals no server detail',
    is_array($result) && !preg_match('/smtp|gmail|password|username|host/i', (string) $result['error']));

putenv('ARAMS_MAIL_USERNAME=sender@example.test');
check('username without password still counts as unconfigured', aramsMailConfig() === null);

// 2. Configured, placeholders only ---------------------------------------
putenv('ARAMS_MAIL_PASSWORD=not-a-real-password');
$cfg = aramsMailConfig();
check('configured: settings are returned', is_array($cfg));
check('default host is smtp.gmail.com', ($cfg['host'] ?? '') === 'smtp.gmail.com');
check('default port is 587 with tls', ($cfg['port'] ?? 0) === 587 && ($cfg['secure'] ?? '') === 'tls');
check('sender defaults to the username', ($cfg['from'] ?? '') === 'sender@example.test');
check('TLS certificate verification is ON by default (allow_insecure is false)', ($cfg['allow_insecure'] ?? null) === false);

putenv('ARAMS_MAIL_SECURE=plaintext');
check('an unrecognised security value falls back to tls', (aramsMailConfig()['secure'] ?? '') === 'tls');
putenv('ARAMS_MAIL_SECURE=SSL');
check('ssl is accepted case-insensitively', (aramsMailConfig()['secure'] ?? '') === 'ssl');
putenv('ARAMS_MAIL_ALLOW_INSECURE=1');
check('insecure TLS can only be switched on explicitly', (aramsMailConfig()['allow_insecure'] ?? null) === true);

// 3. No credential file, no debug output ---------------------------------
$mailer = file_get_contents(__DIR__ . '/../includes/mailer.php');
check('mailer does not load config/mail.php', strpos($mailer, 'config/mail.php') === false);
check('mailer never enables SMTP debug output', preg_match('/SMTPDebug\s*=\s*0\s*;/', $mailer) === 1);

$config = file_get_contents(__DIR__ . '/../config/mail_config.php');
check('the tracked config defines no credential constant', !preg_match('/define\(\s*[\'"]MAIL_PASSWORD/', $config));

$reset();
echo ($failures === 0 ? "\nall checks passed\n" : "\n{$failures} check(s) FAILED\n");
exit($failures === 0 ? 0 : 1);
