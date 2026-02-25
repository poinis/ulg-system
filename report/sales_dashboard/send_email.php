<?php
/**
 * Send Email Report
 */
require_once 'config.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? date('Y-m-d');

try {
    $pdo = getDB();
    
    // Get summary
    $stmt = $pdo->prepare("SELECT * FROM daily_summary WHERE sale_date = ?");
    $stmt->execute([$date]);
    $summary = $stmt->fetch();
    
    if (!$summary) {
        throw new Exception("ไม่พบข้อมูลวันที่ $date");
    }
    
    // Build email body
    $body = "
รายงานยอดขายประจำวันที่ " . formatDate($date) . "

📊 สรุปยอดขาย:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🏪 SPD-Offline        : " . number_format($summary['spd_offline'], 2) . " บาท
🏪 Pronto-Offline     : " . number_format($summary['pronto_offline'], 2) . " บาท
🌐 Pronto-Online      : " . number_format($summary['pronto_online'], 2) . " บาท
🏪 Freitag            : " . number_format($summary['freitag'], 2) . " บาท
🏪 Topo-Offline       : " . number_format($summary['topo_offline'], 2) . " บาท
🌐 Topo-Online        : " . number_format($summary['topo_online'], 2) . " บาท
🏪 IZIPIZI            : " . number_format($summary['izipizi'], 2) . " บาท
🏪 Hooga              : " . number_format($summary['hooga'], 2) . " บาท
🏪 Soup               : " . number_format($summary['soup'], 2) . " บาท
🏪 SW19               : " . number_format($summary['sw19'], 2) . " บาท
🌐 SW19-Lazada        : " . number_format($summary['sw19_lazada'], 2) . " บาท
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   รวม Offline        : " . number_format($summary['total_offline'], 2) . " บาท
   รวม Online         : " . number_format($summary['total_online'], 2) . " บาท
   รวมทั้งหมด         : " . number_format($summary['grand_total'], 2) . " บาท

---
Sales Dashboard
";

    // Send email using PHPMailer or native mail()
    $to = implode(', ', EMAIL_RECIPIENTS);
    $subject = "📊 รายงานยอดขาย " . formatDate($date);
    $headers = [
        'From' => EMAIL_FROM_NAME . ' <' . EMAIL_FROM . '>',
        'Content-Type' => 'text/plain; charset=UTF-8',
    ];
    
    // Try using PHPMailer if available, otherwise use mail()
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // Use PHPMailer
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        foreach (EMAIL_RECIPIENTS as $recipient) {
            $mail->addAddress($recipient);
        }
        
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        $mail->send();
    } else {
        // Use native mail() - may not work on all servers
        $headerStr = '';
        foreach ($headers as $key => $value) {
            $headerStr .= "$key: $value\r\n";
        }
        mail($to, $subject, $body, $headerStr);
    }
    
    echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
