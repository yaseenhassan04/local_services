<?php
// /local_services/client/service_detail.php
// عرض تفاصيل الخدمة والبدء في طلبها

// يجب تعديل مسار الوصول إلى includes حسب هيكلية المجلدات الفعلية لديك
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// يمكن لأي مستخدم مسجل الدخول رؤية تفاصيل الخدمة
requireLogin(); 
global $pdo;

$user = getCurrentUser();
$user_id = $user['id'];
$service_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// 1. التحقق من وجود ID الخدمة
if (!$service_id) {
    display_message("خطأ: لم يتم تحديد الخدمة المطلوبة.", "danger");
    header("Location: /local_services/dashboard.php"); // أو صفحة الخدمات العامة
    exit;
}

// 2. جلب تفاصيل الخدمة ومزودها
$sql = "SELECT 
            s.id, s.title, s.description, s.price, s.category_id, s.provider_id, s.created_at,
            u.full_name AS provider_name, u.email AS provider_email, u.phone AS provider_phone, u.city AS provider_city,
            c.name AS category_name
        FROM services s
        JOIN users u ON s.provider_id = u.id
        LEFT JOIN categories c ON s.category_id = c.id
        WHERE s.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    display_message("الخدمة المطلوبة غير موجودة.", "danger");
    header("Location: /local_services/dashboard.php");
    exit;
}

// تحديد ما إذا كان المستخدم هو مزود هذه الخدمة
$is_provider_of_this_service = ($service['provider_id'] === $user_id);

require_once __DIR__ . '/../includes/header.php';
?>



<div class="service-detail-container">
    <?php display_message(); ?>

    <div class="service-header">
        <h1><?php echo htmlspecialchars($service['title']); ?></h1>
        <p class="text-muted">التصنيف: 
            <span style="font-weight: bold; color: #6c757d;"><?php echo htmlspecialchars($service['category_name'] ?? 'عام'); ?></span>
        </p>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div class="price-box">
            السعر: <?php echo number_format($service['price'], 2); ?> ر.س
        </div>

        <?php if ($is_provider_of_this_service): ?>
            <a href="/local_services/edit_service.php?id=<?php echo $service['id']; ?>" class="btn-action btn-edit" style="width: 45%;">
                ✏️ تعديل هذه الخدمة
            </a>
        <?php else: ?>
            <a href="/local_services/order.php?service_id=<?php echo $service['id']; ?>" class="btn-action btn-order" style="width: 45%;">
                🛒 اطلب الخدمة الآن
            </a>
        <?php endif; ?>
    </div>
    
    <div class="info-card">
        <h4>وصف الخدمة</h4>
        <div style="line-height: 1.8; color: #555;">
            <?php echo nl2br(htmlspecialchars($service['description'])); ?>
        </div>
    </div>
    
    <div class="info-card">
        <h4>معلومات مزود الخدمة</h4>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <p><strong>الاسم:</strong> <?php echo htmlspecialchars($service['provider_name']); ?></p>
            <p><strong>البريد:</strong> <?php echo htmlspecialchars($service['provider_email']); ?></p>
            <p><strong>المدينة:</strong> <?php echo htmlspecialchars($service['provider_city']); ?></p>
            <p><strong>الهاتف:</strong> <?php echo htmlspecialchars($service['provider_phone']); ?></p>
        </div>
        <p class="text-muted" style="margin-top: 15px; font-size: 0.9em;">تاريخ الإضافة: <?php echo date('Y-m-d', strtotime($service['created_at'])); ?></p>
    </div>

    <div style="margin-top: 30px; text-align: center;">
        <a href="/local_services/client_dashboard.php" class="btn btn-secondary" style="background: #6c757d; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">العودة إلى الخدمات</a>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>