# Mail credential rotation: ARAMS 1.0

**Status: ROTATION REQUIRED** (recorded 2026-11-24). Rotated on: _not yet_.

Only the owner of the Gmail account can rotate this. Code changes cannot revoke a password, and nothing in this repository has rotated it.

## What is affected

The ARAMS 1.0 mailer sent password-reset codes and validation results through a Gmail account using an **App Password** stored in plaintext in `arams-main/config/mail.php`. Treat that App Password as **compromised**.

Why, from what was verified (no evidence of actual misuse was found, but none could be looked for without the mailbox's logs):

- It sat in plaintext on disk in a folder that was handed over for review, so it has left its original machine (noted in the Phase 0–1 audit).
- A second copy of the application, including the same file, is deployed under a local web server's document root.
- An unauthenticated page, `test_mail.php`, could send mail through the account to any address. That page was never in git; it has been deleted from this working folder, but a deployed copy still has it (see step 5).

What did **not** happen: the credential file has never been committed to git. This was checked against the full history, and CI now blocks it from being committed (`arams-main/config/mail.php` is on the sensitive-file list).

## What changed in the code

- The mailer reads `ARAMS_MAIL_*` environment variables (names in `arams-main/config/mail.env.example`) and no longer loads `config/mail.php`.
- Without a username and password, nothing is sent; the page shows its normal "could not send" message and no server detail.
- TLS certificate verification is on by default; SMTP debug output (which would print encoded credentials into the page) is never produced.
- `tests/test_mail_config.php` runs in CI to keep it that way.

ARAMS 2.0 (the Laravel backend) already takes mail settings from `.env` (`MAIL_*`, default driver `log`). The same rule applies: real values live only in the server's environment, never in the repository.

## What you need to do

1. **Look first.** Sign in to the Google account that owns the ARAMS mailbox. Review recent security activity and the Sent folder for mail nobody recognises.
2. **Revoke the old App Password.** In the Google Account's Security settings, under 2-Step Verification, open *App passwords* and remove the one(s) used by ARAMS. Revoking is what makes the old value useless. Deleting the file is not enough.
3. **Create a new App Password** with a name such as "ARAMS 1.0 SMTP". Copy it once; do not paste it into chat, a ticket, a document or a repository.
4. **Set it in the server environment**, not in a file under the document root. For Apache that is `SetEnv ARAMS_MAIL_USERNAME …` and `SetEnv ARAMS_MAIL_PASSWORD …` in the server or virtual-host configuration (for XAMPP, `apache/conf/httpd.conf` or an included vhost file). Restart Apache.
5. **Clean every deployed copy.** For each copy of ARAMS 1.0 outside this repository (for example under the web server's document root): delete `test_mail.php` and `config/mail.php`, and replace `includes/mailer.php` and `config/` with the versions from this repository.
6. **Verify.** On a non-production setup, request a password reset for a test account and confirm the email arrives. Then unset the two variables, restart, repeat, and confirm the page says it could not send and shows no SMTP detail.
7. **Record it.** Edit the status line above in a pull request: set "Rotated on" to the date, and change the status to ROTATED.

## Recommendation (not verified)

A personal Gmail account is a weak long-term sender for a university system. UTHM's IT centre may offer an authenticated SMTP relay; whether it does, and whether ARAMS may use it, was not established and needs their authorisation.
