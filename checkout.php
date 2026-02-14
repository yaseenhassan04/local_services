<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php'; 

requireLogin();
global $pdo;

// 1. التحقق من وجود معرف الخدمة (service_id)
$service_id = filter_input(INPUT_GET, 'service_id', FILTER_VALIDATE_INT);
if (!$service_id) {
    display_message("معرف الخدمة غير صالح.", "danger");
    header("Location: services.php");
    exit();
}

// 2. جلب بيانات الخدمة
try {
    $stmt = $pdo->prepare("SELECT s.title, s.price, u.full_name AS provider_name, u.id AS provider_id
                           FROM services s
                           JOIN users u ON s.provider_id = u.id
                           WHERE s.id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        display_message("الخدمة المطلوبة غير موجودة.", "danger");
        header("Location: services.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching service for checkout: " . $e->getMessage());
    display_message("حدث خطأ في جلب بيانات الخدمة.", "danger");
    header("Location: services.php");
    exit();
}

$csrf_token = generateCsrfToken();
$user = getCurrentUser();

require_once __DIR__ . '/includes/header.php';
?>

<section class="container" style="max-width: 700px; margin-top: 40px; margin-bottom: 60px;">
    <?php display_message(); ?>
    <h2 class="text-center">إتمام الطلب والدفع</h2>
    <p class="text-center muted">الخطوة الأخيرة لإتمام طلبك.</p>

    <div class="card shadow p-4" style="margin-top: 30px;">
        <h4 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">ملخص الطلب</h4>
        <div class="summary-details">
            <p><strong>الخدمة:</strong> <?php echo htmlspecialchars($service['title']); ?></p>
            <p><strong>المزود:</strong> <?php echo htmlspecialchars($service['provider_name']); ?></p>
            <p><strong>الإجمالي المطلوب:</strong> <span class="price-large"><?php echo number_format($service['price'], 2); ?> ر.س</span></p>
        </div>

        <h4 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top: 30px;">معلومات الدفع (محاكاة)</h4>
        
        <form action="place_order.php" method="POST" class="payment-form">
            <input type="hidden" name="service_id" value="<?php echo $service_id; ?>">
            <input type="hidden" name="provider_id" value="<?php echo $service['provider_id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-group">
                <label for="card_number">رقم البطاقة:</label>
                <input type="text" id="card_number" name="card_number" class="form-control" value="4111 1111 1111 1111" required>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="expiry_date">تاريخ الانتهاء (MM/YY):</label>
                    <input type="text" id="expiry_date" name="expiry_date" class="form-control" value="12/28" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="cvv">رمز الأمان (CVV):</label>
                    <input type="text" id="cvv" name="cvv" class="form-control" value="123" required>
                </div>
            </div>

            <div class="form-group">
                <label for="card_holder">اسم حامل البطاقة:</label>
                <input type="text" id="card_holder" name="card_holder" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 mt-3">إتمام الطلب والدفع (<?php echo number_format($service['price'], 2); ?> ر.س)</button>
        </form>
    </div>

</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>