<?php
/**
 * Email Configuration and Helper Functions
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Email constants are now loaded from .env via config.php
// No need to redefine them here

/**
 * Send email using PHPMailer
 */
function sendEmail($to, $subject, $message, $recipient_name = '', $options = []) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;

        // Disable SSL verification for local development (remove in production)
        if (defined('APP_ENV') && APP_ENV !== 'production') {
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
        }

        // Recipients
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to, $recipient_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = getEmailTemplate($message, $subject, $recipient_name);

        $mail->send();
        logEmail($to, $recipient_name, $subject, $options['email_type'] ?? 'general', 
                 $options['appraisal_id'] ?? null, $options['user_id'] ?? null, true);
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        logEmail($to, $recipient_name, $subject, $options['email_type'] ?? 'general',
                 $options['appraisal_id'] ?? null, $options['user_id'] ?? null, false);
        return false;
    }
}

/**
 * Get HTML email template
 */
function getEmailTemplate($content, $subject, $recipient_name) {
    $greeting = $recipient_name ? "Hi {$recipient_name}," : "Hello,";
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .email-container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f8f9fa; padding: 30px; border-radius: 0 0 5px 5px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .button { 
                display: inline-block; 
                padding: 12px 24px; 
                background-color: #0d6efd; 
                color: white !important; 
                text-decoration: none; 
                border-radius: 5px;
                margin: 15px 0;
                font-weight: bold;
            }
            .info-box {
                background-color: #e7f3ff;
                border-left: 4px solid #0d6efd;
                padding: 15px;
                margin: 15px 0;
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h2 style='margin: 0;'>Performance Appraisal System</h2>
            </div>
            <div class='content'>
                <p>{$greeting}</p>
                {$content}
            </div>
            <div class='footer'>
                <p>This is an automated email from the Performance Appraisal System.</p>
                <p>Please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " YBS International. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Log email sending attempt to database
 * FIXED: Get database connection properly
 */
/**
 * Log email sending attempt to database
 */
function logEmail($to, $recipient_name, $subject, $email_type, $appraisal_id = null, $user_id = null, $success = true) {
    try {
        // Get database connection
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            error_log("logEmail: Database connection not available");
            return false;
        }
        
        // First, let's check what columns exist in email_logs
        error_log("Attempting to log email to: {$to}");
        
        // Use the column names that match your actual table structure
        // Based on your error, it seems you have 'appraisal_id' not 'related_appraisal_id'
        $query = "INSERT INTO email_logs (recipient_email, recipient_name, subject, email_type, 
                  appraisal_id, user_id, status, sent_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $db->prepare($query);
        $status = $success ? 'sent' : 'failed';
        
        $result = $stmt->execute([
            $to,
            $recipient_name,
            $subject,
            $email_type,
            $appraisal_id,
            $user_id,
            $status
        ]);
        
        if ($result) {
            error_log("✅ Email logged successfully");
        } else {
            error_log("❌ Failed to log email");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("❌ Email logging error: " . $e->getMessage());
        // Don't fail the email sending just because logging failed
        return false;
    }
}

/**
 * Send appraisal submission notification
 * Notifies: Employee, Manager, and HR personnel
 * FIXED: Get database connection properly
 */
function sendAppraisalSubmissionEmails($appraisal_id) {
    try {
        // Get database connection
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            error_log("sendAppraisalSubmissionEmails: Database connection failed");
            return false;
        }
        
        // UPDATED QUERY - Prefer work email (emp_email) over personal email
        $query = "SELECT 
                    a.*, 
                    a.user_id,
                    a.appraisal_period_from,
                    a.appraisal_period_to,
                    u.name as employee_name, 
                    COALESCE(NULLIF(u.emp_email, ''), u.email) as employee_email,
                    u.email as employee_personal_email,
                    u.emp_email as employee_work_email,
                    u.company_id,
                    u.direct_superior,
                    m.name as manager_name, 
                    COALESCE(NULLIF(m.emp_email, ''), m.email) as manager_email,
                    m.email as manager_personal_email,
                    m.emp_email as manager_work_email
                  FROM appraisals a
                  JOIN users u ON a.user_id = u.id
                  LEFT JOIN users m ON u.direct_superior = m.id
                  WHERE a.id = ?";
        
        error_log("=== sendAppraisalSubmissionEmails CALLED ===");
        error_log("Appraisal ID: {$appraisal_id}");
        
        $stmt = $db->prepare($query);
        $stmt->execute([$appraisal_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            error_log("sendAppraisalSubmissionEmails: Appraisal not found - ID: {$appraisal_id}");
            return false;
        }
        
        // DEBUG LOG
        error_log("=== QUERY RESULTS ===");
        error_log("Employee: {$data['employee_name']}");
        error_log("Employee Work Email: " . ($data['employee_work_email'] ?? 'NULL'));
        error_log("Employee Personal Email: " . ($data['employee_personal_email'] ?? 'NULL'));
        error_log("Employee Final Email: {$data['employee_email']}");
        error_log("Direct Superior ID: " . ($data['direct_superior'] ?? 'NULL'));
        error_log("Manager Name: " . ($data['manager_name'] ?? 'NULL'));
        error_log("Manager Work Email: " . ($data['manager_work_email'] ?? 'NULL'));
        error_log("Manager Personal Email: " . ($data['manager_personal_email'] ?? 'NULL'));
        error_log("Manager Final Email: " . ($data['manager_email'] ?? 'NULL'));
        error_log("Company ID: {$data['company_id']}");
        error_log("====================");
        
        $base_url = BASE_URL;
        
        // 1. Send email to employee
        error_log("--- Sending email to EMPLOYEE ---");
        error_log("To: {$data['employee_email']}");
        
        $employee_message = "
            <div class='info-box'>
                <p><strong> Your appraisal has been successfully submitted!</strong></p>
            </div>
            <p><strong>Appraisal Period:</strong> " . formatDate($data['appraisal_period_from']) . " - " . formatDate($data['appraisal_period_to']) . "</p>
            <p>Your manager will review your appraisal and provide feedback soon. You will receive another notification once the review is complete.</p>
            <p style='text-align: center;'>
                <a href='{$base_url}/employee/appraisal/view.php?id={$appraisal_id}' class='button'>View My Appraisal</a>
            </p>
        ";
        
        $employee_sent = sendEmail(
            $data['employee_email'],
            'Appraisal Submitted Successfully',
            $employee_message,
            $data['employee_name'],
            [
                'email_type' => 'appraisal_submitted_employee',
                'appraisal_id' => $appraisal_id,
                'user_id' => $data['user_id']
            ]
        );
        
        error_log("Employee email sent: " . ($employee_sent ? 'SUCCESS' : 'FAILED'));
        
        // 2. Send email to manager
        if (!empty($data['manager_email'])) {
            error_log("--- Sending email to MANAGER ---");
            error_log("To: {$data['manager_email']}");
            error_log("Manager Name: {$data['manager_name']}");
            
            $manager_message = "
                <div class='info-box'>
                    <p><strong> New appraisal ready for your review</strong></p>
                </div>
                <p><strong>{$data['employee_name']}</strong> has submitted their performance appraisal for your review.</p>
                <p><strong>Appraisal Period:</strong> " . formatDate($data['appraisal_period_from']) . " - " . formatDate($data['appraisal_period_to']) . "</p>
                <p>Please review and provide your assessment at your earliest convenience.</p>
                <p style='text-align: center;'>
                    <a href='{$base_url}/manager/review/review.php?id={$appraisal_id}' class='button'>Review Appraisal Now</a>
                </p>
            ";
            
            $manager_sent = sendEmail(
                $data['manager_email'],
                'New Appraisal Pending Your Review - ' . $data['employee_name'],
                $manager_message,
                $data['manager_name'],
                [
                    'email_type' => 'appraisal_submitted_manager',
                    'appraisal_id' => $appraisal_id
                ]
            );
            
            error_log("Manager email sent: " . ($manager_sent ? 'SUCCESS' : 'FAILED'));
        } else {
            error_log("❌ MANAGER EMAIL IS EMPTY - NOT SENDING");
        }
        
        
        
        
        return true;
        
    } catch (Exception $e) {
        error_log("!!! EXCEPTION in sendAppraisalSubmissionEmails !!!");
        error_log("Message: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Send manager review completion notification
 * Notifies: Employee and HR personnel
 * FIXED: Get database connection properly
 */
function sendReviewCompletionEmails($appraisal_id) {
    try {
        // Get database connection
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            error_log("sendReviewCompletionEmails: Database connection failed");
            return false;
        }
        
        // Get appraisal details - PREFER WORK EMAIL
        $query = "SELECT a.*, 
                         u.name as employee_name,
                         COALESCE(NULLIF(u.emp_email, ''), u.email) as employee_email,
                         u.company_id, 
                         m.name as manager_name,
                         COALESCE(NULLIF(m.emp_email, ''), m.email) as manager_email
                  FROM appraisals a
                  JOIN users u ON a.user_id = u.id
                  LEFT JOIN users m ON u.direct_superior = m.id
                  WHERE a.id = ?";
        
        error_log("=== sendReviewCompletionEmails CALLED ===");
        error_log("Appraisal ID: {$appraisal_id}");
        
        $stmt = $db->prepare($query);
        $stmt->execute([$appraisal_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            error_log("sendReviewCompletionEmails: Appraisal not found - ID: {$appraisal_id}");
            return false;
        }
        
        error_log("Employee: {$data['employee_name']} ({$data['employee_email']})");
        error_log("Manager: {$data['manager_name']} ({$data['manager_email']})");
        error_log("Grade: {$data['grade']}, Score: {$data['total_score']}");
        
        $base_url = BASE_URL;
        
        // 1. Send email to EMPLOYEE
        error_log("--- Sending completion email to EMPLOYEE ---");
        $employee_message = "
            <div class='info-box'>
                <p><strong>Your appraisal review is complete!</strong></p>
            </div>
            <p>Your performance appraisal has been reviewed and completed by your manager.</p>
            <p>
                <strong>Appraisal Period:</strong> " . formatDate($data['appraisal_period_from']) . " - " . formatDate($data['appraisal_period_to']) . "<br>
                <strong>Reviewed by:</strong> {$data['manager_name']}
            </p>";
        
        if (!empty($data['grade'])) {
            $employee_message .= "
            <div class='info-box'>
                <p style='margin: 0; font-size: 18px;'><strong>Final Grade: {$data['grade']}</strong></p>
                <p style='margin: 5px 0 0 0;'>Total Score: {$data['total_score']}</p>
            </div>";
        }
        
        $employee_message .= "
            <p>Please log in to view your complete appraisal results and manager feedback.</p>
            <p style='text-align: center;'>
                <a href='{$base_url}/employee/appraisal/view.php?id={$appraisal_id}' class='button'>View My Results</a>
            </p>
        ";
        
        $employee_sent = sendEmail(
            $data['employee_email'],
            'Your Appraisal Review is Complete',
            $employee_message,
            $data['employee_name'],
            [
                'email_type' => 'review_completed_employee',
                'appraisal_id' => $appraisal_id,
                'user_id' => $data['user_id']
            ]
        );
        
        error_log("Employee completion email: " . ($employee_sent ? 'SUCCESS' : 'FAILED'));
        
        // 2. Send email to MANAGER (confirmation)
        if (!empty($data['manager_email'])) {
            error_log("--- Sending confirmation email to MANAGER ---");
            $manager_message = "
                <div class='info-box'>
                    <p><strong>Appraisal Review Completed</strong></p>
                </div>
                <p>You have successfully completed the appraisal review for <strong>{$data['employee_name']}</strong>.</p>
                <p>
                    <strong>Final Grade:</strong> {$data['grade']}<br>
                    <strong>Total Score:</strong> {$data['total_score']}<br>
                    <strong>Appraisal Period:</strong> " . formatDate($data['appraisal_period_from']) . " - " . formatDate($data['appraisal_period_to']) . "
                </p>
                <p>The employee and HR have been notified of the completed review.</p>
            ";
            
            $manager_sent = sendEmail(
                $data['manager_email'],
                'Appraisal Review Completed - Confirmation',
                $manager_message,
                $data['manager_name'],
                [
                    'email_type' => 'review_completed_manager',
                    'appraisal_id' => $appraisal_id
                ]
            );
            
            error_log("Manager confirmation email: " . ($manager_sent ? 'SUCCESS' : 'FAILED'));
        }
        
        // 3. Send emails to HR personnel
        error_log("--- Sending completion emails to HR ---");
        $hr_query = "SELECT DISTINCT u.name, 
                            COALESCE(NULLIF(u.emp_email, ''), u.email) as email
                     FROM hr_companies hc
                     JOIN users u ON hc.user_id = u.id
                     WHERE hc.company_id = ? AND u.is_hr = TRUE AND u.is_active = TRUE";
        
        $hr_stmt = $db->prepare($hr_query);
        $hr_stmt->execute([$data['company_id']]);
        
        $hr_count = 0;
        while ($hr = $hr_stmt->fetch(PDO::FETCH_ASSOC)) {
            error_log("Sending completion email to HR: {$hr['name']} ({$hr['email']})");
            
            $hr_message = "
                <div class='info-box'>
                    <p><strong>HR Notification: Appraisal Review Completed</strong></p>
                </div>
                <p>A performance appraisal review has been completed.</p>
                <p>
                    <strong>Employee:</strong> {$data['employee_name']}<br>
                    <strong>Reviewed by:</strong> {$data['manager_name']}<br>
                    <strong>Appraisal Period:</strong> " . formatDate($data['appraisal_period_from']) . " - " . formatDate($data['appraisal_period_to']) . "
                </p>";
            
            if (!empty($data['grade'])) {
                $hr_message .= "
                <p>
                    <strong>Final Grade:</strong> {$data['grade']}<br>
                    <strong>Total Score:</strong> {$data['total_score']}
                </p>";
            }
            
            $hr_message .= "
                <p style='text-align: center;'>
                    <a href='{$base_url}/hr/appraisals/view.php?id={$appraisal_id}' class='button'>View Complete Appraisal</a>
                </p>
            ";
            
            $hr_sent = sendEmail(
                $hr['email'],
                'Appraisal Review Completed - HR Notification',
                $hr_message,
                $hr['name'],
                [
                    'email_type' => 'review_completed_hr',
                    'appraisal_id' => $appraisal_id
                ]
            );
            
            error_log("HR completion email sent: " . ($hr_sent ? 'SUCCESS' : 'FAILED'));
            $hr_count++;
        }
        
        error_log("Total HR completion emails sent: {$hr_count}");
        error_log("=== sendReviewCompletionEmails COMPLETED ===");
        
        return true;
        
    } catch (Exception $e) {
        error_log("!!! EXCEPTION in sendReviewCompletionEmails !!!");
        error_log("Message: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}