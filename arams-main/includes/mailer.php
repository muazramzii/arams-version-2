<?php
// ============================================================
//  ARAMS — Mailer helper (PHPMailer + Gmail SMTP)
//  Provides: aramsSendMail()  and  aramsSendOtp()
// ============================================================

require_once __DIR__ . '/../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

/**
 * Send an HTML email via Gmail SMTP.
 * Returns ['success'=>true] or ['success'=>false, 'error'=>'...'].
 */
function aramsSendMail(string $to, string $subject, string $htmlBody, string $altBody = '', array $embeds = []): array
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_SECURE;          // 'tls' or 'ssl'
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPDebug  = (int) MAIL_DEBUG;

        // Localhost-only workaround for self-signed / unverifiable TLS certs.
        if (MAIL_ALLOW_INSECURE) {
            $mail->SMTPOptions = ['ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]];
        }

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);

        // Inline (embedded) images referenced in the HTML via cid:<id>
        foreach ($embeds as $img) {
            if (!empty($img['path']) && is_file($img['path'])) {
                $mail->addEmbeddedImage($img['path'], $img['cid']);
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody !== '' ? $altBody : trim(strip_tags($htmlBody));

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Send the 6-digit password-reset code with a clean branded template.
 */
function aramsSendOtp(string $to, string $code): array
{
    $subject = 'ARAMS Password Reset Code';
    $code = htmlspecialchars($code, ENT_QUOTES);

    $logoPath = __DIR__ . '/../assets/images/uthm_logo.png';
    $hasLogo  = is_file($logoPath);

    $logoCell = $hasLogo
        ? '<td style="padding-right:14px;vertical-align:middle;width:54px">
             <span style="display:inline-block;background:#ffffff;border-radius:10px;padding:5px;line-height:0">
               <img src="cid:uthm_logo" alt="UTHM" width="44" height="44" style="display:block;width:44px;height:44px">
             </span>
           </td>'
        : '';

    $html = '
    <div style="background:#f1f5f9;padding:28px 0;font-family:Arial,Helvetica,sans-serif">
      <div style="max-width:480px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0">
        <div style="background:#0B3C5D;padding:18px 24px;color:#fff">
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%">
            <tr>
              ' . $logoCell . '
              <td style="vertical-align:middle">
                <div style="font-size:16px;font-weight:800;letter-spacing:.4px;line-height:1.25">UNIVERSITI TUN HUSSEIN ONN MALAYSIA</div>
                <div style="font-size:12px;color:#bcd;margin-top:3px">Academic Research Analytics &amp; Monitoring System (ARAMS)</div>
              </td>
            </tr>
          </table>
        </div>
        <div style="padding:30px 26px">
          <h2 style="margin:0 0 6px;font-size:19px;color:#0f172a">Password Reset Request</h2>
          <p style="margin:0 0 22px;font-size:14px;color:#475569;line-height:1.6">
            We received a request to reset your ARAMS password. Use the verification code below to continue.
          </p>
          <div style="text-align:center;margin:0 0 22px">
            <div style="display:inline-block;background:#f0fdfa;border:1px dashed #14b8a6;border-radius:12px;padding:16px 30px">
              <div style="font-size:34px;font-weight:800;letter-spacing:10px;color:#0d9488">' . $code . '</div>
            </div>
          </div>
          <p style="margin:0 0 8px;font-size:13px;color:#475569;line-height:1.6">
            This code expires in <strong>10 minutes</strong> and can be used once.
          </p>
          <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.6">
            If you did not request a password reset, you can safely ignore this email &mdash; your password will not change.
          </p>
        </div>
        <div style="background:#f8fafc;padding:14px 26px;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8;text-align:center">
          This is an automated message from ARAMS. Please do not reply.
        </div>
      </div>
    </div>';

    $alt = "ARAMS Password Reset\n\nYour verification code is: " . $code
         . "\nThis code expires in 10 minutes and can be used once.\n"
         . "If you did not request this, ignore this email.";

    $embeds = $hasLogo ? [['path' => $logoPath, 'cid' => 'uthm_logo']] : [];
    return aramsSendMail($to, $subject, $html, $alt, $embeds);
}

/**
 * Notify a lecturer that their submission was Approved or Rejected.
 */
function aramsSendValidationResult(string $to, string $name, string $type, string $status, string $remarks = ''): array
{
    $approved = ($status === 'Approved');
    $accent   = $approved ? '#0d9488' : '#d97706';
    $badgeBg  = $approved ? '#f0fdfa' : '#fffbeb';
    $badgeBd  = $approved ? '#14b8a6' : '#f59e0b';
    $label    = $approved ? 'APPROVED' : 'REJECTED';
    $type     = htmlspecialchars($type, ENT_QUOTES);
    $name     = htmlspecialchars($name, ENT_QUOTES);
    $subject  = 'ARAMS — Your ' . $type . ' submission was ' . $status;

    $logoPath = __DIR__ . '/../assets/images/uthm_logo.png';
    $hasLogo  = is_file($logoPath);
    $logoCell = $hasLogo
        ? '<td style="padding-right:14px;vertical-align:middle;width:54px">
             <span style="display:inline-block;background:#ffffff;border-radius:10px;padding:5px;line-height:0">
               <img src="cid:uthm_logo" alt="UTHM" width="44" height="44" style="display:block;width:44px;height:44px">
             </span>
           </td>'
        : '';

    $remarksBlock = ($remarks !== '')
        ? '<div style="margin:0 0 18px;padding:12px 14px;background:#f8fafc;border-left:3px solid ' . $accent . ';border-radius:6px">
             <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px">Remarks</div>
             <div style="font-size:13px;color:#334155;line-height:1.55">' . nl2br(htmlspecialchars($remarks, ENT_QUOTES)) . '</div>
           </div>'
        : '';

    $html = '
    <div style="background:#f1f5f9;padding:28px 0;font-family:Arial,Helvetica,sans-serif">
      <div style="max-width:480px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0">
        <div style="background:#0B3C5D;padding:18px 24px;color:#fff">
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%"><tr>
            ' . $logoCell . '
            <td style="vertical-align:middle">
              <div style="font-size:16px;font-weight:800;letter-spacing:.4px;line-height:1.25">UNIVERSITI TUN HUSSEIN ONN MALAYSIA</div>
              <div style="font-size:12px;color:#bcd;margin-top:3px">Academic Research Analytics &amp; Monitoring System (ARAMS)</div>
            </td>
          </tr></table>
        </div>
        <div style="padding:28px 26px">
          <p style="margin:0 0 14px;font-size:14px;color:#475569">Dear <strong>' . $name . '</strong>,</p>
          <p style="margin:0 0 18px;font-size:14px;color:#475569;line-height:1.6">
            Your <strong>' . $type . '</strong> submission has been reviewed with the following outcome:
          </p>
          <div style="text-align:center;margin:0 0 20px">
            <span style="display:inline-block;background:' . $badgeBg . ';border:1px solid ' . $badgeBd . ';color:' . $accent . ';font-weight:800;letter-spacing:1px;font-size:15px;padding:9px 24px;border-radius:10px">' . $label . '</span>
          </div>
          ' . $remarksBlock . '
          <p style="margin:0;font-size:13px;color:#475569;line-height:1.6">
            Log in to ARAMS to view the full details of your submission.
          </p>
        </div>
        <div style="background:#f8fafc;padding:14px 26px;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8;text-align:center">
          This is an automated message from ARAMS. Please do not reply.
        </div>
      </div>
    </div>';

    $alt = "Dear {$name},\n\nYour {$type} submission has been {$status}."
         . ($remarks !== '' ? "\nRemarks: {$remarks}" : '')
         . "\n\nLog in to ARAMS to view the details.";

    $embeds = $hasLogo ? [['path' => $logoPath, 'cid' => 'uthm_logo']] : [];
    return aramsSendMail($to, $subject, $html, $alt, $embeds);
}