<?php
// admin/view_order.php - عرض تفاصيل طلب خدمة معين وإجراءات المدير

// ==========================================================
// 1. التهيئة والتحقق من الجلسة والصلاحيات
// ==========================================================
session_start(); 
require_once '../includes/db.php';
require_once '../includes/functions.php'; 
require_once '../includes/auth.php'; 

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit;
}

// ==========================================================
// 2. التحقق من ID الطلب
// ==========================================================
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    set_message("لم يتم تحديد رقم الطلب أو أنه غير صالح.", "danger");
    header("Location: manage_orders.php");
    exit;
}

$order_id = $_GET['id'];
$order = null; 

// ==========================================================
// 3. جلب تفاصيل الطلب من قاعدة البيانات
// ==========================================================
try {
    $sql = "SELECT 
                o.*, 
                s.title AS service_title, 
                s.description AS service_description,
                s.price AS service_price,
                c.name AS category_name,
                u_client.full_name AS client_name,
                u_client.email AS client_email,
                u_provider.full_name AS provider_name,
                u_provider.email AS provider_email
            FROM 
                orders o
            JOIN 
                services s ON o.service_id = s.id
            JOIN 
                users u_client ON o.client_id = u_client.id
            JOIN 
                users u_provider ON o.provider_id = u_provider.id 
            LEFT JOIN 
                categories c ON s.category_id = c.id
            WHERE 
                o.id = :order_id";

    global $pdo; 
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        set_message("الطلب رقم {$order_id} غير موجود.", "danger");
        header("Location: manage_orders.php");
        exit;
    }

} catch (PDOException $e) {
    set_message("خطأ في جلب بيانات الطلب: " . $e->getMessage(), "danger");
    header("Location: manage_orders.php");
    exit;
}

// ==========================================================
// 4. صفحة العرض
// ==========================================================
$pageTitle = "تفاصيل الطلب رقم {$order_id}";
include_once '../includes/header.php'; 
display_message();
?>

<div class="container mt-4">

    <div class="text-center mb-4">
        <h2 class="font-weight-bold">
            <i class="fas fa-file-invoice"></i> تفاصيل الطلب رقم <?= htmlspecialchars($order['id']) ?>
        </h2>

        <span class="badge badge-<?= get_status_color($order['status']) ?> px-3 py-2">
            <?= get_status_display($order['status']) ?>
        </span>

        <div class="mt-3">
            <a href="manage_orders.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-right"></i> العودة للطلبات
            </a>
        </div>
    </div>

    <!-- معلومات عامة -->
    <div class="card shadow mb-4 border-0 rounded-lg">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle"></i> معلومات الطلب</h5>
        </div>
        <div class="card-body">

            <div class="row text-center">

                <div class="col-md-4 mb-3">
                    <div class="p-3 bg-light rounded shadow-sm">
                        <i class="fas fa-calendar-alt fa-lg text-primary mb-2"></i>
                        <h6 class="mb-0">تاريخ الطلب</h6>
                        <p class="text-dark small mb-0">
                            <?= date('Y-m-d H:i:s', strtotime($order['order_date'])) ?>
                        </p>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="p-3 bg-light rounded shadow-sm">
                        <i class="fas fa-credit-card fa-lg text-warning mb-2"></i>
                        <h6 class="mb-0">حالة الدفع</h6>
                        <span class="badge badge-warning mt-1">
                            <?= htmlspecialchars($order['payment_status']) ?>
                        </span>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="p-3 bg-light rounded shadow-sm">
                        <i class="fas fa-dollar-sign fa-lg text-success mb-2"></i>
                        <h6 class="mb-0">المبلغ الكلي</h6>
                        <p class="h5 text-success font-weight-bold mb-0">
                            <?= number_format($order['amount'], 2) ?> ر.س
                        </p>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- العميل والمزود -->
    <div class="row">

        <div class="col-md-6 mb-4">
            <div class="card shadow border-0 rounded-lg">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-user"></i> معلومات العميل</h5>
                </div>
                <div class="card-body">
                    <p class="small mb-1"><strong>الاسم:</strong> <?= htmlspecialchars($order['client_name']) ?></p>
                    <p class="small mb-1">
                        <strong>البريد:</strong>
                        <a href="mailto:<?= $order['client_email'] ?>"><?= $order['client_email'] ?></a>
                    </p>
                    <a href="edit_user.php?id=<?= $order['client_id'] ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-edit"></i> تعديل
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow border-0 rounded-lg">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-user-tie"></i> معلومات المزود</h5>
                </div>
                <div class="card-body">
                    <p class="small mb-1"><strong>الاسم:</strong> <?= htmlspecialchars($order['provider_name']) ?></p>
                    <p class="small mb-1">
                        <strong>البريد:</strong>
                        <a href="mailto:<?= $order['provider_email'] ?>"><?= $order['provider_email'] ?></a>
                    </p>
                    <a href="edit_user.php?id=<?= $order['provider_id'] ?>" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-edit"></i> تعديل
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- تفاصيل الخدمة -->
    <div class="card shadow mb-4 border-0 rounded-lg">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-tools"></i> تفاصيل الخدمة</h5>
        </div>

        <div class="card-body">
            <div class="row">

                <div class="col-md-4">
                    <div class="p-3 bg-light rounded shadow-sm mb-3 text-center">
                        <h6>عنوان الخدمة</h6>
                        <p class="small mb-0"><?= htmlspecialchars($order['service_title']) ?></p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="p-3 bg-light rounded shadow-sm mb-3 text-center">
                        <h6>التصنيف</h6>
                        <span class="badge badge-secondary"><?= htmlspecialchars($order['category_name']) ?></span>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="p-3 bg-light rounded shadow-sm mb-3 text-center">
                        <h6>سعر الخدمة</h6>
                        <p class="text-primary font-weight-bold mb-0">
                            <?= number_format($order['service_price'], 2) ?>شيكل
                        </p>
                    </div>
                </div>

            </div>

            <h6 class="mt-3">وصف الخدمة</h6>
            <div class="border-left pl-3 text-muted small">
                <?= nl2br(htmlspecialchars($order['service_description'])) ?>
            </div>
        </div>
    </div>

    <!-- إجراءات المدير -->
    <div class="card shadow mb-4 border-0 rounded-lg">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-cogs"></i> إجراءات المدير</h5>
        </div>

        <div class="card-body">
            <form action="actions/update_order_status.php" method="POST" class="form-inline">

                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">

                <label for="new_status" class="mr-2">تغيير الحالة:</label>

                <select name="new_status" id="new_status" class="form-control mr-3">
                    <option value="pending" <?= $order['status']=='pending'?'selected':'' ?>>قيد الانتظار</option>
                    <option value="processing" <?= $order['status']=='processing'?'selected':'' ?>>قيد التنفيذ</option>
                    <option value="completed" <?= $order['status']=='completed'?'selected':'' ?>>مكتملة</option>
                    <option value="cancelled" <?= $order['status']=='cancelled'?'selected':'' ?>>ملغاة</option>
                </select>

                <button class="btn btn-danger">
                    <i class="fas fa-sync-alt"></i> تحديث
                </button>

            </form>
        </div>
    </div>

</div>

<?php include_once '../includes/footer.php'; ?>
