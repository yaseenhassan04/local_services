<?php

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php'; 

//  1. حماية الصفحة: يجب أن يكون المدير فقط
check_login('admin'); 

global $pdo;
$orders = [];
$errors = [];




// ==========================================================
// 2. جلب جميع الطلبات للعرض (باستخدام 4 جداول)
// ==========================================================
try {
    //  الاستعلام يعتمد على الأعمدة الموجودة لديك في جدول orders:
    // o.status, o.amount (نستخدم o.amount لتمثيل المبلغ), o.created_at (نستخدم order_date), o.user_id (client_id), s.provider_id
    
    // بناءً على صورة جدول orders لديك (الذي يحتوي على client_id و order_date):
    $sql = "SELECT 
                o.id, 
                o.status, 
                o.amount, 
                o.order_date AS created_at, -- تم استخدام order_date كبديل لـ created_at
                s.title AS service_title,
                c.name AS category_name,
                u_client.full_name AS client_name,
                u_provider.full_name AS provider_name
            FROM 
                orders o
            JOIN 
                services s ON o.service_id = s.id
            JOIN 
                users u_client ON o.client_id = u_client.id -- client_id هو العميل
            JOIN 
                users u_provider ON s.provider_id = u_provider.id -- المزود يتم جلبه من جدول services
            LEFT JOIN 
                categories c ON s.category_id = c.id
            ORDER BY o.order_date DESC";
            
    $stmt = $pdo->query($sql);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    set_message("فشل في جلب قائمة الطلبات. خطأ في DB: " . $e->getMessage(), "danger");
}

include_once '../includes/header.php'; 
?>

<div class="container admin-content">
    
    <h2>💼 إدارة طلبات الخدمات (العقود) (<?= count($orders) ?>)</h2>
    <?php display_message(); ?>

    <table border="1" style="width: 100%; border-collapse: collapse; margin-top: 20px; text-align: center; font-size: 0.9em;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th style="padding: 10px;">ID</th>
                <th style="padding: 10px;">العميل (طالب الخدمة)</th>
                <th style="padding: 10px;">مزود الخدمة</th>
                <th style="padding: 10px;">الخدمة المطلوبة</th>
                <th style="padding: 10px;">المبلغ (ر.س)</th>
                <th style="padding: 10px;">الحالة</th>
                <th style="padding: 10px;">تاريخ الطلب</th>
                <th style="padding: 10px;">الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 15px;">لا توجد طلبات/عقود حالياً في النظام.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td style="padding: 10px;"><?= $order['id'] ?></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($order['client_name']) ?></td>
                        <td style="padding: 10px; color: #007bff; font-weight: bold;"><?= htmlspecialchars($order['provider_name']) ?></td>
                        <td style="padding: 10px; text-align: right;">
                            **<?= htmlspecialchars($order['service_title']) ?>**
                            <br><small style="color: #888;">(<?= htmlspecialchars($order['category_name'] ?? 'لا يوجد تصنيف') ?>)</small>
                        </td>
                        <td style="padding: 10px;"><?= number_format($order['amount'] ?? 0, 2) ?></td>
                        <td style="padding: 10px;">
                            <span style="font-weight: bold; color: <?= get_status_color($order['status']) ?>;">
                                <?= get_status_display($order['status']) ?>
                            </span>
                        </td>
                        <td style="padding: 10px;"><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></td>
                        <td style="padding: 10px; white-space: nowrap;">
                            <a href="view_order.php?id=<?= $order['id'] ?>" style="margin-right: 10px; color: green; text-decoration: none;">عرض التفاصيل</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
</div>

<?php 
include_once '../includes/footer.php';
?>