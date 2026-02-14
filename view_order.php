<?php


require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

global $pdo;
$user = getCurrentUser();
$user_id = $user['id'];
$user_role = $user['role'];

// دوال مساعدة
if (!function_exists('translate_status')) {
    function translate_status($status) {
        $status = $status ?: 'pending';
        switch ($status) {
            case 'pending': return 'قيد الانتظار';
            case 'processing': return 'قيد المعالجة';
            case 'in_progress': return 'قيد التنفيذ';
            case 'completed': return 'مكتمل';
            case 'cancelled': return 'ملغاة';
            default: return 'غير محدد';
        }
    }
}

if (!function_exists('display_payment_status')) {
    function display_payment_status($status) {
        $status = $status ?: 'pending_upload';
        switch ($status) {
            case 'paid': 
                return ['display' => 'مدفوع ', 'color' => '#28a745'];
            case 'pending_upload': 
            case 'pending_verification': 
                return ['display' => 'مدفوع ', 'color' => '#28a745'];
            default: 
                return ['display' => 'غير محدد', 'color' => '#6c757d'];
        }
    }
}

function get_status_color($status) {
    switch ($status) {
        case 'pending': return '#ffc107'; 
        case 'processing': return '#17a2b8'; 
        case 'in_progress': return '#007bff'; 
        case 'completed': return '#28a745'; 
        case 'cancelled': return '#dc3545'; 
        default: return '#6c757d'; 
    }
}

// جلب رقم الطلب
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$order_id) {
    set_message("رقم الطلب غير صالح.", "danger");
    header("Location: /local_services/{$user_role}_dashboard.php");
    exit;
}

// جلب تفاصيل الطلب
try {
    $stmt = $pdo->prepare("
        SELECT 
            o.*, 
            s.title AS service_title, 
            p.full_name AS provider_name, 
            p.phone AS provider_phone,
            c.full_name AS client_name, 
            c.phone AS client_phone,
            (SELECT COUNT(r.id) FROM reviews r WHERE r.order_id = o.id) AS review_count,
            COALESCE(o.status, 'pending') AS actual_status,
            COALESCE(o.payment_status, 'pending_upload') AS actual_payment_status
        FROM orders o
        JOIN services s ON o.service_id = s.id
        JOIN users p ON o.provider_id = p.id
        JOIN users c ON o.client_id = c.id
        WHERE o.id = ? AND (o.client_id = ? OR o.provider_id = ?)
    ");

    $stmt->execute([$order_id, $user_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Order fetching error: " . $e->getMessage());
    set_message("حدث خطأ في جلب بيانات الطلب: " . $e->getMessage(), "danger");
    header("Location: /local_services/dashboard.php");
    exit;
}

if (!$order) {
    $redirect_url = ($user_role === 'client') ? 'client_dashboard.php' : 'provider_dashboard.php';
    set_message("الطلب غير موجود أو ليس لديك صلاحية لعرضه.", "danger");
    header("Location: /local_services/{$redirect_url}");
    exit;
}

$current_status = $order['actual_status'];
$current_payment_status = $order['actual_payment_status'];

require_once __DIR__ . '/includes/header.php';
?>

<section class="container" style="padding: 40px 0; max-width: 900px; text-align: right;">
    <?php display_message(); ?>

    <h2 style="border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 30px;">
        تفاصيل الطلب

    </h2>

    <div class="order-details-grid" style="display: grid; grid-template-columns: 1fr 300px; gap: 30px;">

        <div class="main-content">
            <div class="card shadow" style="padding: 25px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin-top: 0; color: #007bff;">ملخص الطلب</h4>
                <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                    <tr>
                        <th style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">الخدمة المطلوبة:</th>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">
                            <a href="/local_services/service_detail.php?id=<?php echo $order['service_id']; ?>" style="color: #007bff; font-weight: bold; text-decoration: none;">
                                <?php echo htmlspecialchars($order['service_title']); ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">المبلغ الإجمالي:</th>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold; color: #28a745;">
                            <?php echo number_format($order['amount'], 2); ?> ر.س
                        </td>
                    </tr>
                    <tr>
                        <th style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">تاريخ الطلب:</th>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">
                            <?php echo date('Y-m-d H:i', strtotime($order['order_date'])); ?>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">الحالة:</th>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">
                            <span class="badge" style="background-color: <?php echo get_status_color($current_status); ?>; color: white; padding: 5px 10px; border-radius: 4px; font-weight: bold;">
                                <?php echo translate_status($current_status); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding: 8px 0; text-align: right;">حالة الدفع:</th>
                        <td style="padding: 8px 0;">
                            <?php $payment_info = display_payment_status($current_payment_status); ?>
                            <span class="badge" style="background-color: <?php echo htmlspecialchars($payment_info['color']); ?>; color: white; padding: 5px 10px; border-radius: 4px; font-weight: bold;">
                                <?php echo htmlspecialchars($payment_info['display']); ?>
                            </span>
                        </td>
                    </tr>
                </table>
                
            </div>

            <div class="card shadow" style="padding: 25px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="color: #6c757d;">تفاصيل طلب العميل</h4>
                <p style="white-space: pre-wrap; margin-top: 10px; color: #555; background: #f4f4f4; padding: 10px; border-radius: 4px;">
                    <?php echo nl2br(htmlspecialchars($order['details'] ?: 'العميل لم يضف تفاصيل إضافية.')); ?>
                </p>
            </div>

            <div class="card shadow" style="padding: 25px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="color: #6c757d;">معلومات الدفع</h4>
                <p style="white-space: pre-wrap; margin-top: 10px; color: #555;">
                    <?php echo nl2br(htmlspecialchars($order['payment_proof'] ?: 'لا توجد معلومات دفع متاحة.')); ?>
                </p>
            </div>
        </div>

        <div class="sidebar">
            <div class="card provider-info shadow" style="padding: 20px; background: #f8f9fa; border: 1px solid #eee; border-radius: 8px; margin-bottom: 20px;">
                <h5 style="margin-top: 0; color: #333;">تفاصيل التواصل</h5>
                <?php if ($user_role === 'client'): ?>
                    <h6 class="mt-3">بيانات المزود</h6>
                    <p><strong>الاسم:</strong> <?php echo htmlspecialchars($order['provider_name']); ?></p>
                    <p><strong>الهاتف:</strong> <?php echo htmlspecialchars($order['provider_phone']); ?></p>
                <?php else: ?>
                    <h6 class="mt-3">بيانات العميل</h6>
                    <p><strong>الاسم:</strong> <?php echo htmlspecialchars($order['client_name']); ?></p>
                    <p><strong>الهاتف:</strong> <?php echo htmlspecialchars($order['client_phone']); ?></p>
                <?php endif; ?>
            </div>

            <?php 
            $is_client = ($user_id == $order['client_id']); 
            $is_provider = ($user_id == $order['provider_id']); 
            $csrf_token = generateCsrfToken();
            ?>

           
            
            <div class="card actions-box shadow" style="padding: 20px; border: 1px solid #007bff; border-radius: 8px;">
                <h5 style="margin-top: 0; color: #007bff;">الإجراءات المتاحة</h5>

                <?php
                $can_cancel = $is_client || in_array($current_status, ['pending', 'processing']);
                $can_review = $is_client && ($current_status === 'completed' && $order['review_count'] === '0');

                $is_payment_pending = in_array($current_payment_status, ['pending_upload', 'pending_verification']);
               
                $can_start_work = $is_provider || $current_payment_status === 'paid' && $current_status === 'processing';
                $can_complete_work = $is_provider && $current_status === 'in_progress';
                ?>

                
                <?php if ($can_start_work): ?>
                    <form action="/local_services/actions/update_order.php" method="POST" style="margin-top: 15px;">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <input type="hidden" name="new_status" value="in_progress">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" class="btn btn-info" style="width: 100%; padding: 10px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            ▶️ بدء تنفيذ الطلب
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($can_complete_work): ?>
                    <form action="/local_services/actions/update_order.php" method="POST" style="margin-top: 15px;">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <input type="hidden" name="new_status" value="completed">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            ✔️ إنهاء وإكمال الخدمة
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($can_cancel): ?>
                    <form action="/local_services/actions/update_order.php" method="POST" onsubmit="return confirm('هل أنت متأكد من إلغاء هذا الطلب؟ لا يمكن التراجع عن هذا الإجراء.');" style="margin-top: 15px;">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <input type="hidden" name="new_status" value="cancelled">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" class="btn btn-danger" style="width: 100%; padding: 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            ❌ إلغاء الطلب
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($can_review): ?>
                    <a href="/local_services/review_service.php?order_id=<?php echo $order['id']; ?>" class="btn btn-warning" style="display: block; width: 100%; padding: 10px; background: #ffc107; color: #333; border-radius: 4px; text-align: center; text-decoration: none; margin-top: 15px;">
                        ⭐ تقييم الخدمة
                    </a>
                <?php elseif ($order['review_count'] > 0 && $is_client): ?>
                    <p style="margin-top: 15px; color: #28a745; text-align: center; font-weight: bold;">
                        تم تقييم هذه الخدمة بنجاح.
                    </p>
                <?php endif; ?>

                <?php if (in_array($current_status, ['in_progress', 'completed', 'cancelled']) && !$is_client): ?>
                    <p style="margin-top: 15px; color: #007bff; text-align: center; font-weight: bold;">
                        🔄 <?php echo translate_status($current_status); ?>
                    </p>
                <?php endif; ?>

            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>