<?php

// تأكد من بدء الجلسة في حال لم تبدأ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🛑 تضمين ملف db.php الذي يُعرّف المتغير $pdo (اتصال PDO)
require_once __DIR__ . '/db.php';


// ==========================================================
// منطق تسجيل الدخول والحماية
// ==========================================================

/**
 * لجلب بيانات المستخدم الحالي من قاعدة البيانات
 *
 * @return array|null بيانات المستخدم أو null إذا لم يكن مسجلاً.
 */
function getCurrentUser() {
    // نستخدم $pdo كـ global للوصول لاتصال قاعدة البيانات
    global $pdo; 
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    // استعلام آمن لجلب بيانات المستخدم بناءً على ID الجلسة
    $stmt = $pdo->prepare("SELECT id, email, full_name, phone, role, is_active FROM users WHERE id = ?");
    
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC); // جلب البيانات بطريقة PDO
    
    return $user;
}

/**
 * يفرض تسجيل الدخول على الصفحة.
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /local_services/login.php");
        exit;
    }
}