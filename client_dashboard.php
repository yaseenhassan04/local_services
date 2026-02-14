<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php'; 

requireLogin();

global $pdo;
$user = getCurrentUser();
$user_id = $user['id'];
$user_role = $user['role'];

// توجيه الأدوار الأخرى إلى لوحة التحكم الرئيسية
if ($user_role !== 'client') {
    header("Location: /local_services/dashboard.php");
    exit;
}

// ==========================================================
// 1. جلب الإحصائيات الموجزة للعميل
// ==========================================================
$stmt_stats = $pdo->prepare("SELECT
    COUNT(id) AS total_orders,
    SUM(CASE WHEN status IN ('pending', 'processing', 'in_progress') THEN 1 ELSE 0 END) AS active_orders,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_orders,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders
    FROM orders WHERE client_id = ?");
$stmt_stats->execute([$user_id]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// ==========================================================
// 2. جلب الطلبات السابقة للعميل
// ==========================================================
$stmt = $pdo->prepare("SELECT o.id, o.order_date, o.status, s.title, u.full_name AS provider_name, o.amount
    FROM orders o
    JOIN services s ON o.service_id = s.id
    JOIN users u ON o.provider_id = u.id
    WHERE o.client_id = ?
    ORDER BY o.order_date DESC
    LIMIT 20");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';

$csrf_token = generateCsrfToken();
$is_client_dashboard_active = true;
?>

<section class="container dashboard-area">
    <?php display_message(); ?>
    <div class="dashboard-grid">
        
        <aside class="dashboard-right">
            
        <div class="card user-info-card" style="padding: 15px; background: var(--card); border-radius: 8px; box-shadow: var(--shadow-soft);">
    <h4>مرحباً، <?php echo htmlspecialchars($user['full_name']); ?></h4>
    <p class="muted">دور الحساب: **<?php echo htmlspecialchars($user_role); ?>**</p>
    <p class="muted">البريد: <?php echo htmlspecialchars($user['email']); ?></p>
    
    
    <?php if (!empty($user['phone'])): ?>
    <p class="muted">الهاتف: <?php echo htmlspecialchars($user['phone']); ?></p>
    <?php endif; ?>
    <a href="/local_services/profile.php" class="btn btn-info mt-2" style="background: var(--info); color: white; display: block; text-align: center; margin-top: 10px;">تعديل الملف الشخصي</a>
</div>
            
            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">

            <nav class="dashboard-nav-menu">
                <a href="/local_services/index.php" class="nav-item">الرئيسية</a>
                <a href="/local_services/services.php" class="nav-item">تصفح الخدمات</a>
                
                <a href="/local_services/client_dashboard.php" class="nav-item active">طلباتي السابقة</a>
                
                <a href="/local_services/logout.php" class="nav-item logout" style="margin-top: 20px; color: var(--accent);"> خروج آمن</a>
            </nav>

        </aside>

        <div class="dashboard-left" style="margin-top: 20px;">
            <h2>لوحة التحكم - ملخص الطلبات</h2>
            
            <div class="cards-grid-stats">
                
                <div class="stat-card" style="background: var(--primary);">
                    <h4><?php echo $stats['total_orders']; ?></h4>
                    <p>إجمالي الطلبات</p>
                </div>
                
                <div class="stat-card" style="background: var(--warning); color: #333;">
                    <h4><?php echo $stats['active_orders']; ?></h4>
                    <p>طلبات قيد العمل</p>
                </div>
                
                <div class="stat-card" style="background: var(--success);">
                    <h4><?php echo $stats['completed_orders']; ?></h4>
                    <p>طلبات مكتملة</p>
                </div>

                <div class="stat-card" style="background: var(--accent);">
                    <h4><?php echo $stats['cancelled_orders']; ?></h4>
                    <p>طلبات ملغاة</p>
                </div>
            </div>
            <h3 style="border-top: 1px dashed #eee; padding-top: 20px; text-align: right;">الطلبات التفصيلية (آخر 20)</h3>
            
            <?php if (empty($orders)): ?>
                <div class="alert alert-warning" style="padding: 15px; background: #fff3cd; border-radius: 5px; text-align: right;">
                    <p class="muted">لا توجد طلبات بعد. <a href="/local_services/services.php">ابدأ بتصفح الخدمات</a>.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr style="text-align:right;">
                                <th>رقم الطلب</th>
                                <th>الخدمة</th>
                                <th>المزود</th>
                                <th>المبلغ</th>
                                <th>التاريخ</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($orders as $o):
                            // 🚀 جلب الحالة باستخدام الدوال الموحدة 
                            $order_status = $o['status'] ?? 'pending';
                            $status_display = translate_status($order_status);
                            $status_color_hex = get_status_color($order_status);
                            $review_check = false; 
                        ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($o['id']); ?></td>
                                <td><?php echo htmlspecialchars($o['title']); ?></td>
                                <td><?php echo htmlspecialchars($o['provider_name']); ?></td>
                                <td><?php echo number_format($o['amount'], 2); ?> ر.س</td>
                                <td><?php echo date('Y-m-d', strtotime($o['order_date'])); ?></td>
                                <td>
                                    <span class="status-badge" style="background-color: <?php echo htmlspecialchars($status_color_hex); ?>;">
                                        <?php echo htmlspecialchars($status_display); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px; align-items: center;">
                                        <a class="btn btn-sm"
                                            href="/local_services/view_order.php?id=<?php echo $o['id']; ?>"
                                            style="background:var(--primary); color:white; padding: 5px 10px; font-size: 0.9em; text-decoration: none;">
                                            عرض
                                        </a>
                                        
                                        <?php
                                        if ($o['status'] === 'completed' ): ?>
                                            <a class="btn btn-sm" 
                                               href="/local_services/review_service.php?order_id=<?php echo $o['id']; ?>" 
                                               style="background:var(--warning); color: #333; padding: 5px 10px; font-size: 0.9em; text-decoration: none;">
                                                تقييم
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>