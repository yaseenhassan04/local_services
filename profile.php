<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
global $pdo;

$user = getCurrentUser();
$user_id = $user['id'];
$csrf_token = generateCsrfToken();

$errors = [];
$success_message = '';

// تهيئة المتغيرات للنموذج بقيم المستخدم الحالية
$full_name = htmlspecialchars($user['full_name'] ?? '');
$email = htmlspecialchars($user['email'] ?? '');
$city = htmlspecialchars($user['city'] ?? '');
$phone = htmlspecialchars($user['phone'] ?? '');

// ==========================================================
// 1. معالجة طلب تحديث البيانات الشخصية (POST)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    
    // التحقق من CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "خطأ في أمان النموذج (CSRF). حاول مرة أخرى.";
    }

    $new_full_name = trim($_POST['full_name'] ?? '');
    $new_city = trim($_POST['city'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');
    
    if (empty($new_full_name)) {
        $errors[] = "يجب إدخال الاسم الكامل.";
    }
    if (empty($new_city)) {
        $errors[] = "يجب تحديد المدينة.";
    }
    if (empty($new_phone)) {
        $errors[] = "يجب إدخال رقم الهاتف.";
    }

    if (empty($errors)) {
        try {
            $sql = "UPDATE users SET full_name = ?, city = ?, phone = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_full_name, $new_city, $new_phone, $user_id]);

            // تحديث البيانات المعروضة في الصفحة بعد النجاح
            $_SESSION['user']['full_name'] = $new_full_name;
            $_SESSION['user']['city'] = $new_city;
            $_SESSION['user']['phone'] = $new_phone;

            set_message("تم تحديث الملف الشخصي بنجاح.", 'success');
            // إعادة توجيه لمنع إرسال النموذج مرة أخرى
            header("Location: profile.php");
            exit;

        } catch (PDOException $e) {
            $errors[] = "حدث خطأ في قاعدة البيانات أثناء التحديث.";
            // يمكنك طباعة $e->getMessage() للتصحيح
        }
    }
    
    // إذا كان هناك خطأ، نحتفظ بالمدخلات الجديدة في النموذج
    $full_name = htmlspecialchars($new_full_name);
    $city = htmlspecialchars($new_city);
    $phone = htmlspecialchars($new_phone);
}


// ==========================================================
// 2. معالجة طلب تحديث كلمة المرور (POST)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    
    // التحقق من CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "خطأ في أمان النموذج (CSRF). حاول مرة أخرى.";
    }

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // التحقق من كلمة المرور الحالية
    if (!password_verify($current_password, $user['password'])) {
        $errors[] = "كلمة المرور الحالية غير صحيحة.";
    }

    // التحقق من كلمة المرور الجديدة
    if (strlen($new_password) < 6) {
        $errors[] = "يجب أن تكون كلمة المرور الجديدة 6 أحرف على الأقل.";
    }
    if ($new_password !== $confirm_password) {
        $errors[] = "كلمة المرور الجديدة وتأكيدها غير متطابقين.";
    }

    if (empty($errors)) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$hashed_password, $user_id]);

            set_message("تم تحديث كلمة المرور بنجاح. يجب تسجيل الدخول مجدداً.", 'success');
            
            // تسجيل خروج المستخدم بعد تغيير كلمة المرور لأسباب أمنية
            session_destroy();
            header("Location: login.php");
            exit;

        } catch (PDOException $e) {
            $errors[] = "حدث خطأ في قاعدة البيانات أثناء تحديث كلمة المرور.";
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>


<div class="profile-container">
    <h2>تعديل الملف الشخصي</h2>
    
    <?php display_message(); ?>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">البيانات الأساسية</h3>
    <form method="POST" action="profile.php">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="update_profile" value="1">

        <div class="form-group">
            <label for="full_name">الاسم الكامل:</label>
            <input type="text" id="full_name" name="full_name" value="<?php echo $full_name; ?>" required>
        </div>

        <div class="form-group">
            <label for="email">البريد الإلكتروني (لا يمكن تعديله):</label>
            <input type="email" id="email" name="email" value="<?php echo $email; ?>" disabled>
        </div>
        
        

        <div class="form-group">
            <label for="phone">رقم الهاتف:</label>
            <input type="text" id="phone" name="phone" value="<?php echo $phone; ?>" required>
        </div>

        <button type="submit" class="btn-primary">حفظ التغييرات</button>
    </form>

    <div class="section-separator">تغيير كلمة المرور</div>
    
    <form method="POST" action="profile.php">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="update_password" value="1">

        <div class="form-group">
            <label for="current_password">كلمة المرور الحالية:</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>

        <div class="form-group">
            <label for="new_password">كلمة المرور الجديدة:</label>
            <input type="password" id="new_password" name="new_password" required minlength="6">
        </div>

        <div class="form-group">
            <label for="confirm_password">تأكيد كلمة المرور الجديدة:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>

        <button type="submit" class="btn-primary" style="background: #dc3545;">تغيير كلمة المرور</button>
    </form>
    
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>