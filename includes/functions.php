<?php

// **********************************************************
// 1. دوال إدارة الرسائل (Messages)
// **********************************************************

function set_message($message, $type = 'success') {
    // نستخدم $_SESSION لتخزين الرسالة ونوعها (success, danger, warning)
    $_SESSION['message'] = [
        'content' => $message,
        'type' => $type
    ];
}

function display_message() {
    if (isset($_SESSION['message'])) {
        $msg = $_SESSION['message'];
        // عرض الرسالة بنمط HTML أساسي
        echo '<div class="alert alert-' . htmlspecialchars($msg['type']) . '" style="padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid;">';
        echo htmlspecialchars($msg['content']);
        echo '</div>';
        unset($_SESSION['message']); // حذف الرسالة بعد عرضها
    }
}


// **********************************************************
// 2. دوال حالة المستخدم والبيانات (تعتمد على $_SESSION)
// **********************************************************

/**
 * التحقق مما إذا كان المستخدم مسجلاً دخوله.
 * @return bool
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * جلب معلومات المستخدم الحالي. تعتمد على دالة getCurrentUser() المعرّفة في auth.php.
 * @return array|null
 */
function current_user(): ?array
{
    // نعتمد على أن getCurrentUser() تم تعريفها عبر تضمين auth.php في الصفحة الرئيسية.
    if (function_exists('getCurrentUser')) {
        return getCurrentUser();
    }
    return null;
}

/**
 * التحقق من صحة المدخلات.
 * @param string $data
 * @return string
 */
function sanitize_input($data): string
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}


// **********************************************************
// 3. دوال CSRF Token
// **********************************************************

/**
 * يولد ويخزن رمز CSRF جديد.
 * @return string رمز CSRF الجديد.
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        // إنشاء رمز بطول 32 بايت (64 حرفاً سداسياً عشرياً)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * يتحقق من صحة رمز CSRF المرسل مع الطلب.
 * @param string $token الرمز المرسل من النموذج.
 * @return bool
 */
function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// **********************************************************
// 4. دوال الصلاحيات والحماية (تعتمد على set_message و current_user)
// **********************************************************

/**
 * تتحقق مما إذا كان المستخدم الحالي يمتلك دور معين.
 * @param string $required_role الدور المطلوب للوصول.
 * @return bool
 */
function is_user_role($required_role): bool
{
    $user = current_user();
    if ($user && isset($user['role'])) {
        // التحقق من تطابق دور المستخدم مع الدور المطلوب
        return $user['role'] === $required_role;
    }
    return false;
}

/**
 * دالة حماية الصفحة: تتحقق من تسجيل الدخول والدور المطلوب.
 * إذا فشل التحقق، تقوم بالتحويل إلى صفحة الدخول أو لوحة التحكم الخاصة بالمستخدم.
 * @param string $required_role الدور المطلوب ('admin', 'provider', 'client').
 */
function check_login($required_role = 'client')
{
    $user = current_user();

    // تحقق 1: هل المستخدم مسجل دخوله؟
    if (!$user) {
        set_message("يجب تسجيل الدخول للوصول لهذه الصفحة.", "warning");
        header("Location: /local_services/login.php");
        exit();
    }

    // تحقق 2: هل لديه الدور المطلوب؟
    if ($user['role'] !== $required_role) {
        set_message("لا تملك صلاحية الوصول لهذه الصفحة.", "danger");

        // التوجيه إلى لوحة التحكم الصحيحة
        if ($user['role'] === 'client') {
             header("Location: /local_services/client_dashboard.php");
        } else if ($user['role'] === 'provider' || $user['role'] === 'admin') {
             header("Location: /local_services/dashboard.php"); // لوحة تحكم المزود/المدير
        } else {
             // حالة الدور غير المعروف
             header("Location: /local_services/login.php");
        }
        exit();
    }
}

// **********************************************************
// 5. دوال إدارة حالة الطلبات (Order Status Helper)ة
// **********************************************************

/**
 * تحدد النص العربي المناسب لعرض حالة الطلب.
 * @param string|null $status حالة الطلب (pending, processing, completed, cancelled).
 * @return string النص العربي.
 */
if (!function_exists('translate_status')) {
    function translate_status($status) {
        $status = $status ?: 'pending';
        switch ($status) {
            case 'pending': return 'قيد الانتظار';
            case 'processing': return 'قيد المعالجة';
            case 'in_progress': return 'قيد التنفيذ';
            case 'completed': return 'مكتمل';
            case 'cancelled': return 'ملغي';
            default: return 'غير محدد';
        }
    }
}

/**
 * تحدد اللون المناسب (Hex Code) لعرض حالة الطلب.
 * @param string|null $status حالة الطلب (pending, processing, completed, cancelled).
 * @return string رمز اللون (Hex Code).
 */
if (!function_exists('get_status_color')) {
    function get_status_color($status) {
        $status = $status ?: 'pending';
        switch ($status) {
            case 'pending': return '#ffc107';      // Warning / أصفر
            case 'processing': return '#17a2b8';   // Info / أزرق فاتح
            case 'in_progress': return '#007bff';  // Primary / أزرق
            case 'completed': return '#28a745';    // Success / أخضر
            case 'cancelled': return '#dc3545';    // Danger / أحمر
            default: return '#6c757d';             // Secondary / رمادي
        }
    }
}

/**
 * تعيد شارة HTML جاهزة للعرض بنظام الألوان.
 * @param string|null $status حالة الطلب.
 * @return string HTML Span Tag.
 */
if (!function_exists('get_status_display')) {
    function get_status_display($status) {
        $translated_status = translate_status($status);
        $color = get_status_color($status);
        
        // إنشاء شارة HTML أنيقة لعرض الحالة
        return "<span style='background-color: {$color}; color: white; padding: 5px 10px; border-radius: 4px; font-weight: bold; min-width: 100px; display: inline-block; text-align: center;'>{$translated_status}</span>";
    }
}


// **********************************************************
// 6. دوال رفع الملفات (تستخدم في إدارة التصنيفات والخدمات)
// **********************************************************

/**
 * رفع ملف صورة إلى المجلد المحدد.
 * @param array $file_array مصفوفة $_FILES للملف المرفوع.
 * @param string $destination_folder_name اسم المجلد (مثل 'icons' أو 'uploads').
 * @return string اسم الملف الجديد بعد الرفع.
 * @throws Exception إذا فشل الرفع.
 */
function upload_file($file_array, $destination_folder_name) {
    
    if ($file_array['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("خطأ في رفع الملف.");
    }
    
    $file_extension = pathinfo($file_array['name'], PATHINFO_EXTENSION);
    $new_file_name = uniqid() . '.' . $file_extension;
    // مسار الهدف: assets/{destination_folder_name}/
    $target_dir = __DIR__ . "/../assets/" . $destination_folder_name . "/";
    $target_file = $target_dir . $new_file_name;

    // إنشاء المجلد إذا لم يكن موجوداً
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // نقل الملف المرفوع
    if (move_uploaded_file($file_array["tmp_name"], $target_file)) {
        return $new_file_name;
    } else {
        throw new Exception("فشل نقل الملف المرفوع.");
    }
}
