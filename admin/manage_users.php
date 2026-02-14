<?php
// admin/manage_users.php - إدارة المستخدمين (الكود المصحح)

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php'; // 🛑 مهم: تضمين ملف الصلاحيات

// 🛑 1. حماية الصفحة: يجب أن يكون المدير فقط
check_login('admin'); 

global $pdo;
$users = [];
$errors = [];

try {
    // 1. جلب جميع المستخدمين
    $stmt = $pdo->query("SELECT id, full_name, email, role, is_active, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errors[] = "فشل في جلب بيانات المستخدمين: " . $e->getMessage();
}

// 2. معالجة طلب تغيير الدور أو الحالة (Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // (يفضل إضافة التحقق من CSRF Token هنا)

    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        // التأكد من أن حقل user_id رقم صحيح
        $user_id = (int) sanitize_input($_POST['user_id']); 
        $action = sanitize_input($_POST['action']);
        $update_value = NULL;
        $update_field = NULL;
        $success_message = "";

        // 2.1 معالجة تغيير الدور
        if ($action === 'change_role' && isset($_POST['new_role'])) {
            $new_role = sanitize_input($_POST['new_role']);
            // التحقق من أن الدور المدخل صالح
            if (in_array($new_role, ['client', 'provider', 'admin'])) {
                $update_field = 'role';
                $update_value = $new_role;
                $success_message = "تم تغيير دور المستخدم بنجاح إلى '{$new_role}'.";
            }
        
        // 2.2 معالجة تفعيل/حظر المستخدم
        } elseif ($action === 'toggle_active' && isset($_POST['current_status'])) {
            $update_field = 'is_active';
            // تبديل القيمة: إذا كانت 1 تصبح 0، وإذا كانت 0 تصبح 1
            $update_value = (int)$_POST['current_status'] === 1 ? 0 : 1; 
            $status_name = $update_value === 1 ? 'تفعيل' : 'حظر';
            $success_message = "تم تغيير حالة المستخدم بنجاح إلى '{$status_name}'.";
        }

        if ($update_field) {
            try {
                // استخدام الاستعلامات المُعدّة لتحديث آمن
                $stmt_update = $pdo->prepare("UPDATE users SET $update_field = ? WHERE id = ?");
                $stmt_update->execute([$update_value, $user_id]);
                
                // 🛑 التصحيح الحاسم: تحديث الجلسة إذا كان المدير هو من تم تعديل دوره
                if ($update_field === 'role' && $user_id === (int)($_SESSION['user_id'] ?? 0)) {
                    $_SESSION['user_role'] = $update_value;
                }
                
                set_message($success_message, "success");
                // إعادة التوجيه لمنع إعادة إرسال النموذج (Post/Redirect/Get)
                header("Location: manage_users.php");
                exit();
                
            } catch (PDOException $e) {
                set_message("خطأ في قاعدة البيانات أثناء تحديث المستخدم: " . $e->getMessage(), "danger");
            }
        }
    }
    // إذا فشل التحديث لأي سبب، نعيد التوجيه لمنع إعادة الإرسال العشوائي
    header("Location: manage_users.php");
    exit();
}

include_once '../includes/header.php'; // نفترض وجود هيدر خاص بالمدير
?>

<div class="container admin-content">
    <h2>👥 إدارة المستخدمين (<?= count($users) ?>)</h2>
    <?php display_message(); ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errors[0]) ?></div>
    <?php endif; ?>

    <table border="1" style="width: 100%; border-collapse: collapse; margin-top: 20px; text-align: center;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th style="padding: 10px;">ID</th>
                <th style="padding: 10px;">الاسم</th>
                <th style="padding: 10px;">البريد الإلكتروني</th>
                <th style="padding: 10px;">الدور</th>
                <th style="padding: 10px;">الحالة</th>
                <th style="padding: 10px;">تاريخ الانضمام</th>
                <th style="padding: 10px;">الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td style="padding: 10px;"><?= $user['id'] ?></td>
                    <td style="padding: 10px;"><?= htmlspecialchars($user['full_name']) ?></td>
                    <td style="padding: 10px;"><?= htmlspecialchars($user['email']) ?></td>
                    
                    <td style="padding: 10px;">
                        <form method="POST" action="manage_users.php" style="display: inline-block;">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <input type="hidden" name="action" value="change_role">
                            <select name="new_role" onchange="this.form.submit()">
                                <?php foreach (['client', 'provider', 'admin'] as $role): ?>
                                    <option value="<?= $role ?>" <?= $user['role'] === $role ? 'selected' : '' ?>>
                                        <?= $role ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    
                    <td style="padding: 10px;">
                        <span style="color: <?= $user['is_active'] ? 'green' : 'red' ?>; font-weight: bold;">
                            <?= $user['is_active'] ? 'مفعل' : 'محظور' ?>
                        </span>
                    </td>
                    <td style="padding: 10px;"><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                    
                    <td style="padding: 10px;">
                        <form method="POST" action="manage_users.php" style="display: inline-block; margin-left: 5px;">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="current_status" value="<?= $user['is_active'] ?>">
                            <button type="submit" onclick="return confirm('هل أنت متأكد؟')"
                                    style="background-color: <?= $user['is_active'] ? 'orange' : 'green' ?>; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 4px;">
                                <?= $user['is_active'] ? 'حظر' : 'تفعيل' ?>
                            </button>
                        </form>
                        
                        <a href="edit_user.php?id=<?= $user['id'] ?>" style="margin-right: 10px; color: #007bff; text-decoration: none;">تعديل البيانات</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
</div>

<?php 
include_once '../includes/footer.php';
?>