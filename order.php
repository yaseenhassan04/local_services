<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php'; 
require_once __DIR__ . '/includes/functions.php'; 

global $pdo; 

// 1. التحقق من تسجيل الدخول والدور
check_login('client'); 

$user = getCurrentUser();
$user_id = $user['id'];
$service_id = sanitize_input($_GET['service_id'] ?? null);
$csrf_token = generateCsrfToken();

// 2. التحقق من وجود رقم خدمة صالح
if (!$service_id || !is_numeric($service_id)) {
    set_message("يجب تحديد الخدمة المطلوبة أولاً.", "danger");
    header("Location: /local_services/services.php");
    exit;
}

// 3. جلب تفاصيل الخدمة (للتأكد من وجودها وعرض ملخص للعميل)
$stmt = $pdo->prepare("
    SELECT s.id, s.title, s.price, s.description, u.full_name AS provider_name, u.id AS provider_id
    FROM services s
    JOIN users u ON s.provider_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    set_message("الخدمة المطلوبة غير موجودة أو تم حذفها.", "danger");
    header("Location: /local_services/services.php");
    exit;
}

// 4. منع العميل من طلب خدماته الخاصة (إذا كان مزوداً لنفس الخدمة)
if ($service['provider_id'] === $user_id) {
    set_message("لا يمكنك طلب خدمة أنت مزودها.", "warning");
    header("Location: /local_services/client/service_detail.php?id=" . $service_id);
    exit;
}


require_once __DIR__ . '/includes/header.php'; 
?>

<section class="container" style="padding: 40px 0; max-width: 800px; text-align: right;">
    <?php display_message(); ?>

    <h2 style="border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px;">طلب الخدمة: <?php echo htmlspecialchars($service['title']); ?></h2>
    
    <div class="card p-3 mb-4" style="background: #e9f7ff; border: 1px solid #cce5ff;">
        <h5 style="color: #007bff; margin-bottom: 10px;">ملخص الخدمة</h5>
        <p><strong>المزود:</strong> <?php echo htmlspecialchars($service['provider_name']); ?></p>
        <p><strong>سعر الوحدة/الخدمة الثابت:</strong> <span id="unit-price"><?php echo number_format($service['price'], 2); ?></span> ر.س</p>
        <p><strong>الوصف:</strong> <?php echo nl2br(substr(htmlspecialchars($service['description']), 0, 100)); ?>...</p>
    </div>

    <form action="/local_services/actions/create_order.php" method="POST" style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.05);">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="service_id" value="<?php echo $service_id; ?>">
        <input type="hidden" name="provider_id" value="<?php echo $service['provider_id']; ?>">
        <input type="hidden" name="price" id="price-input" value="<?php echo $service['price']; ?>">
        
        <div class="form-group" style="margin-bottom: 20px;">
            <label for="quantity" style="display: block; font-weight: bold; margin-bottom: 5px;">الكمية المطلوبة (مثل: عدد الساعات/الوحدات):</label>
            <input type="number" name="quantity" id="quantity" class="form-control" value="1" min="1" required 
                   style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label for="client_notes" style="display: block; font-weight: bold; margin-bottom: 5px;">ملاحظات/متطلبات إضافية للخدمة (ضروري):</label>
            <textarea name="client_notes" id="client_notes" class="form-control" rows="5" required
                      style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 30px; padding: 15px; border: 2px solid #28a745; border-radius: 6px; background: #e2f0e6;">
            <label style="display: block; font-size: 1.1em; font-weight: bold;">المبلغ الإجمالي المتوقع:</label>
            <span id="total-amount" style="font-size: 1.8em; color: #28a745; font-weight: bold;">
                <?php echo number_format($service['price'], 2); ?>
            </span>
            <span style="font-size: 1.2em; color: #333;"> ر.س</span>
            <input type="hidden" name="final_amount" id="final-amount-input" value="<?php echo $service['price']; ?>">
        </div>

        <button type="submit" class="btn btn-primary" 
                style="width: 100%; padding: 15px; background: #007bff; color: white; border: none; border-radius: 4px; font-size: 1.1em; cursor: pointer;">
            تأكيد وطلب الخدمة
        </button>
    </form>
</section>

<script>
    // 🆕 جافاسكريبت لتحديث السعر الإجمالي بشكل تفاعلي
    document.addEventListener('DOMContentLoaded', function() {
        const quantityInput = document.getElementById('quantity');
        const unitPrice = parseFloat(document.getElementById('unit-price').textContent.replace(/[^0-9.]/g, ''));
        const totalAmountSpan = document.getElementById('total-amount');
        const finalAmountInput = document.getElementById('final-amount-input');

        function updateTotalPrice() {
            const quantity = parseInt(quantityInput.value) || 1;
            const totalPrice = quantity * unitPrice;
            
            // عرض السعر بفاصلة عشرية
            totalAmountSpan.textContent = totalPrice.toFixed(2);
            // حفظ القيمة لإرسالها مع النموذج
            finalAmountInput.value = totalPrice.toFixed(2);
        }

        quantityInput.addEventListener('input', updateTotalPrice);
        // تحديث السعر عند تحميل الصفحة لأول مرة (في حال كانت القيمة الافتراضية ليست 1)
        updateTotalPrice(); 
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>