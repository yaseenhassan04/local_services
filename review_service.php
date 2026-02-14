<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php'; 
require_once __DIR__ . '/includes/functions.php'; 

global $pdo; 

// 1. التحقق من تسجيل الدخول والدور
check_login('client'); 

$user = getCurrentUser();
$user_id = $user['id'];
$order_id = sanitize_input($_GET['order_id'] ?? null);
$csrf_token = generateCsrfToken();

// 2. التحقق من رقم الطلب
if (!$order_id || !is_numeric($order_id)) {
    set_message("رقم الطلب غير صالح لعملية التقييم.", "danger");
    header("Location: /local_services/client_dashboard.php");
    exit;
}

// 3. جلب تفاصيل الطلب والتحقق من الصلاحيات
$stmt = $pdo->prepare("
    SELECT 
        o.id, o.status, o.service_id, o.provider_id, 
        s.title AS service_title, u.full_name AS provider_name
    FROM orders o
    JOIN services s ON o.service_id = s.id
    JOIN users u ON o.provider_id = u.id
    WHERE o.id = ? AND o.client_id = ? 
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    set_message("الطلب غير موجود أو لا تملك صلاحية الوصول إليه.", "danger");
    header("Location: /local_services/client_dashboard.php");
    exit;
}

// 4. التحقق من أن الطلب مكتمل ومسموح بالتقييم
if ($order['status'] !== 'completed') {
    set_message("يمكن تقييم الطلبات المكتملة فقط.", "warning");
    header("Location: /local_services/view_order.php?id=" . $order_id);
    exit;
}

// 5. التحقق من عدم وجود تقييم مسبق لهذا الطلب
$stmt_check_review = $pdo->prepare("SELECT id FROM reviews WHERE order_id = ?");
$stmt_check_review->execute([$order_id]);

if ($stmt_check_review->fetch()) {
    set_message("لقد قمت بتقييم هذا الطلب مسبقاً.", "info");
    header("Location: /local_services/view_order.php?id=" . $order_id);
    exit;
}

require_once __DIR__ . '/includes/header.php'; 
?>



<section class="container" style="padding: 40px 0; max-width: 700px; text-align: right;">
    <?php display_message(); ?>

    <h2 style="border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px;">تقييم الخدمة</h2>
    
    <div class="card p-4 mb-4" style="background: #f8f9fa;">
        <h5 style="color: #333;">تقييم طلبك لخدمة: <strong><?php echo htmlspecialchars($order['service_title']); ?></strong></h5>
        <p class="muted">من مزود الخدمة: <?php echo htmlspecialchars($order['provider_name']); ?></p>
        <p class="muted">الرجاء تقييم الخدمة لتمكين الآخرين من اتخاذ القرار.</p>
    </div>

    <form action="/local_services/actions/submit_review.php" method="POST" style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.05);">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
        <input type="hidden" name="service_id" value="<?php echo $order['service_id']; ?>">
        <input type="hidden" name="provider_id" value="<?php echo $order['provider_id']; ?>">

        <div class="form-group" style="margin-bottom: 30px; text-align: center;">
            <label for="rating" style="display: block; font-weight: bold; margin-bottom: 15px; font-size: 1.2em;">تقييمك للخدمة (عدد النجوم):</label>
            <div class="rating-stars">
                <input type="radio" id="star5" name="rating" value="5" required><label for="star5" title="ممتاز">★</label>
                <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="جيد جداً">★</label>
                <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="جيد">★</label>
                <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="ضعيف">★</label>
                <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="سيئ جداً">★</label>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 30px;">
            <label for="comment" style="display: block; font-weight: bold; margin-bottom: 5px;">ملاحظاتك وتعليقك على الخدمة:</label>
            <textarea name="comment" id="comment" class="form-control" rows="5" placeholder="اكتب تعليقك هنا..." required
                      style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
        </div>

        <button type="submit" class="btn btn-success" 
                style="width: 100%; padding: 15px; background: #28a745; color: white; border: none; border-radius: 4px; font-size: 1.1em; cursor: pointer;">
            إرسال التقييم
        </button>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>