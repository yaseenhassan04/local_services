<?php

// 1. تضمين الملفات الأساسية
include_once __DIR__ . '/includes/header.php'; 
require_once __DIR__ . '/includes/db.php'; 
require_once __DIR__ . '/includes/functions.php'; 

global $pdo;
$latest_services = [];
$categories = []; 

$icons_mapping = [
    'كهرباء' => 'electricity.svg',
    'سباكة' => 'plumbing.svg',
    'تنظيف وتعقيم' => 'cleaning.svg',
    'نجارة' => 'carpentry.svg',
    'تكييف وتبريد' => 'ac.svg',
    'تصميم جرافيك' => 'graphic_design.svg',
    'برمجة وتطوير مواقع' => 'programming.svg',
    'مونتاج فيديو' => 'video_editing.svg',
    'كتابة وترجمة' => 'writing.svg',
    'تسويق إلكتروني' => 'marketing.svg',
    'تصميم واجهات' => 'default-icon.svg', 
    'unuuu' => 'default-icon.svg' 
];

// 2. جلب البيانات من قاعدة البيانات (باستخدام PDO)
try {
    $sql = "SELECT s.id, s.title, s.description, s.price, s.city, s.image, u.full_name
            FROM services s
            JOIN users u ON s.provider_id = u.id
            WHERE s.is_active = 1
            ORDER BY s.created_at DESC
            LIMIT 6";
    $stmt = $pdo->query($sql);
    $latest_services = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error fetching latest services: " . $e->getMessage());
    $latest_services = [];
}

// جلب التصنيفات الشائعة
try {
    $categories_sql = "SELECT id, name, image FROM categories LIMIT 6";
    $categories_stmt = $pdo->query($categories_sql);
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error fetching categories: " . $e->getMessage());
    $categories = [];
}
?>

<section class="hero">
    <div class="hero-inner container">

        <h2>
            بوابتك لاحترافية الأعمال الحرة والخدمات المحلية </h2>
        <p>ربط سريع وموثوق بين أفضل المستقلين والأعمال المحلية وعملائهم.</p>
        
        <form class="search-form" action="/local_services/services.php" method="get">
            <input type="text" name="q" placeholder="مثال: سباكة، تصميم شعار، تسويق رقمي...">
            <select name="city">
                <option value="">كل المدن</option>
                <option>غزة</option>
                <option>خانيونس</option>
                <option>دير البلح</option>
                <option>رفح</option>
                <option>شمال غزة</option>
            </select>
            <button type="submit">ابحث الآن</button>
        </form>
        
        <div class="hero-actions" style="margin-top: 30px;">
            <a class="btn" href="/local_services/register.php?role=provider" style="background: var(--accent); margin-left: 15px;">انضم كمزود خدمة</a>
            <a class="btn" href="/local_services/services.php" style="background: rgba(255, 255, 255, 0.2); color: #fff;">تصفح الخدمات</a>
        </div>
    </div>
</section>

<?php if (!empty($categories)): ?>
<section class="category-section">
    <div class="container">
        <h2>التصنيفات الشائعة</h2>
        <p class="muted" style="text-align: center; margin-bottom: 30px;">تصفح آلاف الخدمات الموثوقة حسب المجال.</p>
        
        <div class="categories-grid">
            <?php foreach ($categories as $cat): 
                
                $icon_file = trim($cat['image'] ?? ''); 
                $icon_path = '';

                if (!empty($icon_file)) {
                    $icon_path = '/local_services/assets/uploads/' . htmlspecialchars($icon_file);
                } else {
                    $default_icon_name = $icons_mapping[htmlspecialchars($cat['name'])] ?? 'default-icon.svg';
                    $icon_path = '/local_services/assets/icons/' . htmlspecialchars($default_icon_name);
                }

                // *** استخدام فئة category-card  ***
            ?>
            <a href="/local_services/services.php?cat=<?php echo $cat['id']; ?>" class="category-card">
                <img src="<?= $icon_path ?>" alt="<?= htmlspecialchars($cat['name']) ?>" 
                    style="width: 60px; height: 60px; object-fit: contain; margin: 0 auto 10px; display: block;">
                <h4><?php echo htmlspecialchars($cat['name']); ?></h4>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="why-us" style="padding: 60px 0;">
    <div class="container" style="background: var(--card); padding: 40px; border-radius: 12px; box-shadow: var(--shadow-soft);">
        <h2 style="text-align: center; color: var(--primary-dark);">لماذا تختار منصة خدماتي؟</h2>
        <div class="features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; margin-top: 30px;">
            <div class="feature-item" style="text-align: center;">
                <div class="feature-icon" style="font-size: 30px; color: var(--primary); margin-bottom: 10px;">✨</div>
                <h3>احترافية وجودة</h3>
                <p class="muted">جميع مقدمي الخدمات يتم تقييمهم والتحقق من هويتهم لضمان أعلى مستوى من الجودة والخبرة.</p>
            </div>
            <div class="feature-item" style="text-align: center;">
                <div class="feature-icon" style="font-size: 30px; color: var(--primary); margin-bottom: 10px;">📍</div>
                <h3>قرب جغرافي (محلي)</h3>
                <p class="muted">نساعدك على إيجاد أفضل الخبراء القريبين منك لتقديم خدمات سريعة وفعالة في منطقتك.</p>
            </div>
            <div class="feature-item" style="text-align: center;">
                <div class="feature-icon" style="font-size: 30px; color: var(--primary); margin-bottom: 10px;">🔒</div>
                <h3>أمان وسهولة</h3>
                <p class="muted">نظام دفع آمن وحماية بيانات متقدمة، مع عملية طلب خدمة سهلة ومباشرة لا تتجاوز الدقيقة.</p>
            </div>
        </div>
    </div>
</section>

<section class="latest-services">
    <div class="container">
        <h2>أحدث الخدمات المضافة</h2>
        <?php if (!empty($latest_services)): ?>
        <div class="cards-grid">
            <?php foreach ($latest_services as $row): 
                // تحديد مسار الصورة 
                $service_image_path = $row['image']
                                      ? '/local_services/assets/uploads/' . htmlspecialchars($row['image'])
                                      : '/local_services/assets/images/default-service.jpg';
            ?>
                <div class="card">
                    <div class="card-image" style="background-image: url('<?php echo $service_image_path; ?>')"></div>
                    <div class="card-body">
                        <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                        <p class="muted">مقدم الخدمة: <?php echo htmlspecialchars($row['full_name']); ?> — <?php echo htmlspecialchars($row['city']); ?></p>
                        <p class="price"><?php echo number_format($row['price'],2); ?> ر.س</p>
                        <a class="btn" href="/local_services/service_detail.php?id=<?php echo $row['id']; ?>">عرض الخدمة</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align: center; margin-top: 40px;">
            <a href="/local_services/services.php" class="btn" style="background: var(--primary-dark); padding: 12px 30px;">تصفح جميع الخدمات</a>
        </div>
        <?php else: ?>
            <div class="auth-card" style="max-width: 600px; margin: 0 auto; text-align: center; padding: 40px;">
                <h2>😔 لا توجد خدمات حالياً</h2>
                <p>كن أول من يضيف خدمة إلى المنصة!</p>
                <p><a class="btn" href="/local_services/add_service.php">أضف خدمتك الآن</a></p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php 
// 7. تضمين ملف التذييل
include_once __DIR__ . '/includes/footer.php';
?>