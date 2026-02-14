<?php
// /local_services/dashboard.php
// مخصص الآن للأدوار: المدير (Admin) والمزود (Provider) فقط

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php'; 
require_once __DIR__ . '/includes/functions.php'; 

requireLogin();

global $pdo; 
$user = getCurrentUser();
$user_id = $user['id'];
$user_role = $user['role']; 

// توجيه العميل فوراً إلى لوحة التحكم الخاصة به
if ($user_role === 'client') {
    header("Location: /local_services/client_dashboard.php");
    exit;
}

require_once __DIR__ . '/includes/header.php'; 

$csrf_token = generateCsrfToken(); 
$is_dashboard_active = true; 
?>



<section class="container dashboard-area">
    <?php display_message(); ?>
    <div class="dashboard-grid">
        
        <aside class="dashboard-right">
            
            <div class="card user-info-card">
    <h4>مرحباً، <?php echo htmlspecialchars($user['full_name']); ?></h4>
    <p class="muted">دور الحساب: <?php echo htmlspecialchars($user_role); ?></p>
    <p class="muted">البريد: <?php echo htmlspecialchars($user['email']); ?></p>
    
    
    <?php if (!empty($user['phone'])): ?>
    <p class="muted">الهاتف: <?php echo htmlspecialchars($user['phone']); ?></p>
    <?php endif; ?>
    <a href="/local_services/profile.php" class="btn btn-sm btn-info mt-2" style="background: #17a2b8; color: white; display: block; text-align: center;">تعديل الملف الشخصي</a>
</div>
            
            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">

            <nav class="dashboard-nav-menu">
                <a href="/local_services/index.php" class="nav-item">الرئيسية</a>
                <a href="/local_services/services.php" class="nav-item">تصفح الخدمات</a>
                
                <a href="/local_services/dashboard.php" class="nav-item active">لوحة التحكم</a>

                
                
             
                
                <a href="/local_services/logout.php" class="nav-item logout" style="margin-top: 20px; color: #dc3545;"> خروج آمن</a>
            </nav>

        </aside>

        <div class="dashboard-left" style="margin-top: 20px;">
            <h2>لوحة التحكم - <?php echo ($user_role === 'admin') ? 'المدير' : 'المزود'; ?></h2>
            
            <?php if ($user_role === 'admin'): /* المحتوى الخاص بالمدير */ ?>
                
                <h3 class="text-center mb-4 mt-4" style="text-align: right;">نطاق التحكم الإداري</h3>
                
                <div class="cards-grid">
                    <a href="admin/manage_orders.php" class="dashboard-card-link">
                        <div class="card shadow text-center py-5" style="background: #17a2b8; color: white; border-radius: 8px;">
                            <i class="fas fa-clipboard-list fa-4x mb-3"></i> 
                            <h5 class="card-title mb-0">إدارة الطلبات</h5>
                        </div>
                    </a>

                    <a href="admin/manage_services.php" class="dashboard-card-link">
                        <div class="card shadow text-center py-5" style="background: #007bff; color: white; border-radius: 8px;">
                            <i class="fas fa-tools fa-4x mb-3"></i>
                            <h5 class="card-title mb-0">إدارة الخدمات</h5>
                        </div>
                    </a>
                    
                    <a href="admin/manage_categories.php" class="dashboard-card-link">
                        <div class="card shadow text-center py-5" style="background: #28a745; color: white; border-radius: 8px;">
                            <i class="fas fa-tags fa-4x mb-3"></i>
                            <h5 class="card-title mb-0">إدارة التصنيفات</h5>
                        </div>
                    </a>
                    
                    <a href="admin/manage_users.php" class="dashboard-card-link">
                        <div class="card shadow text-center py-10" style="background: #dc3545; color: white; border-radius: 8px;">
                            <i class="fas fa-users-cog fa-4x mb-3"></i>
                            <h5 class="card-title mb-0">إدارة المستخدمين</h5>
                        </div>
                    </a>
                </div>
                
                <?php elseif ($user_role === 'provider'): ?>
                
                <h3 id="incoming_orders" style="margin-top:25px; text-align: right;">طلبات العملاء الواردة</h3>
                
                <?php
                $stmt_orders = $pdo->prepare("SELECT 
                    o.id, o.order_date, o.status, s.title, c.full_name AS client_name, o.amount
                    FROM orders o
                    JOIN services s ON o.service_id = s.id
                    JOIN users c ON o.client_id = c.id
                    WHERE o.provider_id = ?
                    ORDER BY o.order_date DESC
                    LIMIT 20");
                $stmt_orders->execute([$user_id]);
                $incoming_orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <?php if (!empty($incoming_orders)): ?>
                    <div style="overflow-x: auto;">
                        <table class="dashboard-table">
                            <thead>
                                <tr style="text-align:right;">
                                    <th>رقم الطلب</th>
                                    <th>الخدمة</th>
                                    <th>طالب الخدمة</th>
                                    <th>المبلغ</th>
                                    <th>التاريخ</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($incoming_orders as $o): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($o['id']); ?></td>
                                    <td><?php echo htmlspecialchars($o['title']); ?></td>
                                    <td><?php echo htmlspecialchars($o['client_name']); ?></td>
                                    <td><?php echo number_format($o['amount'], 2); ?> ر.س</td>
                                    <td><?php echo date('Y-m-d', strtotime($o['order_date'])); ?></td>
                                    <td>
                                        <span class="status-badge" style="background-color: <?php echo get_status_color($o['status']); ?>; color: white;">
                                            <?php echo translate_status($o['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="btn btn-sm" href="/local_services/view_order.php?id=<?php echo $o['id']; ?>" style="background:#007bff; color:white; padding: 5px 10px; font-size: 0.9em; text-decoration: none;">عرض وتحديث</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info" style="padding: 15px; background: #d1ecf1; border-radius: 5px; text-align: right;">
                        <p class="muted">لم تتلقَ أي طلبات على خدماتك بعد.</p>
                    </div>
                <?php endif; ?>


                <h3 id="my_services" style="margin-top:40px; border-top: 1px dashed #eee; padding-top: 20px; text-align: right;">خدماتي المنشورة</h3>
                
                <?php
                // استعلام جلب الخدمات 
                $stmt = $pdo->prepare("SELECT id, title, price, city, image, is_active, created_at FROM services WHERE provider_id = ? ORDER BY created_at DESC");
                $stmt->execute([$user_id]);
                $services = $stmt->fetchAll(PDO::FETCH_ASSOC); 
                ?>
                
                <?php if (!empty($services)): ?>
                    <div class="cards-grid">
                        <?php foreach ($services as $s): 
                            $service_image_path = $s['image'] 
                                ? '/local_services/assets/uploads/' . htmlspecialchars($s['image'])
                                : '/local_services/assets/images/default-service.jpg';
                        ?>
                            <div class="card shadow" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
                                <div class="card-image" style="background-image: url('<?php echo $service_image_path; ?>')"></div>
                                <div class="card-body">
                                    <h3 style="font-size: 1.2em;"><?php echo htmlspecialchars($s['title']); ?></h3>
                                    <p class="muted"><?php echo htmlspecialchars($s['city']); ?></p>
                                    <p class="price" style="font-weight: bold; color: #28a745;"><?php echo number_format($s['price'],2); ?> ر.س</p>
                                    <div class="action-links" style="margin-top: 15px; display: flex; gap: 10px; justify-content: flex-end;">
                                        <a class="btn btn-sm" href="/local_services/service_detail.php?id=<?php echo $s['id']; ?>" style="background:#007bff; color:white; text-decoration: none;">عرض</a>
                                        <a class="btn btn-sm" href="/local_services/edit_service.php?id=<?php echo $s['id']; ?>" style="background:#ffb74d; color: #333; text-decoration: none;">تعديل</a>
                                        
                                        <form method="post" action="/local_services/delete_service.php" style="display:inline;">
                                            <input type="hidden" name="service_id" value="<?php echo $s['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <button type="submit" class="btn btn-sm" style="background:#dc3545; color:white; border:none;" onclick="return confirm('هل أنت متأكد من حذف هذه الخدمة؟');">حذف</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning" style="padding: 15px; background: #fff3cd; border-radius: 5px; text-align: right;">
                        <p class="muted">لم تقم بإضافة أي خدمات بعد. <a href="/local_services/provider/add_service.php">أضف خدمتك الأولى</a>.</p>
                    </div>
                <?php endif; ?>

            <?php endif;  ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>