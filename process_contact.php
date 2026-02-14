<?php

// تأكد من تضمين ملف الاتصال بقاعدة البيانات
require_once __DIR__ . '/includes/db.php'; 
global $pdo;

// التأكد من أن الطلب تم إرساله بطريقة POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: contact.php");
    exit;
}

// 1. جمع البيانات المرسلة وتنظيفها
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? 'رسالة تواصل جديدة');
$message_content = trim($_POST['message'] ?? '');

// 2. التحقق من صحة البيانات الأساسية
if (empty($name) || empty($email) || empty($message_content) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error_msg = urlencode("يرجى ملء جميع الحقول الضرورية وإدخال بريد إلكتروني صحيح.");
    header("Location: contact.php?status=error&msg=$error_msg");
    exit;
}

// 3. ** التخزين في قاعدة البيانات **
$db_insert_success = false;
try {
    $sql = "INSERT INTO contact_messages (name, email, subject, message_content, created_at) 
            VALUES (:name, :email, :subject, :message_content, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':subject' => $subject,
        ':message_content' => $message_content
    ]);
    
    $db_insert_success = true;

} catch (PDOException $e) {
    // تسجيل الخطأ في السجلات وعدم إيقاف العملية (البريد لا يزال يمكن إرساله)
    error_log("Database error storing contact message: " . $e->getMessage());
}

// 4. ** إرسال البريد الإلكتروني **
$email_sent_success = false;

// عنوان البريد الذي ستصلك عليه الرسائل
$to = "your.admin.email@example.com"; // **⚠️ يجب تغيير هذا البريد إلى بريدك الفعلي ⚠️**
    
// محتوى الرسالة
$email_body = "رسالة جديدة من نموذج الاتصال في منصة خدماتي.\n\n" .
              "الاسم: " . $name . "\n" .
              "البريد الإلكتروني: " . $email . "\n" .
              "الموضوع: " . $subject . "\n\n" .
              "محتوى الرسالة:\n" . $message_content;
              
// الرؤوس (Headers) لضمان أن الرد يذهب إلى مرسل النموذج
$headers = "From: " . $name . " <" . $email . ">\r\n";
$headers .= "Reply-To: " . $email . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// محاولة الإرسال
if (mail($to, $subject, $email_body, $headers)) {
    $email_sent_success = true;
}


// 5. إعادة التوجيه بناءً على النتائج

if ($db_insert_success && $email_sent_success) {
    // نجاح كامل
    header("Location: contact.php?status=success");
} elseif ($db_insert_success && !$email_sent_success) {
    // نجاح في التخزين، فشل في البريد (وهذا مقبول)
    $msg = urlencode("تم استلام رسالتك بنجاح وحفظها في قاعدة البيانات .");
    header("Location: contact.php?status=success&msg=$msg");
} else {
    // فشل في التخزين والبريد
    $msg = urlencode("عذراً، لم نتمكن من تسجيل رسالتك في الوقت الحالي. يرجى المحاولة لاحقاً أو التواصل عبر الهاتف.");
    header("Location: contact.php?status=error&msg=$msg");
}

exit;
?>