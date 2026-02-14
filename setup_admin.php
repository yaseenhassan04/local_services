<?php

require_once __DIR__ . '/includes/db.php';

// بيانات المستخدم المسؤول (Admin)
$email = 'admin@admin.com';
$pass = 'admin123';
$name = 'Admin';

// 1. التحقق إن كان المستخدم موجوداً بالفعل باستخدام $pdo
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);

if ($stmt->fetch()) {
    echo '❌ Admin already exists.';
    exit;
}

// 2. إنشاء المستخدم وإضافة كلمة المرور مشفرة 
$hash = password_hash($pass, PASSWORD_DEFAULT);

// 🛑 تم تغيير اسم العمود هنا إلى 'password_hash'
$stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');

if ($stmt->execute([$name, $email, $hash, 'admin'])) {
    echo '✅ Admin user created successfully. **Email: admin@admin.com, Password: admin123**. **Please delete this file after use.**';
} else {
    echo '❌ Failed to create Admin user.';
}





?>