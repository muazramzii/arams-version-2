<?php
// ============================================================
//  ARAMS 1.0 — Mail (SMTP) configuration, read from the environment.
//
//  No credential lives in this repository or in a file beside the code.
//  Set the variables below in the web server's environment (Apache SetEnv,
//  php-fpm `env[...]`, or the operating system). See
//  config/mail.env.example for the names and
//  docs/security/mail-credential-rotation.md for the procedure.
//
//  If ARAMS_MAIL_USERNAME or ARAMS_MAIL_PASSWORD is missing, mail is
//  treated as NOT configured and nothing is sent.
// ============================================================

/** One setting from the environment, or null when unset or empty. */
function aramsMailEnv(string $name): ?string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? null;   // Apache SetEnv lands here
    }
    return ($value === null || $value === '') ? null : (string) $value;
}

/**
 * SMTP settings, or null when mail is not configured. Callers must not
 * attempt to send when this returns null.
 *
 * Defaults are the safe ones: TLS certificate verification is ON, and SMTP
 * debug output is never produced (it would print the conversation, including
 * the encoded credentials, into the page).
 */
function aramsMailConfig(): ?array
{
    $username = aramsMailEnv('ARAMS_MAIL_USERNAME');
    $password = aramsMailEnv('ARAMS_MAIL_PASSWORD');

    if ($username === null || $password === null) {
        return null;
    }

    $secure = strtolower(aramsMailEnv('ARAMS_MAIL_SECURE') ?? 'tls');

    return [
        'host'           => aramsMailEnv('ARAMS_MAIL_HOST') ?? 'smtp.gmail.com',
        'port'           => (int) (aramsMailEnv('ARAMS_MAIL_PORT') ?? 587),
        'secure'         => in_array($secure, ['tls', 'ssl'], true) ? $secure : 'tls',
        'username'       => $username,
        'password'       => $password,
        'from'           => aramsMailEnv('ARAMS_MAIL_FROM') ?? $username,
        'from_name'      => aramsMailEnv('ARAMS_MAIL_FROM_NAME') ?? 'ARAMS UTHM',
        // Only for a local XAMPP whose Gmail TLS certificate cannot be verified.
        'allow_insecure' => filter_var(aramsMailEnv('ARAMS_MAIL_ALLOW_INSECURE') ?? '0', FILTER_VALIDATE_BOOLEAN),
    ];
}
