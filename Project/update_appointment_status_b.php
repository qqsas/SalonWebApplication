<?php
session_start();
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'barber') {
    header("Location: Login.php");
    exit();
}

include 'db.php';
include 'mail.php'; // Include the mail functions

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentID = $_POST['AppointmentID'] ?? null;
    $status = $_POST['Status'] ?? '';
    $redirect = $_POST['redirect'] ?? 'barber_dashboard.php?view=appointments';
    
    if ($appointmentID && $status) {
        // Verify the appointment belongs to this barber and get appointment details
        $stmt = $conn->prepare("
            SELECT a.*, u.Email, u.Name as UserName, b.Name as BarberName 
            FROM Appointment a 
            JOIN User u ON a.UserID = u.UserID 
            JOIN Barber b ON a.BarberID = b.BarberID 
            WHERE a.AppointmentID = ? AND a.BarberID = ?
        ");
        $stmt->bind_param("ii", $appointmentID, $_SESSION['BarberID']);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment = $result->fetch_assoc();
        
        if ($appointment) {
            // Update appointment status
            $stmt = $conn->prepare("UPDATE Appointment SET Status = ? WHERE AppointmentID = ?");
            $stmt->bind_param("si", $status, $appointmentID);
            
            if ($stmt->execute()) {
                // Send email notification to client
                sendAppointmentStatusEmail($appointment, $status);
                header("Location: $redirect&message=Status updated successfully&success=1");
            } else {
                header("Location: $redirect&message=Error updating status&success=0");
            }
        } else {
            header("Location: $redirect&message=Appointment not found&success=0");
        }
    } else {
        header("Location: $redirect&message=Invalid data&success=0");
    }
} else {
    header("Location: barber_dashboard.php");
}
exit();

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
    
    $subject = "Appointment Status Update - Your appointment with {$barberName} is now {$statusName}";
    
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
            .barber-note {
                background: #e7f3ff;
                padding: 15px;
                border-left: 4px solid #007BFF;
                margin: 15px 0;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Appointment Status Update</h2>
                <p>From: {$barberName}</p>
            </div>
            
            <p>Hello {$userName},</p>
            
            <p>Your barber, {$barberName}, has updated the status of your appointment:</p>
            
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
                <p><strong>Cost:</strong> $" . number_format($appointment['Cost'], 2) . "</p>
            </div>";
    
    // Add status-specific messages
    switch($newStatus) {
        case 'confirmed':
            $htmlBody .= "
            <div class='barber-note'>
                <p><strong>Note from your barber:</strong></p>
                <p>Your appointment has been confirmed! I look forward to seeing you for your {$serviceType}.</p>
                <p>Please arrive 5-10 minutes before your scheduled time to ensure we can start promptly.</p>
            </div>";
            break;
        case 'completed':
            $htmlBody .= "
            <div class='barber-note'>
                <p><strong>Note from your barber:</strong></p>
                <p>Thank you for your visit! It was a pleasure serving you.</p>
                <p>I hope you're happy with your {$serviceType}. If you have any feedback or would like to schedule your next appointment, please let me know!</p>
            </div>";
            break;
        case 'cancelled':
            $htmlBody .= "
            <div class='barber-note'>
                <p><strong>Note from your barber:</strong></p>
                <p>I'm sorry, but I've had to cancel your appointment. This could be due to unforeseen circumstances or scheduling conflicts.</p>
                <p>I apologize for any inconvenience this may cause. Please feel free to book another appointment at your convenience.</p>
            </div>";
            break;
        case 'no_show':
            $htmlBody .= "
            <div class='barber-note'>
                <p><strong>Note from your barber:</strong></p>
                <p>I noticed we missed you for your scheduled appointment.</p>
                <p>If you'd still like to get your {$serviceType}, I'd be happy to reschedule. Please contact me to find a new time that works for you.</p>
            </div>";
            break;
        default:
            $htmlBody .= "
            <div class='barber-note'>
                <p><strong>Note from your barber:</strong></p>
                <p>The status of your appointment has been updated. If you have any questions about this change, please don't hesitate to reach out.</p>
            </div>";
    }
    
    $htmlBody .= "
            <p>Best regards,<br>{$barberName}</p>
            
            <div class='footer'>
                <p>This is an automated notification from your barber. Please do not reply to this email.</p>
                <p>If you need to contact {$barberName} directly, please use the contact information provided in your appointment confirmation.</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Optional: Add BCC for barber/admin notifications
    $bcc = [
        'barbers@yourbarbershop.com', // Barber management email
        // You could also add the barber's email if stored in session/database
    ];
    
    // Send the email
    return sendEmail($userEmail, $subject, $htmlBody, $bcc);
}
?>
