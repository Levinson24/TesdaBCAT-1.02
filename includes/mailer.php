<?php
/**
 * Mailer Helper — Email Notifications via PHPMailer
 * TESDA-BCAT Grade Management System
 *
 * Configure SMTP settings in Admin → Settings.
 * Requires: composer require phpmailer/phpmailer
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * Send an email using system SMTP settings
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $subject
 * @param string $htmlBody
 * @return bool
 */
function sendSystemEmail($toEmail, $toName, $subject, $htmlBody)
{
    // Check if email notifications are enabled
    if (getSetting('email_notifications', '0') !== '1') {
        return false;
    }

    $smtpHost     = getSetting('smtp_host', 'smtp.gmail.com');
    $smtpPort     = (int) getSetting('smtp_port', '587');
    $smtpUser     = getSetting('smtp_user', '');
    $smtpPass     = getSetting('smtp_pass', '');
    $fromName     = getSetting('smtp_from_name', 'TESDA-BCAT GMS');

    if (empty($smtpUser) || empty($smtpPass)) {
        return false; // Not configured
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtpPort;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($smtpUser, $fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (MailerException $e) {
        error_log("Mailer error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($username, $toEmail, $resetUrl)
{
    $schoolName = getSetting('school_name', 'TESDA-BCAT');
    $subject    = "[{$schoolName}] Password Reset Request";

    $html = "
    <div style='font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;'>
        <div style='background:linear-gradient(135deg,#1a3a5c,#0f2a47);padding:32px 24px;text-align:center;color:white;'>
            <h2 style='margin:0;font-size:1.4rem;'>🔐 Password Reset</h2>
            <p style='margin:0.5rem 0 0;opacity:0.8;font-size:0.875rem;'>{$schoolName}</p>
        </div>
        <div style='padding:32px 24px;'>
            <p style='font-size:1rem;color:#1e293b;'>Hello <strong>" . htmlspecialchars($username) . "</strong>,</p>
            <p style='color:#475569;'>We received a request to reset your password. Click the button below to set a new password. This link is valid for <strong>1 hour</strong>.</p>
            <div style='text-align:center;margin:2rem 0;'>
                <a href='" . htmlspecialchars($resetUrl) . "'
                   style='background:#1a3a5c;color:white;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:700;display:inline-block;'>
                    Reset My Password
                </a>
            </div>
            <p style='color:#94a3b8;font-size:0.8rem;'>If you didn't request this, you can safely ignore this email — your password will remain unchanged.</p>
            <hr style='border:none;border-top:1px solid #e2e8f0;margin:1.5rem 0;'>
            <p style='color:#94a3b8;font-size:0.75rem;text-align:center;'>&copy; " . date('Y') . " {$schoolName} Grade Management System</p>
        </div>
    </div>";

    return sendSystemEmail($toEmail, $username, $subject, $html);
}

/**
 * Send grade notification email to student
 */
function sendGradeNotificationEmail($studentEmail, $studentName, $courseName, $grade, $semester, $schoolYear)
{
    $schoolName = getSetting('school_name', 'TESDA-BCAT');
    $subject    = "[{$schoolName}] Your Grade Has Been Posted";

    $html = "
    <div style='font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;'>
        <div style='background:linear-gradient(135deg,#1a3a5c,#0f2a47);padding:32px 24px;text-align:center;color:white;'>
            <h2 style='margin:0;font-size:1.4rem;'>📋 Grade Posted</h2>
            <p style='margin:0.5rem 0 0;opacity:0.8;font-size:0.875rem;'>{$schoolName}</p>
        </div>
        <div style='padding:32px 24px;'>
            <p>Hello <strong>" . htmlspecialchars($studentName) . "</strong>,</p>
            <p style='color:#475569;'>Your grade for the following subject has been officially posted:</p>
            <table style='width:100%;border-collapse:collapse;margin:1.5rem 0;font-size:0.9rem;'>
                <tr style='background:#f8fafc;'>
                    <td style='padding:10px 14px;font-weight:700;color:#64748b;border-bottom:1px solid #e2e8f0;'>Subject</td>
                    <td style='padding:10px 14px;border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($courseName) . "</td>
                </tr>
                <tr>
                    <td style='padding:10px 14px;font-weight:700;color:#64748b;border-bottom:1px solid #e2e8f0;'>Final Grade</td>
                    <td style='padding:10px 14px;border-bottom:1px solid #e2e8f0;font-weight:700;color:#1a3a5c;font-size:1.1rem;'>" . htmlspecialchars($grade) . "</td>
                </tr>
                <tr style='background:#f8fafc;'>
                    <td style='padding:10px 14px;font-weight:700;color:#64748b;'>Period</td>
                    <td style='padding:10px 14px;'>" . htmlspecialchars($semester) . " &mdash; " . htmlspecialchars($schoolYear) . "</td>
                </tr>
            </table>
            <p style='color:#94a3b8;font-size:0.8rem;'>Please log in to your student portal to view your full grade report.</p>
            <hr style='border:none;border-top:1px solid #e2e8f0;margin:1.5rem 0;'>
            <p style='color:#94a3b8;font-size:0.75rem;text-align:center;'>&copy; " . date('Y') . " {$schoolName} Grade Management System</p>
        </div>
    </div>";

    return sendSystemEmail($studentEmail, $studentName, $subject, $html);
}

/**
 * Send enrollment confirmation email
 */
function sendEnrollmentNotificationEmail($studentEmail, $studentName, $sectionName, $courseName, $semester, $schoolYear)
{
    $schoolName = getSetting('school_name', 'TESDA-BCAT');
    $subject    = "[{$schoolName}] Enrollment Confirmed";

    $html = "
    <div style='font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;'>
        <div style='background:linear-gradient(135deg,#27ae60,#1e8449);padding:32px 24px;text-align:center;color:white;'>
            <h2 style='margin:0;font-size:1.4rem;'>✅ Enrollment Confirmed</h2>
            <p style='margin:0.5rem 0 0;opacity:0.8;font-size:0.875rem;'>{$schoolName}</p>
        </div>
        <div style='padding:32px 24px;'>
            <p>Hello <strong>" . htmlspecialchars($studentName) . "</strong>,</p>
            <p style='color:#475569;'>You have been successfully enrolled in the following class:</p>
            <table style='width:100%;border-collapse:collapse;margin:1.5rem 0;font-size:0.9rem;'>
                <tr style='background:#f8fafc;'>
                    <td style='padding:10px 14px;font-weight:700;color:#64748b;border-bottom:1px solid #e2e8f0;'>Subject</td>
                    <td style='padding:10px 14px;border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($courseName) . "</td>
                </tr>
                <tr>
                    <td style='padding:10px 14px;font-weight:700;color:#64748b;border-bottom:1px solid #e2e8f0;'>Section</td>
                    <td style='padding:10px 14px;border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($sectionName) . "</td>
                </tr>
                <tr style='background:#f8fafc;'>
                    <td style='padding:10px 14px;font-weight:700;color:#64748b;'>Period</td>
                    <td style='padding:10px 14px;'>" . htmlspecialchars($semester) . " &mdash; " . htmlspecialchars($schoolYear) . "</td>
                </tr>
            </table>
            <hr style='border:none;border-top:1px solid #e2e8f0;margin:1.5rem 0;'>
            <p style='color:#94a3b8;font-size:0.75rem;text-align:center;'>&copy; " . date('Y') . " {$schoolName} Grade Management System</p>
        </div>
    </div>";

    return sendSystemEmail($studentEmail, $studentName, $subject, $html);
}
?>
