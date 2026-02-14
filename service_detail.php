<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php'; 
require_once __DIR__ . '/includes/functions.php'; 

global $pdo;
$user = getCurrentUser(); 
$is_logged_in = ($user !== false && $user !== null);
$user_id = $is_logged_in ? $user['id'] : null;

// 1. التحقق من وجود معرف الخدمة
$service_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$service_id) {
    set_message("معرف الخدمة غير صالح.", "danger");
    header("Location: services.php");
    exit();
}

// 2. جلب بيانات الخدمة وتفاصيل المزود
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.id, s.title, s.description, s.price, 
            s.image AS image_url, 
            s.category_id, s.provider_id,
            u.full_name AS provider_name, u.phone AS provider_phone,
            c.name AS category_name
        FROM services s
        LEFT JOIN users u ON s.provider_id = u.id
        LEFT JOIN categories c ON s.category_id = c.id
        WHERE s.id = ? AND s.is_active = 1
    ");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        set_message("الخدمة المطلوبة غير موجودة أو تم إيقافها.", "danger");
        header("Location: services.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching service detail: " . $e->getMessage());
    set_message("حدث خطأ في جلب بيانات الخدمة: " . $e->getMessage(), "danger"); 
    header("Location: services.php");
    exit();
}

// 3. التحقق من إذن الطلب
$is_owner = $is_logged_in && ($user_id == $service['provider_id']);
$can_order = $is_logged_in && $user['role'] === 'client' && !$is_owner;

// 4. جلب التقييمات ومتوسطها
try {
    $stmt_reviews = $pdo->prepare("
        SELECT 
            r.rating, 
            r.comment AS review_text, 
            r.created_at, 
            u.full_name AS client_name
        FROM reviews r
        LEFT JOIN orders o ON r.order_id = o.id 
        LEFT JOIN users u ON o.client_id = u.id 
        WHERE o.service_id = ? 
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt_reviews->execute([$service_id]);
    $reviews = $stmt_reviews->fetchAll(PDO::FETCH_ASSOC);

    $total_rating = array_sum(array_column($reviews, 'rating'));
    $review_count = count($reviews);
    $average_rating = $review_count > 0 ? round($total_rating / $review_count, 1) : 0;
} catch (PDOException $e) {
    error_log("Error fetching reviews: " . $e->getMessage());
    $reviews = [];
    $review_count = 0;
    $average_rating = 0;
}


$csrf_token = generateCsrfToken(); 

require_once __DIR__ . '/includes/header.php';
?>


<div class="container service-container mx-auto">
    <?php display_message(); ?>
    
    <div class="row d-flex flex-wrap">
        
        <div class="col-md-8" style="flex: 2; padding-right: 25px;">
            <div class="styled-card">
                
                <h1 class="service-title"><?php echo htmlspecialchars($service['title']); ?></h1>
                
                <div class="meta-info d-flex align-items-center mb-4" style="padding-bottom: 10px; border-bottom: 1px solid #eee;">
                    <span class="meta-badge"><?php echo htmlspecialchars($service['category_name']); ?></span>
                    
                    <span class="rating-display">
                        <span class="ms-2">★</span> 
                        <?php echo $average_rating; ?> (<?php echo $review_count; ?> تقييم)
                    </span>
                </div>

                <?php if (!empty($service['image_url'])): ?>
                    <img src="/local_services/assets/uploads/<?php echo htmlspecialchars($service['image_url']); ?>" alt="<?php echo htmlspecialchars($service['title']); ?>" class="service-image">
                <?php endif; ?>

                <h3 class="description-header">وصف الخدمة</h3>
                <p class="description-text" style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>
                
            </div>
            
            <div class="styled-card reviews-card">
                <h3 class="description-header" style="border-bottom: 1px solid #e0e0e0; color: #1a237e;">آراء العملاء (<?php echo $review_count; ?>)</h3>
                
                <?php if ($review_count === 0): ?>
                    <p class="text-muted" style="color:#6c757d;">لا توجد تقييمات لهذه الخدمة بعد. كن أول من يجربها!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-item">
                            <p style="margin: 0;">
                                <span class="review-name"><?php echo htmlspecialchars($review['client_name'] ?? 'مستخدم محذوف'); ?></span> 
                                <span class="review-rating">
                                    (<?php echo htmlspecialchars($review['rating']); ?> ★)
                                </span>
                                <span class="review-date">
                                    <?php echo date('Y-m-d', strtotime($review['created_at'])); ?>
                                </span>
                            </p>
                            <p class="review-text"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-md-4" style="flex: 1; min-width: 300px;">
            <div class="side-panel sticky-top">
                <h4 class="text-center" style="color: #4caf50; border-bottom: 1px dashed #cfd8dc; padding-bottom: 10px; margin-bottom: 15px;">احصل على الخدمة</h4>
                
                <div class="price-box">
                    <span class="main-price"><?php echo number_format($service['price'], 2); ?></span> <span class="currency">ر.س</span>
                </div>
                
                <?php if ($can_order): ?>
                    <button id="openPaymentModal" class="order-btn">
                        طلب الخدمة والدفع الآن
                    </button>
                <?php elseif ($is_owner): ?>
                    <div class="alert alert-info text-center" style="padding: 10px; background: #ffebee; border: 1px solid #f44336; color: #f44336; border-radius: 6px;">
                        ❌ لا يمكنك طلب خدمتك الخاصة.
                    </div>
                <?php else: ?>
                    <a href="/local_services/login.php" class="order-btn" style="display: block; text-align: center; text-decoration: none;">
                        تسجيل الدخول للطلب
                    </a>
                <?php endif; ?>

                <p class="text-center mt-3" style="font-size: 0.85em; color: #78909c;">* سيتم التواصل معك من قبل المزود بعد إتمام عملية الدفع.</p>

                <div class="provider-info">
                    <h6 style="color: #1a237e; border-top: 1px dashed #cfd8dc; padding-top: 15px; margin-top: 25px;">معلومات المزود</h6>
                    <p>👤 **الاسم:** **<?php echo htmlspecialchars($service['provider_name'] ?? 'غير معروف'); ?>**</p>
                    <p>📞 **الهاتف:** **<?php echo htmlspecialchars($service['provider_phone'] ?? 'غير متوفر'); ?>**</p>
                </div>

            </div>
        </div>
    </div>
</div>

<div id="paymentModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" id="closeModal">&times;</span>
        <h3>اختر طريقة الدفع لطلب (<?php echo htmlspecialchars($service['title']); ?>)</h3>

        <div class="payment-icons">
            <button data-method="jawwal"> <img src="/local_services/assets/images/jawwalpay-icon.png" alt="Jawwal Pay" class="payment-icon-img"> جوال باي </button>
            <button data-method="paypal"> <img src="/local_services/assets/images/paypal-logo.png" alt="PayPal" class="payment-icon-img"> باي بال </button>
            <button data-method="bank"> <img src="/local_services/assets/images/payment-bank.png" alt="Bank Transfer" class="payment-icon-img"> تطبيق بنكي </button>
        </div>
        
        <form id="paymentForm" class="payment-form" action="/local_services/actions/place_order.php" method="POST">
            <input type="hidden" name="service_id" value="<?php echo $service_id; ?>">
            <input type="hidden" name="provider_id" value="<?php echo $service['provider_id']; ?>"> 
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="amount" value="<?php echo htmlspecialchars($service['price']); ?>">
            <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="">

            <label>رقم الحساب/الهاتف للدفع :</label>
            <input type="text" name="account_number" placeholder="أدخل رقم الحساب أو الهاتف" required>
            
            <label>تفاصيل ومتطلبات الطلب (الموقع، التوقيت...):</label>
            <textarea name="details" rows="3" placeholder="أدخل موقعك ومتطلباتك الخاصة..." required></textarea>
            
            <button class="checkout-btn" id="confirmPaymentBtn" type="submit" disabled>ادفع الآن (<?php echo number_format($service['price'], 2); ?> شيكل)</button>
        </form>

    </div>
</div>

<div id="successModal" class="modal">
    <div class="modal-content">
        <div class="success-icon">
            <span class="ms-2">✅</span>
        </div>
        <div class="success-message">
            تم طلب الخدمة بنجاح!
        </div>
        <p style="color: #6c757d; margin-top: 10px;">سيتم تحويلك قريباً إلى صفحة الطلبات لتتبع حالته.</p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById("paymentModal");
        var successModal = document.getElementById("successModal");
        var btn = document.getElementById("openPaymentModal");
        var closeModalBtn = document.getElementById("closeModal");
        var paymentButtons = document.querySelectorAll('.payment-icons button');
        var confirmBtn = document.getElementById("confirmPaymentBtn");
        var paymentForm = document.getElementById("paymentForm");
        var selectedMethodInput = document.getElementById("selectedPaymentMethod");
        // نحول السعر إلى رقم يمكن استخدامه في JS للعرض
        var servicePrice = <?php echo json_encode(number_format($service['price'], 2)); ?>;

        // 1. فتح وإغلاق نافذة الدفع والنجاح
        if (btn) {
            btn.onclick = function() { modal.style.display = "block"; }
        }
        if (closeModalBtn) {
            closeModalBtn.onclick = function() { modal.style.display = "none"; }
        }
        window.onclick = function(event) {
            if (event.target == modal) { modal.style.display = "none"; }
            if (event.target == successModal) { successModal.style.display = "none"; }
        }

        // 2. وظيفة اختيار طريقة الدفع
        paymentButtons.forEach(button => {
            button.addEventListener('click', function() {
                paymentButtons.forEach(b => b.classList.remove('selected'));
                this.classList.add('selected');
                
                const method = this.getAttribute('data-method');
                selectedMethodInput.value = method;

                paymentForm.style.display = 'block';
                confirmBtn.disabled = false;
                
                let buttonText = 'ادفع الآن';
                if (method === 'jawwal') {
                    buttonText = 'ادفع عبر جوال باي';
                } else if (method === 'paypal') {
                    buttonText = 'ادفع عبر باي بال';
                } else if (method === 'bank') {
                    buttonText = 'أكد الطلب (إثبات الدفع لاحقاً)'; 
                }
                
                confirmBtn.textContent = buttonText + ' (' + servicePrice + ' ر.س)';
            });
        });

        // 3. معالجة إرسال النموذج بالـ AJAX
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault(); 

            confirmBtn.disabled = true;
            confirmBtn.textContent = 'جاري المعالجة...';

            const formData = new FormData(paymentForm);

            fetch(paymentForm.action, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // يجب أن تكون الاستجابة بصيغة JSON
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // 1. إخفاء نافذة الدفع
                    modal.style.display = 'none';
                    
                    // 2. إظهار نافذة النجاح
                    successModal.style.display = 'block';

                    // 3. إغلاق نافذة النجاح وتحديث الصفحة تلقائياً بعد 3 ثوانٍ
                    setTimeout(() => {
                        successModal.style.display = 'none';
                        window.location.reload(); 
                        // يمكن استخدام: window.location.href = data.redirect_url; إذا كنت تريد تحويل محدد
                    }, 3000); 

                } else {
                    // في حالة وجود خطأ
                    alert('خطأ: ' + (data.message || 'حدث خطأ غير معروف أثناء معالجة الطلب.'));
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'ادفع الآن (' + servicePrice + ' ر.س)';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('خطأ في الاتصال بالخادم. يرجى المحاولة مرة أخرى.');
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'ادفع الآن (' + servicePrice + ' ر.س)';
            });
        });
    });
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>