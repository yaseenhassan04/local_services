<?php

// تأكد من صحة مسارات الملفات
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php'; 

// 1. حماية الصفحة: يجب أن يكون المدير فقط
check_login('admin'); 

global $pdo; // تأكد أن $pdo متاح عالمياً

$errors = [];

// ==========================================================
// 2. جلب بيانات المستخدم المستهدف
// ==========================================================

// جلب الـ ID بوضوح: استخدام فلتر آمن لضمان أنه رقم صحيح
$user_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$user_id = (int)$user_id;

// التحقق الصارم: يجب أن يكون الـ ID أكبر من صفر
if ($user_id <= 0) {
    set_message("معرف المستخدم غير صالح أو مفقود في الرابط.", "danger");
    header("Location: manage_users.php");
    exit();
}

try {
    // جلب بيانات المستخدم
    $stmt = $pdo->prepare("SELECT id, full_name, email, role, is_active, password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $target_user = $stmt->fetch(PDO::FETCH_ASSOC); 

    if (!$target_user) {
        set_message("المستخدم المطلوب غير موجود.", "danger");
        header("Location: manage_users.php");
        exit();
    }
} catch (PDOException $e) {
    set_message("فشل في جلب بيانات المستخدم: " . $e->getMessage(), "danger");
    header("Location: manage_users.php");
    exit();
}

// ==========================================================
// 3. معالجة تحديث المستخدم (POST Request)
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // البيانات الأساسية
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email     = sanitize_input($_POST['email'] ?? '');
    $role      = sanitize_input($_POST['role'] ?? $target_user['role']);
    $is_active = (int) sanitize_input($_POST['is_active'] ?? $target_user['is_active']);
    
    // بيانات كلمة المرور الجديدة
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $password_update_required = false;

    // ----------------------------------------------------------
    // 3.1. التحقق من صحة كلمة المرور الجديدة (إن وُجدت)
    // ----------------------------------------------------------
    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $errors[] = "كلمة المرور الجديدة وتأكيدها غير متطابقين.";
        } elseif (strlen($new_password) < 6) { 
            $errors[] = "يجب أن لا تقل كلمة المرور عن 6 أحرف.";
        } else {
            $password_update_required = true;
        }
    }
    
    // 3.2. التحقق الأساسي من صحة الاسم والبريد
    if (empty($full_name) || empty($email)) {
        $errors[] = "يجب ملء حقل الاسم والبريد الإلكتروني.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "صيغة البريد الإلكتروني غير صحيحة.";
    }

    if (empty($errors)) {
        try {
            
            // ----------------------------------------------------------
            // 3.3. تحديث كلمة المرور إذا كانت مطلوبة
            // ----------------------------------------------------------
            if ($password_update_required) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_pass = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt_pass->execute([$hashed_password, $user_id]);
            }

            // ----------------------------------------------------------
            // 3.4. تحديث البيانات الأساسية (الاسم، البريد، الدور، التفعيل)
            // ----------------------------------------------------------
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $role, $is_active, $user_id]);
            
            // تحديث الجلسة إذا كان المدير الحالي هو من يتم تعديله
            if ($user_id === (int)($_SESSION['user_id'] ?? 0)) {
                $_SESSION['user_role'] = $role;
                $_SESSION['is_active'] = $is_active;
            }
            
            set_message("تم تحديث بيانات المستخدم '{$full_name}' بنجاح!" . ($password_update_required ? " وتم تحديث كلمة المرور." : ""), "success");
            header("Location: manage_users.php");
            exit();
            
        } catch (PDOException $e) {
            // التحقق من تكرار البريد الإلكتروني (خطأ 23000 هو رمز UNIQUE/PRIMARY KEY)
            if ($e->getCode() === '23000') {
                 $errors[] = "البريد الإلكتروني '{$email}' مستخدم بالفعل.";
            } else {
                 $errors[] = "خطأ في قاعدة البيانات أثناء التحديث.";
            }
        }
    }
    
    // إعادة تحميل بيانات المستخدم المُعدلة لتظهر في النموذج في حال وجود أخطاء
    $target_user['full_name'] = $full_name;
    $target_user['email'] = $email;
    $target_user['role'] = $role;
    $target_user['is_active'] = $is_active;
}

include_once '../includes/header.php'; 
?>

<div class="container admin-content">
    
    <h2>✏️ تعديل المستخدم: <?= htmlspecialchars($target_user['full_name']) ?> (ID: <?= $target_user['id'] ?>)</h2>
    <?php display_message(); ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="color: red; margin-bottom: 20px;">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div style="max-width: 600px; margin-top: 20px; border: 1px solid #ddd; padding: 30px; border-radius: 8px;">
        <form method="POST" action="edit_user.php?id=<?= $target_user['id'] ?>">
            
            <label for="full_name" style="display: block; margin-bottom: 5px; font-weight: bold;">الاسم الكامل:</label>
            <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($target_user['full_name']) ?>" required 
                     style="padding: 10px; margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
            
            <label for="email" style="display: block; margin-bottom: 5px; font-weight: bold;">البريد الإلكتروني:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($target_user['email']) ?>" required 
                     style="padding: 10px; margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
            
            <h4 style="margin-top: 30px; margin-bottom: 10px; border-bottom: 1px dashed #ddd; padding-bottom: 5px;">تغيير كلمة المرور (اختياري)</h4>
            
            <label for="new_password" style="display: block; margin-bottom: 5px; font-weight: bold;">كلمة المرور الجديدة:</label>
            <input type="password" id="new_password" name="new_password" 
                     style="padding: 10px; margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
            
            <label for="confirm_password" style="display: block; margin-bottom: 5px; font-weight: bold;">تأكيد كلمة المرور الجديدة:</label>
            <input type="password" id="confirm_password" name="confirm_password" 
                     style="padding: 10px; margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
            <label for="role" style="display: block; margin-bottom: 5px; font-weight: bold;">دور المستخدم:</label>
            <select id="role" name="role" required 
                     style="padding: 10px; margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
                <?php foreach (['client', 'provider', 'admin'] as $role_option): ?>
                    <option value="<?= $role_option ?>" <?= $target_user['role'] === $role_option ? 'selected' : '' ?>>
                        <?= $role_option ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <label for="is_active" style="display: block; margin-bottom: 5px; font-weight: bold;">حالة الحساب:</label>
            <select id="is_active" name="is_active" required 
                     style="padding: 10px; margin-bottom: 25px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
                <option value="1" <?= (int)$target_user['is_active'] === 1 ? 'selected' : '' ?>>1 (مفعل)</option>
                <option value="0" <?= (int)$target_user['is_active'] === 0 ? 'selected' : '' ?>>0 (محظور)</option>
            </select>
            
            <button type="submit" style="padding: 12px 25px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
                حفظ التعديلات
            </button>
            <a href="manage_users.php" style="margin-right: 15px; color: #555; text-decoration: none;">إلغاء والعودة</a>
        </form>
    </div>
    
</div>

<?php 
include_once '../includes/footer.php';
?>