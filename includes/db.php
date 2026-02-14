<?php
// includes/db.php (النسخة المصححة)

$host = 'localhost';
$db   = 'local_services_db'; // 1. تأكد من اسم قاعدة البيانات
$user = 'root';
$pass = ''; // 2. تأكد من كلمة المرور (عادة فارغة في XAMPP)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// 💡 1. يتم تجميع جميع خيارات الاتصال هنا، بما في ذلك التعامل مع الأخطاء (PDO::ATTR_ERRMODE)
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // يجعل PDO يطلق استثناءات عند أخطاء SQL
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null; // تعريف مبدئي للمتغير

try {
    // 💡 2. هنا يتم إنشاء كائن PDO الفعلي وتعيين المتغير $pdo
    // هنا يتم تمرير مصفوفة الخيارات $options مباشرة
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // 🛑 تم حذف هذا السطر لأنه سبب الخطأ: $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // لقد قمت بمحاولة استخدام $pdo قبل تعريفه في النسخة الأصلية.
    
} catch (\PDOException $e) {
    // 🛑 عند فشل الاتصال، يتم عرض رسالة الخطأ وإنهاء البرنامج
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}

