<?php
// /client/services.php - صفحة عرض الخدمات وتصفيتها (لواجهة العميل)

// 🛑 ملاحظة: تم تعديل المسارات للصعود خطوة واحدة (..) للوصول إلى مجلد includes 
require_once __DIR__ . '/../includes/db.php'; 
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php'; 
require_once __DIR__ . '/../includes/header.php';

global $pdo;

// إعداد الفلاتر والمتغيرات
$search_query = trim($_GET['q'] ?? '');
$city = trim($_GET['city'] ?? '');
$category_id = filter_input(INPUT_GET, 'cat', FILTER_VALIDATE_INT); 

$where_clauses = [];
$params = [];

// 1. معالجة البحث (Title OR Description)
if (!empty($search_query)) {
    $q_like = "%" . $search_query . "%";
    $where_clauses[] = "(s.title LIKE ? OR s.description LIKE ?)";
    $params[] = $q_like;
    $params[] = $q_like;
}

// 2. معالجة تصفية المدينة
if (!empty($city)) {
    $where_clauses[] = "s.city = ?";
    $params[] = $city;
}

// 3. معالجة تصفية التصنيف
if ($category_id !== false && $category_id !== null) { 
    $where_clauses[] = "s.category_id = ?"; 
    $params[] = $category_id;
}

// 4. دائمًا يجب أن تكون الخدمات نشطة
$where_clauses[] = "s.is_active = 1";

// بناء الاستعلام بالكامل
$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";

$sql = "SELECT s.id, s.title, s.price, s.city, s.image, u.full_name AS provider_name 
        FROM services s 
        JOIN users u ON s.provider_id = u.id 
        {$where_sql}
        ORDER BY s.created_at DESC";


// 5. تحضير وتنفيذ الاستعلام
$stmt = $pdo->prepare($sql);

if (!$stmt) {
    error_log("SQL Prepare Error in services.php: " . print_r($pdo->errorInfo(), true));
    $services = [];
    $error_message = "حدث خطأ في قاعدة البيانات أثناء تصفح الخدمات.";
} else {
    try {
        $stmt->execute($params);
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC); 
    } catch (PDOException $e) {
        error_log("SQL Execute Error in services.php: " . $e->getMessage());
        $services = [];
        $error_message = "حدث خطأ في قاعدة البيانات أثناء تصفح الخدمات.";
    }
}

// 6. جلب قائمة بالمدن المتاحة للفلاتر
try {
    $cities_stmt = $pdo->query("SELECT DISTINCT city FROM services WHERE is_active = 1 AND city IS NOT NULL AND city != '' ORDER BY city ASC");
    $available_cities = $cities_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Cities Query Error: " . $e->getMessage());
    $available_cities = [];
}
?>

<section class="container service-page-area" style="margin-top: 40px; margin-bottom: 60px;">
    <h2>تصفح الخدمات</h2>
    
    <form method="GET" class="filter-form" style="display:flex; gap: 10px; margin-bottom: 30px; align-items: stretch;">
        <input type="search" name="q" placeholder="ابحث باسم الخدمة..." value="<?php echo htmlspecialchars($search_query); ?>" style="flex-grow:1; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
        
        <select name="city" style="padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
            <option value="">جميع المدن</option>
            <?php foreach ($available_cities as $city_option): ?>
                <option value="<?php echo htmlspecialchars($city_option); ?>" 
                        <?php echo ($city === $city_option) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($city_option); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <?php if ($category_id !== false && $category_id !== null): ?>
            <input type="hidden" name="cat" value="<?php echo htmlspecialchars($category_id); ?>">
        <?php endif; ?>
        
        <button type="submit" class="btn btn-primary" style="padding: 10px 20px; white-space: nowrap;">تصفية</button>
        <a href="/local_services/client/services.php" class="btn btn-secondary" style="padding: 10px 20px; background: #ccc; color: #333; white-space: nowrap;">مسح الفلاتر</a>
    </form>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger" style="padding: 15px; background: #fdd; border: 1px solid #f00; border-radius: 5px; margin-bottom: 20px;"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <div class="services-results-count" style="margin-bottom: 20px; font-weight: bold; color: #555;">
        تم العثور على (<?php echo count($services); ?>) خدمة.
    </div>
    
    <?php if (!empty($services)): ?>
        <div class="cards-grid service-cards-grid row">
            <?php foreach ($services as $s): 
                $service_image_path = !empty($s['image']) 
                    ? '/local_services/assets/uploads/' . htmlspecialchars($s['image'])
                    : '/local_services/assets/images/default-service.jpg';
            ?>
                <div class="col-md-4 mb-4">
                    <div class="card service-card shadow-sm" style="height: 100%;">
                        <div class="card-image" style="height: 200px; background-image: url('<?php echo $service_image_path; ?>'); background-size: cover; background-position: center; border-radius: 4px 4px 0 0;"></div>
                        <div class="card-body p-3">
                            <h3 style="font-size: 1.2em; margin-top: 0;"><?php echo htmlspecialchars($s['title']); ?></h3>
                            <p class="muted provider-info" style="font-size: 0.9em; color: #6c757d;">
                                👤 مقدم الخدمة: <strong><?php echo htmlspecialchars($s['provider_name']); ?></strong>
                            </p>
                            <p class="muted location" style="font-size: 0.9em; color: #6c757d;">📍 <?php echo htmlspecialchars($s['city']); ?></p>
                            <p class="price-tag" style="font-size: 1.5em; font-weight: bold; color: #28a745;">
                                <?php echo number_format($s['price'], 2); ?> ر.س
                            </p>
                            <a href="/local_services/client/service_detail.php?id=<?php echo $s['id']; ?>" class="btn btn-primary w-100 mt-2">مشاهدة التفاصيل</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="auth-card empty-state" style="max-width: 600px; margin: 20px auto; text-align: center; padding: 40px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px;">
            <h2>😔 لا توجد نتائج</h2>
            <p>نأسف، لم يتم العثور على خدمات مطابقة لمعايير البحث الحالية.</p>
            <p><a class="btn btn-info" href="/local_services/client/services.php" style="background: #17a2b8; color: white;">عرض جميع الخدمات</a></p>
        </div>
    <?php endif; ?>
</section>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>