<?php
/**
 * Email Class - Professional Email Management
 * Handles email sending with PHPMailer integration and fallback support
 */

// Check if autoloader exists and define availability
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (!defined('PHPMAILER_AVAILABLE')) {
        define('PHPMAILER_AVAILABLE', true);
    }
} else {
    if (!defined('PHPMAILER_AVAILABLE')) {
        define('PHPMAILER_AVAILABLE', false);
    }
}

// Import PHPMailer classes if available
if (PHPMAILER_AVAILABLE) {
    try {
        // Check if PHPMailer classes exist
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            class_alias('PHPMailer\PHPMailer\PHPMailer', 'PHPMailer_PHPMailer');
            class_alias('PHPMailer\PHPMailer\SMTP', 'PHPMailer_SMTP');
            class_alias('PHPMailer\PHPMailer\Exception', 'PHPMailer_Exception');
        } else {
            // PHPMailer not properly installed
            if (!defined('PHPMAILER_FALLBACK')) {
                define('PHPMAILER_FALLBACK', true);
            }
        }
    } catch (Exception $e) {
        error_log("PHPMailer class aliasing failed: " . $e->getMessage());
        if (!defined('PHPMAILER_FALLBACK')) {
            define('PHPMAILER_FALLBACK', true);
        }
    }
}

class Email {
    private $mailer;
    private $phpmailerAvailable;
    private $fallbackMode;
    
    /**
     * Initializes the Email class, setting up PHPMailer if available or enabling fallback mode otherwise.
     *
     * Attempts to instantiate and configure PHPMailer for email sending. If PHPMailer is unavailable or initialization fails, enables fallback mode to use PHP's native mail functionality.
     */
    public function __construct() {
        $this->phpmailerAvailable = PHPMAILER_AVAILABLE && !defined('PHPMAILER_FALLBACK');
        $this->fallbackMode = !$this->phpmailerAvailable;
        
        if ($this->phpmailerAvailable) {
            try {
                $this->mailer = new PHPMailer_PHPMailer(true);
                $this->configure();
            } catch (Exception $e) {
                error_log("PHPMailer initialization failed: " . $e->getMessage());
                $this->fallbackMode = true;
                $this->phpmailerAvailable = false;
            }
        } else {
            error_log("PHPMailer not available - using fallback email functionality");
        }
    }
    
    /**
     * Configures the PHPMailer instance with SMTP settings.
     *
     * Sets SMTP host, authentication, credentials, encryption, port, sender information, and character set for outgoing emails. Enables fallback mode and returns false if configuration fails.
     *
     * @return bool True if configuration succeeds; false if PHPMailer is unavailable or configuration fails.
     */
    private function configure() {
        if (!$this->phpmailerAvailable || !$this->mailer) {
            return false;
        }
        
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = defined('SMTP_USER') ? SMTP_USER : '';
            $this->mailer->Password = defined('SMTP_PASS') ? SMTP_PASS : '';
            
            // Use string constant for compatibility
            $this->mailer->SMTPSecure = 'tls';
            $this->mailer->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
            
            $this->mailer->setFrom(
                defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@localhost', 
                defined('FROM_NAME') ? FROM_NAME : 'System'
            );
            $this->mailer->CharSet = 'UTF-8';
            
            return true;
            
        } catch (Exception $e) {
            error_log("Email configuration failed: " . $e->getMessage());
            $this->fallbackMode = true;
            return false;
        }
    }
    
    /**
     * Sends a work order creation notification email to the customer.
     *
     * Attempts to use PHPMailer for sending an HTML email with work order details. If PHPMailer is unavailable or sending fails, falls back to PHP's native mail function.
     *
     * @param array $workOrder Associative array containing work order and customer details.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public function sendWorkOrderCreated($workOrder) {
        $subject = "Work Order Created - {$workOrder['work_order_number']}";
        $body = $this->getWorkOrderCreatedTemplate($workOrder);
        
        if ($this->phpmailerAvailable && $this->mailer) {
            try {
                $this->mailer->clearAddresses();
                $this->mailer->addAddress($workOrder['customer_email'], $workOrder['customer_name']);
                $this->mailer->Subject = $subject;
                $this->mailer->msgHTML($body);
                
                return $this->mailer->send();
                
            } catch (Exception $e) {
                error_log("PHPMailer send failed: " . $e->getMessage());
                // Fallback to basic mail
                return $this->sendFallbackEmail($workOrder['customer_email'], $subject, $body);
            }
        } else {
            // Use fallback method
            return $this->sendFallbackEmail($workOrder['customer_email'], $subject, $body);
        }
    }
    
    /**
     * Sends a work order completion notification email to the customer.
     *
     * Attempts to send an HTML email using PHPMailer if available; falls back to PHP's native mail function if PHPMailer is unavailable or sending fails.
     *
     * @param array $workOrder The work order data, including customer email and name.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public function sendWorkOrderCompleted($workOrder) {
        $subject = "Work Order Completed - {$workOrder['work_order_number']}";
        $body = $this->getWorkOrderCompletedTemplate($workOrder);
        
        if ($this->phpmailerAvailable && $this->mailer) {
            try {
                $this->mailer->clearAddresses();
                $this->mailer->addAddress($workOrder['customer_email'], $workOrder['customer_name']);
                $this->mailer->Subject = $subject;
                $this->mailer->msgHTML($body);
                
                return $this->mailer->send();
                
            } catch (Exception $e) {
                error_log("PHPMailer send failed: " . $e->getMessage());
                // Fallback to basic mail
                return $this->sendFallbackEmail($workOrder['customer_email'], $subject, $body);
            }
        } else {
            // Use fallback method
            return $this->sendFallbackEmail($workOrder['customer_email'], $subject, $body);
        }
    }
    
    /**
     * Sends a low stock alert email to all active admin users.
     *
     * Attempts to notify each admin about items with low stock using PHPMailer if available, or falls back to PHP's mail function. Returns true if all emails are sent successfully, false if any fail or if no admins are found.
     *
     * @param array $items List of items with low stock to include in the alert.
     * @return bool True if all alert emails are sent successfully, false otherwise.
     */
    public function sendLowStockAlert($items) {
        try {
            // Get admin users safely
            $admins = [];
            try {
                $db = Database::getInstance();
                $admins = $db->select("SELECT email, full_name FROM users WHERE role = 'admin' AND is_active = 1");
            } catch (Exception $e) {
                error_log("Failed to get admin users: " . $e->getMessage());
                return false;
            }
            
            if (empty($admins)) {
                error_log("No admin users found for low stock alert");
                return false;
            }
            
            $subject = "Low Stock Alert - " . count($items) . " Items";
            $body = $this->getLowStockTemplate($items);
            $success = true;
            
            foreach($admins as $admin) {
                if ($this->phpmailerAvailable && $this->mailer) {
                    try {
                        $this->mailer->clearAddresses();
                        $this->mailer->addAddress($admin['email'], $admin['full_name']);
                        $this->mailer->Subject = $subject;
                        $this->mailer->msgHTML($body);
                        
                        if (!$this->mailer->send()) {
                            $success = false;
                        }
                    } catch (Exception $e) {
                        error_log("PHPMailer send failed for {$admin['email']}: " . $e->getMessage());
                        // Try fallback
                        if (!$this->sendFallbackEmail($admin['email'], $subject, $body)) {
                            $success = false;
                        }
                    }
                } else {
                    // Use fallback method
                    if (!$this->sendFallbackEmail($admin['email'], $subject, $body)) {
                        $success = false;
                    }
                }
            }
            
            return $success;
            
        } catch (Exception $e) {
            error_log("Low stock alert failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generates an HTML email template for notifying a customer of a newly created work order.
     *
     * The template includes customer name, work order number, vehicle details, status, priority, damage description, and estimated cost if available. It uses inline CSS for styling and includes a footer with the system name and an automated message notice.
     *
     * @param array $workOrder Associative array containing work order details.
     * @return string The HTML content for the work order creation notification email.
     */
    private function getWorkOrderCreatedTemplate($workOrder) {
        $fromName = defined('FROM_NAME') ? FROM_NAME : 'Bengkel Management System';
        return "
        <html>
        <head>
            <style>
                .email-container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: white; }
                .details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { background: #6c757d; color: white; padding: 15px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h2>🚗 Work Order Created</h2>
                </div>
                <div class='content'>
                    <p>Dear {$workOrder['customer_name']},</p>
                    <p>Your work order has been created successfully. Here are the details:</p>
                    
                    <div class='details'>
                        <h4>Work Order Details:</h4>
                        <p><strong>Work Order Number:</strong> {$workOrder['work_order_number']}</p>
                        <p><strong>Vehicle:</strong> {$workOrder['brand']} {$workOrder['model']} ({$workOrder['license_plate']})</p>
                        <p><strong>Status:</strong> Pending</p>
                        <p><strong>Priority:</strong> " . ucfirst($workOrder['priority'] ?? 'normal') . "</p>
                        <p><strong>Damage Description:</strong> {$workOrder['damage_description']}</p>
                        " . (!empty($workOrder['estimated_cost']) ? "<p><strong>Estimated Cost:</strong> Rp " . number_format($workOrder['estimated_cost'], 0, ',', '.') . "</p>" : "") . "
                    </div>
                    
                    <p>We will keep you updated on the progress. Thank you for choosing our service!</p>
                </div>
                <div class='footer'>
                    <p>{$fromName}</p>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Generates an HTML email template for notifying a customer that their work order has been completed.
     *
     * The template includes customer name, work order number, vehicle details, completion date, optional final amount, and technician notes.
     *
     * @param array $workOrder Associative array containing work order and customer details.
     * @return string The HTML email content for work order completion notification.
     */
    private function getWorkOrderCompletedTemplate($workOrder) {
        $fromName = defined('FROM_NAME') ? FROM_NAME : 'Bengkel Management System';
        return "
        <html>
        <head>
            <style>
                .email-container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; }
                .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: white; }
                .details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { background: #6c757d; color: white; padding: 15px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h2>✅ Work Order Completed</h2>
                </div>
                <div class='content'>
                    <p>Dear {$workOrder['customer_name']},</p>
                    <p>Great news! Your work order has been completed. Your vehicle is ready for pickup!</p>
                    
                    <div class='details'>
                        <h4>Completion Details:</h4>
                        <p><strong>Work Order Number:</strong> {$workOrder['work_order_number']}</p>
                        <p><strong>Vehicle:</strong> {$workOrder['brand']} {$workOrder['model']} ({$workOrder['license_plate']})</p>
                        <p><strong>Completed Date:</strong> " . date('d/m/Y H:i', strtotime($workOrder['actual_completion_date'] ?? 'now')) . "</p>
                        " . (!empty($workOrder['final_amount']) ? "<p><strong>Total Amount:</strong> Rp " . number_format($workOrder['final_amount'], 0, ',', '.') . "</p>" : "") . "
                        " . (!empty($workOrder['technician_notes']) ? "<p><strong>Technician Notes:</strong> {$workOrder['technician_notes']}</p>" : "") . "
                    </div>
                    
                    <p>Please contact us to arrange pickup of your vehicle. Thank you for your business!</p>
                </div>
                <div class='footer'>
                    <p>{$fromName}</p>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Generates an HTML email template for a low stock alert.
     *
     * The template lists items that are below their minimum stock levels, including item name, current stock, unit of measure, and minimum stock threshold.
     *
     * @param array $items List of items with low stock, each containing 'name', 'current_stock', 'unit_of_measure', and 'minimum_stock'.
     * @return string The HTML content for the low stock alert email.
     */
    private function getLowStockTemplate($items) {
        $fromName = defined('FROM_NAME') ? FROM_NAME : 'Bengkel Management System';
        $itemsList = '';
        foreach($items as $item) {
            $itemsList .= "<li>{$item['name']} - Stock: {$item['current_stock']} {$item['unit_of_measure']} (Min: {$item['minimum_stock']})</li>";
        }
        
        return "
        <html>
        <head>
            <style>
                .email-container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; }
                .header { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: white; }
                .footer { background: #6c757d; color: white; padding: 15px; text-align: center; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h2>⚠️ Low Stock Alert</h2>
                </div>
                <div class='content'>
                    <div class='warning'>
                        <h4>The following items are running low on stock:</h4>
                        <ul>{$itemsList}</ul>
                        <p><strong>Please restock these items as soon as possible to avoid service interruptions.</strong></p>
                    </div>
                </div>
                <div class='footer'>
                    <p>{$fromName}</p>
                    <p>This is an automated alert message.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Sends an HTML email using PHP's native mail() function as a fallback.
     *
     * Uses default sender and reply-to addresses if not defined. Logs an error if sending fails.
     *
     * @param string $to Recipient email address.
     * @param string $subject Email subject.
     * @param string $message HTML email body.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    private function sendFallbackEmail($to, $subject, $message) {
        // Fallback using PHP's mail() function
        $headers = "From: " . (defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@localhost') . "\r\n";
        $headers .= "Reply-To: " . (defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@localhost') . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $result = mail($to, $subject, $message, $headers);
        
        if (!$result) {
            error_log("Fallback email failed to send to: $to");
        }
        
        return $result;
    }
}
?>
