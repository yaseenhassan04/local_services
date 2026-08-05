<?php
// /client/services.php - صفحة عرض الخدمات وتصفيتها

require_once __DIR__ . '/includes/db.php'; 
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php'; 

global $pdo;

$search_query = trim($_GET['q'] ?? '');
$city         = trim($_GET['city'] ?? '');
$category_id  = filter_input(INPUT_GET, 'cat', FILTER_VALIDATE_INT); 

$where_clauses = [];
$params        = [];

if (!empty($search_query)) {
    $q_like = "%" . $search_query . "%";
    $where_clauses[] = "(s.title LIKE ? OR s.description LIKE ?)";
    $params[] = $q_like;
    $params[] = $q_like;
}
if (!empty($city)) {
    $where_clauses[] = "s.city = ?";
    $params[] = $city;
}

// ---- التحقق من وجود جدول categories قبل استخدامه ----
$categories_table_exists = false;
try {
    $pdo->query("SELECT 1 FROM categories LIMIT 1");
    $categories_table_exists = true;
} catch (PDOException $e) {
    $categories_table_exists = false;
}

if ($categories_table_exists && $category_id !== false && $category_id !== null) {
    $where_clauses[] = "s.category_id = ?"; 
    $params[] = $category_id;
}

$where_clauses[] = "s.is_active = 1";

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";

// إذا كان جدول categories موجوداً نضم معه، وإلا نتجاهله
if ($categories_table_exists) {
    $sql = "SELECT s.id, s.title, s.price, s.city, s.image, u.full_name AS provider_name,
                   c.name AS category_name
            FROM services s 
            JOIN users u ON s.provider_id = u.id
            LEFT JOIN categories c ON s.category_id = c.id
            {$where_sql}
            ORDER BY s.created_at DESC";
} else {
    $sql = "SELECT s.id, s.title, s.price, s.city, s.image, u.full_name AS provider_name,
                   NULL AS category_name
            FROM services s 
            JOIN users u ON s.provider_id = u.id 
            {$where_sql}
            ORDER BY s.created_at DESC";
}

$stmt = $pdo->prepare($sql);
try {
    $stmt->execute($params);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC); 
} catch (PDOException $e) {
    error_log("SQL Execute Error in services.php: " . $e->getMessage());
    $services = [];
    $error_message = "حدث خطأ في قاعدة البيانات أثناء تصفح الخدمات.";
}

try {
    $cities_stmt = $pdo->query("SELECT DISTINCT city FROM services WHERE is_active = 1 AND city IS NOT NULL AND city != '' ORDER BY city ASC");
    $available_cities = $cities_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $available_cities = [];
}

// جلب التصنيفات فقط إذا كان الجدول موجوداً
$categories = [];
if ($categories_table_exists) {
    try {
        $cats_stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
        $categories = $cats_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $categories = [];
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>تصفح الخدمات | منصة الخدمات المحلية</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        :root {
            --dark-bg:    #060818;
            --card-bg:    #0e1726;
            --border:     #1b2e4b;
            --text:       #e0e6ed;
            --muted:      #888ea8;
            --primary:    #4361ee;
            --success:    #00ab55;
            --warning:    #e2a03f;
            --danger:     #e7515a;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--dark-bg);
            color: var(--text);
            direction: rtl;
        }
        /* NAVBAR */
        .top-nav {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            height: 64px;
            display: flex; align-items: center; gap: 15px;
            position: sticky; top: 0; z-index: 100;
        }
        .nav-brand {
            display: flex; align-items: center; gap: 8px;
            text-decoration: none;
        }
        .nav-brand .ico {
            width: 34px; height: 34px;
            background: var(--primary);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 16px;
        }
        .nav-brand span { font-size: 17px; font-weight: 800; color: var(--text); }
        .nav-spacer { flex: 1; }
        .nav-link-item {
            color: var(--muted); text-decoration: none;
            padding: 6px 12px; border-radius: 6px;
            font-size: 13px; font-weight: 600;
            transition: all .2s;
        }
        .nav-link-item:hover { background: rgba(67,97,238,.15); color: var(--primary); text-decoration: none; }
        .nav-link-item.active { color: var(--primary); }

        /* HERO SEARCH */
        .hero-area {
            background: linear-gradient(135deg, #0d1b44 0%, #0e1726 100%);
            border-bottom: 1px solid var(--border);
            padding: 40px 24px;
            text-align: center;
        }
        .hero-area h1 { font-size: 28px; font-weight: 800; margin-bottom: 8px; }
        .hero-area p { color: var(--muted); margin-bottom: 24px; }
        .search-bar-main {
            display: flex; gap: 10px; max-width: 700px;
            margin: 0 auto;
        }
        .search-bar-main input, .search-bar-main select {
            background: rgba(255,255,255,.06);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 8px;
            padding: 12px 16px;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
        }
        .search-bar-main input { flex: 1; }
        .search-bar-main input:focus, .search-bar-main select:focus {
            outline: none; border-color: var(--primary);
            background: rgba(67,97,238,.08);
        }
        .search-bar-main select option { background: var(--card-bg); }
        .btn-search {
            background: var(--primary);
            color: #fff; border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-family: 'Tajawal', sans-serif;
            font-weight: 700; font-size: 14px;
            cursor: pointer; white-space: nowrap;
            transition: opacity .2s;
        }
        .btn-search:hover { opacity: .85; }
        .btn-clear {
            background: rgba(255,255,255,.06);
            color: var(--muted); border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
            cursor: pointer; text-decoration: none;
            display: flex; align-items: center;
        }
        .btn-clear:hover { color: var(--text); text-decoration: none; }

        /* FILTER PILLS */
        .filter-pills {
            display: flex; gap: 8px; flex-wrap: wrap;
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            background: var(--card-bg);
        }
        .filter-pill {
            padding: 5px 14px; border-radius: 20px;
            background: rgba(27,46,75,.8);
            border: 1px solid var(--border);
            color: var(--muted); font-size: 12px; font-weight: 600;
            text-decoration: none; cursor: pointer;
            transition: all .2s;
        }
        .filter-pill:hover, .filter-pill.active {
            background: rgba(67,97,238,.15);
            border-color: var(--primary);
            color: var(--primary);
            text-decoration: none;
        }

        /* MAIN GRID */
        .page-container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .results-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 20px;
        }
        .results-count { font-size: 14px; color: var(--muted); }
        .results-count strong { color: var(--primary); }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        /* SERVICE CARD */
        .service-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            transition: transform .2s, box-shadow .2s, border-color .2s;
            display: flex; flex-direction: column;
        }
        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0,0,0,.4);
            border-color: var(--primary);
        }
        .card-image {
            height: 180px;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        .card-category-badge {
            position: absolute; top: 12px; right: 12px;
            background: rgba(67,97,238,.9);
            color: #fff; font-size: 11px; font-weight: 700;
            padding: 3px 10px; border-radius: 20px;
        }
        .card-body {
            padding: 16px;
            flex: 1; display: flex; flex-direction: column;
        }
        .card-title {
            font-size: 15px; font-weight: 700;
            color: var(--text); margin-bottom: 8px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .card-meta {
            display: flex; flex-direction: column; gap: 4px;
            margin-bottom: 12px; flex: 1;
        }
        .card-meta-item {
            display: flex; align-items: center; gap: 6px;
            font-size: 12px; color: var(--muted);
        }
        .card-meta-item i { color: var(--primary); font-size: 14px; }
        .card-footer-inner {
            display: flex; align-items: center; justify-content: space-between;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }
        .price-tag { font-size: 18px; font-weight: 800; color: var(--success); }
        .price-tag small { font-size: 11px; color: var(--muted); }
        .btn-detail {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 8px 14px; border-radius: 7px;
            background: rgba(67,97,238,.15);
            color: var(--primary); font-size: 13px; font-weight: 600;
            text-decoration: none; transition: all .2s;
        }
        .btn-detail:hover { background: var(--primary); color: #fff; text-decoration: none; }

        /* EMPTY STATE */
        .empty-state {
            text-align: center; padding: 80px 20px;
            grid-column: 1 / -1;
        }
        .empty-icon {
            width: 90px; height: 90px;
            background: rgba(67,97,238,.1);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 40px; color: var(--primary);
            margin: 0 auto 20px;
        }
        .empty-state h3 { font-size: 19px; margin-bottom: 8px; }
        .empty-state p { color: var(--muted); }

        @media (max-width: 600px) {
            .search-bar-main { flex-wrap: wrap; }
            .search-bar-main input { min-width: 100%; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="top-nav">
    <a href="/local_services/index.php" class="nav-brand">
        <div class="ico"><i class="las la-map-marker"></i></div>
        <span>خدماتي</span>
    </a>
    <div class="nav-spacer"></div>
    <a href="/local_services/client_dashboard.php" class="nav-link-item">
        <i class="las la-tachometer-alt"></i> لوحة التحكم
    </a>
    <a href="/local_services/client/services.php" class="nav-link-item active">
        <i class="las la-th-large"></i> الخدمات
    </a>
    <a href="/local_services/profile.php" class="nav-link-item">
        <i class="las la-user"></i> حسابي
    </a>
    <a href="/local_services/logout.php" class="nav-link-item" style="color: #e7515a;">
        <i class="las la-sign-out-alt"></i> خروج
    </a>
</nav>

<!-- HERO SEARCH -->
<div class="hero-area">
    <h1>🔍 تصفح الخدمات المتاحة</h1>
    <p>ابحث عن الخدمة المناسبة بسهولة وأوامر مباشرة</p>

    <form method="GET" class="search-bar-main">
        <input type="search" name="q"
               placeholder="ابحث باسم الخدمة أو وصفها..."
               value="<?php echo htmlspecialchars($search_query); ?>">

        <select name="city">
            <option value="">📍 جميع المدن</option>
            <?php foreach ($available_cities as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>"
                    <?php echo ($city === $c) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ($category_id): ?>
            <input type="hidden" name="cat" value="<?php echo (int)$category_id; ?>">
        <?php endif; ?>

        <button type="submit" class="btn-search"><i class="las la-search"></i> بحث</button>
        <a href="/local_services/client/services.php" class="btn-clear"><i class="las la-times"></i></a>
    </form>
</div>

<!-- CATEGORY FILTER PILLS -->
<?php if (!empty($categories)): ?>
<div class="filter-pills">
    <a href="/local_services/client/services.php<?php echo $search_query ? '?q='.urlencode($search_query) : ''; ?>"
       class="filter-pill <?php echo !$category_id ? 'active' : ''; ?>">
        الكل
    </a>
    <?php foreach ($categories as $cat): ?>
        <a href="?<?php echo http_build_query(['q' => $search_query, 'city' => $city, 'cat' => $cat['id']]); ?>"
           class="filter-pill <?php echo ($category_id == $cat['id']) ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($cat['name']); ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- MAIN CONTENT -->
<div class="page-container">
    <div class="results-header">
        <div class="results-count">
            تم العثور على <strong><?php echo count($services); ?></strong> خدمة
            <?php if ($search_query): ?>
                بكلمة "<strong style="color:var(--warning)"><?php echo htmlspecialchars($search_query); ?></strong>"
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div style="background:rgba(231,81,90,.15);border:1px solid rgba(231,81,90,.3);color:#e7515a;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
            <i class="las la-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <div class="services-grid">
        <?php if (empty($services)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="las la-search"></i></div>
                <h3>لم يتم العثور على خدمات</h3>
                <p>جرب تغيير كلمات البحث أو المدينة أو التصنيف</p>
                <a href="/local_services/client/services.php"
                   style="display:inline-block;margin-top:16px;padding:10px 20px;background:rgba(67,97,238,.15);color:var(--primary);border-radius:8px;text-decoration:none;font-weight:600;">
                    عرض جميع الخدمات
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($services as $s):
                $img = !empty($s['image'])
                    ? '/local_services/assets/uploads/' . htmlspecialchars($s['image'])
                    : '/local_services/assets/images/default-service.jpg';
            ?>
                <div class="service-card">
                    <div class="card-image" style="background-image:url('<?php echo $img; ?>')">
                        <span class="card-category-badge">
                            <i class="las la-tag"></i> خدمة
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="card-title"><?php echo htmlspecialchars($s['title']); ?></div>
                        <div class="card-meta">
                            <div class="card-meta-item">
                                <i class="las la-user-tie"></i>
                                <?php echo htmlspecialchars($s['provider_name']); ?>
                            </div>
                            <div class="card-meta-item">
                                <i class="las la-map-marker-alt"></i>
                                <?php echo htmlspecialchars($s['city'] ?? 'غير محددة'); ?>
                            </div>
                        </div>
                        <div class="card-footer-inner">
                            <div class="price-tag">
                                <?php echo number_format($s['price'], 2); ?>
                                <small>ر.س</small>
                            </div>
                            <a href="/local_services/client/service_detail.php?id=<?php echo $s['id']; ?>"
                               class="btn-detail">
                                <i class="las la-eye"></i> التفاصيل
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>