<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'admin') {
    header("Location: Login.php");
    exit();
}

include 'db.php';
include 'mail.php'; // Include the mail functions

// Validate POST input
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentID = $_POST['AppointmentID'] ?? null;
    $status = $_POST['Status'] ?? null;
    $redirectView = $_POST['view'] ?? 'appointments'; // keeps the same tab

    if (!$appointmentID || !$status) {
        $_SESSION['flash_error'] = "Invalid request.";
        header("Location: admin_dashboard.php?view=" . urlencode($redirectView));
        exit();
    }

    // First, get appointment details including user email
    $stmt = $conn->prepare("
        SELECT a.*, u.Email, u.Name as UserName, b.Name as BarberName 
        FROM Appointment a 
        JOIN User u ON a.UserID = u.UserID 
        JOIN Barber b ON a.BarberID = b.BarberID 
        WHERE a.AppointmentID = ?
    ");
    $stmt->bind_param("i", $appointmentID);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();

    if (!$appointment) {
        $_SESSION['flash_error'] = "Appointment not found.";
        header("Location: admin_dashboard.php?view=" . urlencode($redirectView));
        exit();
    }

    // Update status in database
    $stmt = $conn->prepare("UPDATE Appointment SET Status = ? WHERE AppointmentID = ?");
    $stmt->bind_param("si", $status, $appointmentID);

    if ($stmt->execute()) {
        $_SESSION['flash_message'] = "Appointment status updated successfully.";
        
        // Send email notification to client
        sendAppointmentStatusEmail($appointment, $status);
        
    } else {
        $_SESSION['flash_error'] = "Failed to update status: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    // Redirect back to the admin dashboard on the same tab
    header("Location: admin_dashboard.php?view=" . urlencode($redirectView));
    exit();
} else {
    // Invalid access
    $_SESSION['flash_error'] = "Invalid request method.";
    header("Location: admin_dashboard.php");
    exit();
}

/**
 * Send email notification for appointment status change
 */
function sendAppointmentStatusEmail($appointment, $newStatus) {
    $userEmail = $appointment['Email'];
    $userName = $appointment['UserName'];
    $barberName = $appointment['BarberName'];
    $appointmentTime = date('F j, Y \a\t g:i A', strtotime($appointment['Time']));
    $forName = $appointment['ForName'];
    $serviceType = $appointment['Type'];
    
    // Map status to friendly names and colors
    $statusInfo = [
        'pending' => ['name' => 'Pending', 'color' => '#FFA500'],
        'confirmed' => ['name' => 'Confirmed', 'color' => '#007BFF'],
        'completed' => ['name' => 'Completed', 'color' => '#28A745'],
        'cancelled' => ['name' => 'Cancelled', 'color' => '#DC3545'],
        'no_show' => ['name' => 'No Show', 'color' => '#6C757D']
    ];
    
    $statusName = $statusInfo[$newStatus]['name'] ?? ucfirst($newStatus);
    $statusColor = $statusInfo[$newStatus]['color'] ?? '#6C757D';
    
    $subject = "Appointment Status Update - Your appointment is now {$statusName}";
    
    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 5px; }
            .status-badge { 
                display: inline-block; 
                padding: 8px 16px; 
                background: {$statusColor}; 
                color: white; 
                border-radius: 20px; 
                font-weight: bold; 
                margin: 10px 0; 
            }
            .appointment-details { 
                background: #f8f9fa; 
                padding: 15px; 
                border-radius: 5px; 
                margin: 15px 0; 
            }
            .footer { 
                margin-top: 20px; 
                padding-top: 20px; 
                border-top: 1px solid #dee2e6; 
                text-align: center; 
                color: #6c757d; 
                font-size: 14px; 
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Appointment Status Update</h2>
            </div>
            
            <p>Hello {$userName},</p>
            
            <p>The status of your appointment has been updated:</p>
            
            <div style='text-align: center;'>
                <div class='status-badge'>{$statusName}</div>
            </div>
            
            <div class='appointment-details'>
                <h3>Appointment Details:</h3>
                <p><strong>For:</strong> {$forName}</p>
                <p><strong>Service:</strong> {$serviceType}</p>
                <p><strong>Barber:</strong> {$barberName}</p>
                <p><strong>Date & Time:</strong> {$appointmentTime}</p>
                <p><strong>Duration:</strong> {$appointment['Duration']} minutes</p>
            </div>";
    
    // Add status-specific messages
    switch($newStatus) {
        case 'confirmed':
            $htmlBody .= "
            <p>Your appointment has been confirmed! We look forward to seeing you.</p>
            <p>Please arrive 5-10 minutes before your scheduled time.</p>";
            break;
        case 'completed':
            $htmlBody .= "
            <p>Thank you for choosing our services! We hope you had a great experience.</p>
            <p>We'd love to hear your feedback about your appointment.</p>";
            break;
        case 'cancelled':
            $htmlBody .= "
            <p>Your appointment has been cancelled. If this was unexpected or you'd like to reschedule, 
            please contact us as soon as possible.</p>";
            break;
        case 'no_show':
            $htmlBody .= "
            <p>We noticed you weren't able to make it to your scheduled appointment.</p>
            <p>If you'd like to reschedule, please contact us at your earliest convenience.</p>";
            break;
        default:
            $htmlBody .= "
            <p>If you have any questions about this status update, please don't hesitate to contact us.</p>";
    }
    
    $htmlBody .= "
            <p>Best regards,<br>The Barber Shop Team</p>
            
            <div class='footer'>
                <p>This is an automated notification. Please do not reply to this email.</p>
                <p>If you have any questions, please contact our support team.</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Optional: Add BCC for admin notifications
    $bcc = ['admin@yourbarbershop.com']; // Add admin email for tracking
    
    // Send the email
    return sendEmail($userEmail, $subject, $htmlBody, $bcc);
}
?>
