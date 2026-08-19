<?php
// ============================================================
//  ARAMS — Mail (SMTP) Configuration  [TEMPLATE]
//  Copy this file to "mail.php" and fill in the App Password.
//  mail.php is git-ignored so the real password never reaches the repo.
// ============================================================

define('MAIL_HOST',      'smtp.gmail.com');
define('MAIL_PORT',      587);                    // 587 = TLS, 465 = SSL
define('MAIL_SECURE',    'tls');                  // 'tls' for 587, 'ssl' for 465
define('MAIL_USERNAME',  'arams.uthm@gmail.com'); // the ARAMS Gmail account
define('MAIL_PASSWORD',  'YOUR_16_CHAR_APP_PASSWORD');  // <-- fill in mail.php (NOT here)
define('MAIL_FROM',      'arams.uthm@gmail.com');
define('MAIL_FROM_NAME', 'ARAMS UTHM');

// TRUE only for localhost (XAMPP) where Gmail's TLS cert can't be verified.
// Set FALSE in production.
define('MAIL_ALLOW_INSECURE', true);

define('MAIL_DEBUG', 0);                          // 2 or 3 to debug sending issues