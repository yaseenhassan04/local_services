<?php
// register.php - نسخة معدلة بضوابط حماية متقدمة

require_once __DIR__ . '/includes/db.php';
include_once __DIR__ . '/includes/header.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $phone     = trim($_POST['phone'] ?? '');
    $city      = trim($_POST['city'] ?? '');
    $role      = in_array($_POST['role'] ?? 'client', ['client','provider','admin']) ? $_POST['role'] : 'client';

    // 1. التحقق من الحقول الفارغة
    if ($full_name === '' || $email === '' || $password === '' || $phone === '') {
        $errors[] = "الرجاء ملء جميع الحقول الأساسية.";
    }

    // 2. التحقق من تطابق كلمتي المرور
    if ($password !== $password2) {
        $errors[] = "كلمتا المرور غير متطابقتين.";
    }

    // 3. ضابط قوة كلمة المرور (طلب الدكتور: 8 خانات، أرقام، رموز، أحرف كبيرة وصغيرة)
    // النمط: حرف كبير، حرف صغير، رقم، رمز خاص، وطول لا يقل عن 8
    $pwd_pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
    if (!preg_match($pwd_pattern, $password)) {
        $errors[] = "كلمة المرور ضعيفة! يجب أن تحتوي على 8 خانات على الأقل، تشمل (أحرف كبيرة، أحرف صغيرة، أرقام، ورموز خاصة).";
    }

    // 4. ضابط رقم الهاتف (طلب الدكتور: 10 أرقام فقط)
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = "رقم الهاتف غير صحيح، يجب أن يتكون من 10 أرقام فقط.";
    }

    // 5. التحقق من صحة صيغة البريد الإلكتروني (منع الحسابات الوهمية)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "صيغة البريد الإلكتروني غير صالحة.";
    }

    // 6. التحقق من وجود البريد مسبقاً في قاعدة البيانات
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "هذا البريد الإلكتروني مسجل بالفعل.";
        }
    }

    // 7. تنفيذ عملية التسجيل في حال عدم وجود أخطاء
    if (empty($errors)) {
        // تشفير كلمة المرور
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // استعلام الإدخال مع تسمية المتغيرات بوضوح
        $sql = "INSERT INTO users (full_name, email, password_hash, phone, city, role) 
                VALUES (:fn, :em, :pass, :ph, :ct, :rl)";
        
        $stmt = $pdo->prepare($sql);
        
        // ربط المتغيرات (BindParams)
        $params = [
            ':fn'   => $full_name,
            ':em'   => $email,
            ':pass' => $hash,
            ':ph'   => $phone,
            ':ct'   => $city,
            ':rl'   => $role
        ];

        if ($stmt->execute($params)) {
            $user_id = $pdo->lastInsertId();

            // إذا كان المسجل "مزود خدمة"، ننشئ له ملفاً في جدول المزودين
            if ($role === 'provider') {
                $pstmt = $pdo->prepare("INSERT INTO providers (user_id, bio) VALUES (:uid, :bio)");
                $pstmt->execute([':uid' => $user_id, ':bio' => '']);
            }

            // بدء الجلسة وتوجيه المستخدم
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            $_SESSION['user_id'] = $user_id;
            header("Location: /local_services/dashboard.php");
            exit;
        } else {
            $errors[] = "حدث خطأ فني أثناء التسجيل، يرجى المحاولة لاحقاً.";
        }
    }
}
?>

<section class="auth-page-container">
    <div class="auth-card">
        <h2>تسجيل حساب جديد</h2>
        
        <?php if ($errors): ?>
            <div class="errors" style="color: red; background: #ffeeee; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php foreach ($errors as $e) echo "<p>• " . htmlspecialchars($e) . "</p>"; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <label>الاسم الكامل
                <input type="text" name="full_name" required value="<?php echo htmlspecialchars($full_name ?? ''); ?>">
            </label>
            
            <label>البريد الإلكتروني
                <input type="email" name="email" placeholder="example@mail.com" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </label>
            
            <label>رقم الهاتف (10 أرقام)
                <input type="text" name="phone" placeholder="05XXXXXXXX" required value="<?php echo htmlspecialchars($phone ?? ''); ?>">
            </label>

            <div style="display: flex; gap: 10px;">
                <label style="flex: 1;">كلمة المرور
                    <input type="password" name="password" required>
                </label>
                <label style="flex: 1;">تأكيد كلمة المرور
                    <input type="password" name="password2" required>
                </label>
            </div>
            <small style="display: block; margin-bottom: 10px; color: #666;">* يجب أن تحتوي على أحرف كبيرة وصغيرة وأرقام ورموز.</small>
            
            <label>المدينة
                <input type="text" name="city" value="<?php echo htmlspecialchars($city ?? ''); ?>">
            </label>
            
            <label class="role-select">نوع الحساب
                <select name="role">
                    <option value="client" <?php echo ($role === 'client') ? 'selected' : ''; ?>>طالب خدمة (عميل)</option>
                    <option value="provider" <?php echo ($role === 'provider') ? 'selected' : ''; ?>>مقدّم خدمة (مستقل)</option>
                </select>
            </label>
            
            <button type="submit" class="btn">إنشاء الحساب</button>
        </form>
        <p class="muted">لديك حساب بالفعل؟ <a href="/local_services/login.php">تسجيل دخول</a></p>
    </div>
</section>

<?php include_once __DIR__ . '/includes/footer.php'; ?>