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
            if (defined('PHPMAILER_AVAILABLE')) {
                // Redefine constant safely
                define('PHPMAILER_FALLBACK', true);
            } else {
                define('PHPMAILER_AVAILABLE', false);
                define('PHPMAILER_FALLBACK', true);
            }
        }
    } catch (Exception $e) {
        error_log("PHPMailer class aliasing failed: " . $e->getMessage());
        define('PHPMAILER_FALLBACK', true);
    }
}

class Email {
    private $mailer;
    private $phpmailerAvailable;
    private $fallbackMode;
    
    /**
     * Initializes the Email class, setting up PHPMailer if available or enabling fallback mode.
     *
     * Attempts to configure PHPMailer for SMTP email sending. If PHPMailer is unavailable or initialization fails, enables fallback mode to use PHP's native mail functionality.
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
     * Configures the PHPMailer instance with SMTP settings and sender information.
     *
     * Sets SMTP host, authentication, credentials, encryption, port, sender email, and character encoding.
     * Enables fallback mode and logs an error if configuration fails.
     *
     * @return bool True if configuration succeeds, false otherwise.
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
            
            // Use constant if available, otherwise fallback to string
            if (defined('PHPMailer_PHPMailer::ENCRYPTION_STARTTLS')) {
                $this->mailer->SMTPSecure = PHPMailer_PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $this->mailer->SMTPSecure = 'tls';
            }
            
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
     * Sends an email notification to the customer when a work order is created.
     *
     * @param array $workOrder Associative array containing work order and customer details.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public function sendWorkOrderCreated($workOrder) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($workOrder['customer_email'], $workOrder['customer_name']);
            
            $this->mailer->Subject = "Work Order Created - {$workOrder['work_order_number']}";
            
            $body = $this->getWorkOrderCreatedTemplate($workOrder);
            $this->mailer->msgHTML($body);
            
            return $this->mailer->send();
            
        } catch (Exception $e) {
            error_log("Email send failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sends an email notification to the customer when a work order is completed.
     *
     * @param array $workOrder The work order data, including customer and completion details.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public function sendWorkOrderCompleted($workOrder) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($workOrder['customer_email'], $workOrder['customer_name']);
            
            $this->mailer->Subject = "Work Order Completed - {$workOrder['work_order_number']}";
            
            $body = $this->getWorkOrderCompletedTemplate($workOrder);
            $this->mailer->msgHTML($body);
            
            return $this->mailer->send();
            
        } catch (Exception $e) {
            error_log("Email send failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sends a low stock alert email to all active admin users.
     *
     * Each admin receives an email listing the items that are low in stock.
     *
     * @param array $items List of items that are low in stock.
     * @return bool True if emails were sent successfully to all admins, false if an error occurred.
     */
    public function sendLowStockAlert($items) {
        try {
            // Send to admin users
            $admins = Database::getInstance()->getConnection()
                     ->query("SELECT email, full_name FROM users WHERE role = 'admin' AND is_active = 1")
                     ->fetchAll();
            
            foreach($admins as $admin) {
                $this->mailer->clearAddresses();
                $this->mailer->addAddress($admin['email'], $admin['full_name']);
                
                $this->mailer->Subject = "Low Stock Alert - " . count($items) . " Items";
                
                $body = $this->getLowStockTemplate($items);
                $this->mailer->msgHTML($body);
                
                $this->mailer->send();
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Email send failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generates an HTML email template for notifying a customer that a work order has been created.
     *
     * The template includes customer name, work order number, vehicle details, status, priority, damage description, and estimated cost if available.
     *
     * @param array $workOrder Associative array containing work order details.
     * @return string The HTML content for the work order creation notification email.
     */
    private function getWorkOrderCreatedTemplate($workOrder) {
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
                        <p><strong>Priority:</strong> " . ucfirst($workOrder['priority']) . "</p>
                        <p><strong>Damage Description:</strong> {$workOrder['damage_description']}</p>
                        " . (!empty($workOrder['estimated_cost']) ? "<p><strong>Estimated Cost:</strong> Rp " . number_format($workOrder['estimated_cost'], 0, ',', '.') . "</p>" : "") . "
                    </div>
                    
                    <p>We will keep you updated on the progress. Thank you for choosing our service!</p>
                </div>
                <div class='footer'>
                    <p>" . FROM_NAME . "</p>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Generates an HTML email template notifying a customer that their work order has been completed.
     *
     * The template includes work order details such as number, vehicle information, completion date, final amount, and technician notes if available.
     *
     * @param array $workOrder Associative array containing work order and customer details.
     * @return string The HTML content for the work order completion email.
     */
    private function getWorkOrderCompletedTemplate($workOrder) {
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
                        <p><strong>Completed Date:</strong> " . date('d/m/Y H:i', strtotime($workOrder['actual_completion_date'])) . "</p>
                        " . (!empty($workOrder['final_amount']) ? "<p><strong>Total Amount:</strong> Rp " . number_format($workOrder['final_amount'], 0, ',', '.') . "</p>" : "") . "
                        " . (!empty($workOrder['technician_notes']) ? "<p><strong>Technician Notes:</strong> {$workOrder['technician_notes']}</p>" : "") . "
                    </div>
                    
                    <p>Please contact us to arrange pickup of your vehicle. Thank you for your business!</p>
                </div>
                <div class='footer'>
                    <p>" . FROM_NAME . "</p>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Generates an HTML email template listing items that are low in stock.
     *
     * The template includes a styled list of items with their current stock, unit of measure, and minimum required stock, along with a warning message.
     *
     * @param array $items An array of items, each containing 'name', 'current_stock', 'unit_of_measure', and 'minimum_stock'.
     * @return string The HTML content for the low stock alert email.
     */
    private function getLowStockTemplate($items) {
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
                    <p>" . FROM_NAME . "</p>
                    <p>This is an automated alert message.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Sends an HTML email using PHP's native mail() function as a fallback.
     *
     * @param string $to Recipient email address.
     * @param string $subject Email subject.
     * @param string $message HTML email body.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    private function sendFallbackEmail($to, $subject, $message) {
        // Fallback using PHP's mail() function
        $headers = "From: " . (defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@localhost') . "
";
        $headers .= "Reply-To: " . (defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@localhost') . "
";
        $headers .= "Content-Type: text/html; charset=UTF-8
";
        
        $result = mail($to, $subject, $message, $headers);
        
        if (!$result) {
            error_log("Fallback email failed to send to: $to");
        }
        
        return $result;
    }
}
?>
