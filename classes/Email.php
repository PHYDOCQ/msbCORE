<?php
// Check if autoloader exists and define availability
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    define('PHPMAILER_AVAILABLE', true);
} else {
    define('PHPMAILER_AVAILABLE', false);
}

// Import PHPMailer classes if available
if (PHPMAILER_AVAILABLE) {
    // These will only be used if PHPMailer is available
    class_alias('PHPMailer\PHPMailer\PHPMailer', 'PHPMailer_PHPMailer');
    class_alias('PHPMailer\PHPMailer\SMTP', 'PHPMailer_SMTP');
    class_alias('PHPMailer\PHPMailer\Exception', 'PHPMailer_Exception');
}

class Email {
    private $mailer;
    private $phpmailerAvailable;
    
    public function __construct() {
        $this->phpmailerAvailable = PHPMAILER_AVAILABLE;
        
        if ($this->phpmailerAvailable) {
            $this->mailer = new PHPMailer_PHPMailer(true);
            $this->configure();
        } else {
            error_log("PHPMailer not available - email functionality disabled");
        }
    }
    
    private function configure() {
        if (!$this->phpmailerAvailable) {
            return false;
        }
        
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = defined('SMTP_USER') ? SMTP_USER : '';
            $this->mailer->Password = defined('SMTP_PASS') ? SMTP_PASS : '';
            $this->mailer->SMTPSecure = $this->phpmailerAvailable ? PHPMailer_PHPMailer::ENCRYPTION_STARTTLS : 'tls';
            $this->mailer->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
            
            $this->mailer->setFrom(
                defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@localhost', 
                defined('FROM_NAME') ? FROM_NAME : 'System'
            );
            $this->mailer->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            error_log("Email configuration failed: " . $e->getMessage());
        }
    }
    
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
