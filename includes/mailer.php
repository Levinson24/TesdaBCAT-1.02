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

        // SSL verification bypass for localhost (common issue)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

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
 * Send password reset email with 6-digit code
 */
function sendPasswordResetEmail($username, $toEmail, $resetCode)
{
    $schoolName = getSetting('school_name', 'TESDA-BCAT');
    $subject    = "[{$schoolName}] Password Reset Request Code";

    $html = "
    <div style='font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;'>
        <div style='background:linear-gradient(135deg,#0038A8,#002366);padding:32px 24px;text-align:center;color:white;'>
        <div style='background-color:#0038A8;padding:32px 24px;text-align:center;color:white;'>
            <h2 style='margin:0;font-size:1.4rem;'>🔐 Verification Code</h2>
            <p style='margin:0.5rem 0 0;opacity:0.8;font-size:0.875rem;'>{$schoolName}</p>
        </div>
        <div style='padding:32px 24px;'>
            <p style='font-size:1rem;color:#1e293b;'>Hello <strong>" . htmlspecialchars($username) . "</strong>,</p>
            <p style='color:#475569;'>We received a request to reset your password. Use the following 6-digit request code to proceed. This code is valid for <strong>1 hour</strong>.</p>
            
            <div style='text-align:center;margin:2rem 0;background:#f8fafc;padding:24px;border-radius:10px;border:1px dashed #cbd5e0;'>
                <span style='font-size:2.5rem;font-weight:800;letter-spacing:10px;color:#0038A8;'>" . htmlspecialchars($resetCode) . "</span>
            </div>
            
            <p style='color:#64748b;font-size:0.85rem;text-align:center;'>Enter this code on the password reset page to set a new password.</p>
            <p style='color:#94a3b8;font-size:0.8rem;margin-top:2rem;'>If you didn't request this, you can safely ignore this email — your password will remain unchanged.</p>
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
        <div style='background:linear-gradient(135deg,#0038A8,#002366);padding:32px 24px;text-align:center;color:white;'>
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
                    <td style='padding:10px 14px;border-bottom:1px solid #e2e8f0;font-weight:700;color:#0038A8;font-size:1.1rem;'>" . htmlspecialchars($grade) . "</td>
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

/**
 * Send password reset code to Administrator (Supervised Reset)
 */
function sendAdminResetCodeEmail($adminEmail, $requestingUsername, $requestingEmail, $resetCode)
{
    $schoolName = getSetting('school_name', 'TESDA-BCAT');
    $subject    = "[{$schoolName}] ACTION REQUIRED: Password Reset Code for {$requestingUsername}";

    $html = "
    <div style='font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;'>
        <div style='background:linear-gradient(135deg,#e63946,#c1121f);padding:32px 24px;text-align:center;color:white;'>
            <h2 style='margin:0;font-size:1.4rem;'>🔑 Reset Request Received</h2>
            <p style='margin:0.5rem 0 0;opacity:0.8;font-size:0.875rem;'>Security Notification</p>
        </div>
        <div style='padding:32px 24px;'>
            <p style='font-size:1rem;color:#1e293b;'>Hello Administrator,</p>
            <p style='color:#475569;'>A user has requested a password reset. Because the system is set to <strong>Supervised Reset</strong>, the code has been sent to you. Please provide this code to the user after verifying their identity.</p>
            
            <div style='background:#f8fafc;padding:20px;border-radius:10px;margin:1.5rem 0;border:1px solid #e2e8f0;'>
                <p style='margin:0 0 10px;font-size:0.9rem;'><strong>Requesting User:</strong> " . htmlspecialchars($requestingUsername) . "</p>
                <p style='margin:0 0 10px;font-size:0.9rem;'><strong>User Email:</strong> " . htmlspecialchars($requestingEmail) . "</p>
                <div style='text-align:center;padding:15px;background:white;border:2px dashed #0038A8;border-radius:8px;'>
                    <span style='font-size:2rem;font-weight:800;letter-spacing:8px;color:#0038A8;'>" . htmlspecialchars($resetCode) . "</span>
                </div>
            </div>
            
            <p style='color:#94a3b8;font-size:0.8rem;'>The user will be waiting on the verification screen to enter this 6-digit code.</p>
            <hr style='border:none;border-top:1px solid #e2e8f0;margin:1.5rem 0;'>
            <p style='color:#94a3b8;font-size:0.75rem;text-align:center;'>&copy; " . date('Y') . " {$schoolName} Grade Management System</p>
        </div>
    </div>";

    return sendSystemEmail($adminEmail, 'System Administrator', $subject, $html);
}
?>
