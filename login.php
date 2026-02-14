<?php
// login.php - صفحة تسجيل الدخول

// 1. تضمين ملف الاتصال بقاعدة البيانات (يُعرّف $pdo)
require_once __DIR__ . '/includes/db.php';

// 2. تضمين الهيدر والوظائف الأساسية (يُضمن header.php بدوره auth.php)
include_once __DIR__ . '/includes/header.php'; 

// إذا كان المستخدم مسجلاً بالفعل، يتم توجيهه إلى لوحة التحكم
if (getCurrentUser()) {
    header("Location: /local_services/dashboard.php");
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $errors[] = "الرجاء ملء الحقول.";
    } else {
        // الاستعلام عن المستخدم، باستخدام عمود 'password_hash'
        $sql = "SELECT id, full_name, password_hash, is_active FROM users WHERE email = ?";
        
        // 🛑 تصحيح: استخدام $pdo بدلاً من $conn 
        $stmt = $pdo->prepare($sql);
        
        if (!$stmt) {
            // ملاحظة:PDO لا تستخدم $conn->error بل يجب استخدام $pdo->errorInfo()
            $errors[] = "خطأ فادح في تجهيز الاستعلام.";
            error_log("SQL Prepare Error in login.php: " . print_r($pdo->errorInfo(), true));
        } else {
            // 🛑 PDO تستخدم bindValue أو bindParam بالطريقة الخاصة بها، لكن execute([$email]) هي الأسهل
            // بما أن الكود الأصلي كان يستخدم bind_param (وهي دالة MySQLi)، سنحولها إلى الطريقة الأبسط لـ PDO.
            $stmt->execute([$email]);
            $res = $stmt->fetch();
            
            // تحقق من وجود المستخدم وصحة كلمة المرور
            // يجب أن يكون 'password_hash' هو اسم العمود الصحيح في DB
            if ($res && password_verify($password, $res['password_hash'])) {
                if (!$res['is_active']) {
                    $errors[] = "الحساب غير مفعل. يرجى تفعيل حسابك أولاً.";
                } else {
                    $_SESSION['user_id'] = $res['id'];
                    header("Location: /local_services/dashboard.php");
                    exit;
                }
            } else {
                $errors[] = "بيانات الدخول غير صحيحة. تحقق من البريد وكلمة المرور.";
            }
        }
    }
}
?>

<section class="auth-page-container">
    <div class="auth-card">
        <h2>تسجيل الدخول</h2>
        <?php if ($errors): ?>
            <div class="errors">
                <?php foreach ($errors as $e) echo "<p>" . htmlspecialchars($e) . "</p>"; ?>
            </div>
        <?php endif; ?>
        <form method="post" action="">
            <label>البريد الإلكتروني<input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></label>
            <label>كلمة المرور<input type="password" name="password" required></label>
            <button type="submit" class="btn">دخول</button>
        </form>
        <p class="muted">ليس لديك حساب؟ <a href="/local_services/register.php">سجّل الآن</a></p>
    </div>
</section>
<?php include_once __DIR__ . '/includes/footer.php'; ?>