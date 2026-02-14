<?php
// /local_services/services.php - صفحة تصفح الخدمات المتاحة

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php'; 
require_once __DIR__ . '/includes/functions.php'; 

// لا يلزم تسجيل الدخول بالضرورة لتصفح الخدمات، لكن سنضمن تضمن الملفات الأساسية
global $pdo; 

// ==========================================================
// 1. جلب جميع الخدمات النشطة
// ==========================================================
$stmt = $pdo->prepare("
    SELECT 
        s.id, s.title, s.description, s.price, s.city, s.image,
        u.full_name AS provider_name, c.name AS category_name
    FROM services s
    JOIN users u ON s.provider_id = u.id
    JOIN categories c ON s.category_id = c.id
    WHERE s.is_active = 1
    ORDER BY s.created_at DESC
");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php'; 
?>


<section class="container" style="padding: 40px 0;">
    <?php display_message(); ?>

    <h2 style="border-bottom: 2px solid #007bff; padding-bottom: 10px;">
        تصفح جميع الخدمات المتاحة (<?php echo count($services); ?>)
    </h2>
    <p class="muted">اكتشف الخدمات المحلية في منطقتك. يمكنك طلب الخدمة مباشرة بالنقر على تفاصيلها.</p>

    <?php if (empty($services)): ?>
        <div class="alert alert-info" style="margin-top: 30px; padding: 15px; background: #e9ecef; border-radius: 5px; text-align: right;">
            <p class="muted">عذراً، لا توجد خدمات متاحة حالياً.</p>
        </div>
    <?php else: ?>
        <div class="services-grid">
            <?php foreach ($services as $s): 
                $service_image_path = $s['image'] 
                    ? '/local_services/assets/uploads/' . htmlspecialchars($s['image'])
                    : '/local_services/assets/images/default-service.jpg';
            ?>
                <a href="/local_services/service_detail.php?id=<?php echo $s['id']; ?>" class="service-card">
                    <div class="card-image" style="background-image: url('<?php echo $service_image_path; ?>')"></div>
                    <div class="card-body">
                        <span style="font-size: 0.9em; color: #6c757d; display: block; margin-bottom: 5px;">
                            #<?php echo htmlspecialchars($s['category_name']); ?>
                        </span>
                        <h3><?php echo htmlspecialchars($s['title']); ?></h3>
                        <p class="muted" style="margin: 5px 0;">
                            المزود: <?php echo htmlspecialchars($s['provider_name']); ?>
                        </p>
                        <p class="muted" style="margin: 5px 0;">
                            المدينة: <strong><?php echo htmlspecialchars($s['city']); ?></strong>
                        </p>
                        <p class="price">
                            <?php echo number_format($s['price'], 2); ?> ر.س
                        </p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>