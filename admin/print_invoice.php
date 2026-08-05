<?php
// admin/print_invoice.php
// فاتورة رسمية للطباعة - تُفتح من view_order.php

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

check_login('admin');

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    die("رقم طلب غير صالح.");
}

$order_id = (int)$_GET['id'];

try {
    $sql = "SELECT
                o.*,
                s.title        AS service_title,
                s.description  AS service_description,
                s.price        AS service_price,
                c.name         AS category_name,
                u_client.full_name  AS client_name,
                u_client.email      AS client_email,
                u_client.phone      AS client_phone,
                u_provider.full_name AS provider_name,
                u_provider.email     AS provider_email,
                u_provider.phone     AS provider_phone
            FROM orders o
            JOIN services s        ON o.service_id  = s.id
            JOIN users u_client    ON o.client_id   = u_client.id
            JOIN users u_provider  ON o.provider_id = u_provider.id
            LEFT JOIN categories c ON s.category_id = c.id
            WHERE o.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) { die("الطلب غير موجود."); }
} catch (PDOException $e) {
    die("خطأ: " . $e->getMessage());
}

// ── حسابات مالية ────────────────────────────────────
$ADMIN_RATE        = 0.15;
$order_amount      = (float)($order['amount'] ?? 0);
$admin_commission  = $order_amount * $ADMIN_RATE;
$provider_earning  = $order_amount - $admin_commission;
$vat_rate          = 0.00; // غيّر لو في ضريبة
$vat_amount        = $order_amount * $vat_rate;
$total_with_vat    = $order_amount + $vat_amount;

// ── حالة الطلب بالعربي ──────────────────────────────
$status_map = [
    'pending'     => ['label' => 'قيد الانتظار',  'color' => '#e2a03f'],
    'processing'  => ['label' => 'قيد المعالجة', 'color' => '#805dca'],
    'in_progress' => ['label' => 'قيد التنفيذ',  'color' => '#2196f3'],
    'completed'   => ['label' => 'مكتمل',         'color' => '#00ab55'],
    'cancelled'   => ['label' => 'ملغي',           'color' => '#e7515a'],
];
$status_info = $status_map[$order['status']] ?? ['label' => $order['status'], 'color' => '#888'];

// ── رقم الفاتورة ─────────────────────────────────────
$invoice_number = 'INV-' . str_pad($order_id, 5, '0', STR_PAD_LEFT);
$invoice_date   = date('Y/m/d');
$invoice_time   = date('H:i');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>فاتورة <?= $invoice_number ?></title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════
   INVOICE — print_invoice.php
   تصميم فاتورة رسمية مناسبة للطباعة
═══════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --primary:  #4361ee;
    --success:  #00ab55;
    --warning:  #e2a03f;
    --danger:   #e7515a;
    --dark:     #1a2332;
    --mid:      #374151;
    --muted:    #6b7280;
    --light:    #f8fafc;
    --border:   #e5e7eb;
    --accent:   #eef2ff;
}

body {
    font-family: 'Tajawal', sans-serif;
    background: #f0f2f5;
    color: var(--dark);
    direction: rtl;
    font-size: 14px;
    line-height: 1.6;
    padding: 30px 20px;
}

/* ── بطاقة الفاتورة ── */
.invoice-wrap {
    max-width: 820px;
    margin: 0 auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 30px rgba(0,0,0,.12);
    overflow: hidden;
}

/* ── الترويسة ── */
.inv-header {
    background: linear-gradient(135deg, #1a2332 0%, #2d3f5e 100%);
    color: #fff;
    padding: 36px 40px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
}
.inv-brand {
    display: flex;
    align-items: center;
    gap: 12px;
}
.inv-logo {
    width: 52px; height: 52px;
    background: var(--primary);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; font-weight: 900; color: #fff;
    letter-spacing: -1px;
}
.inv-brand-name { font-size: 22px; font-weight: 900; letter-spacing: .5px; }
.inv-brand-sub  { font-size: 12px; color: rgba(255,255,255,.6); margin-top: 2px; }

.inv-meta { text-align: left; }
.inv-label {
    font-size: 11px; font-weight: 700;
    color: rgba(255,255,255,.5);
    text-transform: uppercase; letter-spacing: 1px;
    margin-bottom: 3px;
}
.inv-number { font-size: 26px; font-weight: 900; color: #fff; }
.inv-date   { font-size: 13px; color: rgba(255,255,255,.7); margin-top: 4px; }

/* ── شريط الحالة ── */
.inv-status-bar {
    background: var(--accent);
    border-bottom: 1px solid var(--border);
    padding: 12px 40px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.inv-status-item { display: flex; align-items: center; gap: 7px; font-size: 12px; color: var(--muted); }
.inv-status-item strong { color: var(--dark); font-weight: 700; }
.status-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 12px; border-radius: 20px;
    font-size: 12px; font-weight: 700;
    border: 1.5px solid currentColor;
}
.status-pill::before {
    content: ''; width: 7px; height: 7px;
    border-radius: 50%; background: currentColor;
}

/* ── المحتوى الرئيسي ── */
.inv-body { padding: 36px 40px; }

/* ── بيانات الأطراف ── */
.parties-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 32px;
}
.party-box {
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: 18px;
    background: #fdfdff;
}
.party-box.issuer { border-color: var(--primary); background: var(--accent); }
.party-label {
    font-size: 10px; font-weight: 800;
    text-transform: uppercase; letter-spacing: 1px;
    color: var(--muted); margin-bottom: 10px;
    display: flex; align-items: center; gap: 5px;
}
.party-label span { width: 20px; height: 2px; background: currentColor; display: inline-block; }
.party-name  { font-size: 15px; font-weight: 800; color: var(--dark); margin-bottom: 6px; }
.party-info  { font-size: 12px; color: var(--muted); line-height: 1.8; }
.party-info b { color: var(--mid); font-weight: 600; }

/* ── جدول الخدمة ── */
.inv-section-title {
    font-size: 13px; font-weight: 800;
    color: var(--primary);
    text-transform: uppercase; letter-spacing: .5px;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--accent);
    display: flex; align-items: center; gap: 7px;
}
.inv-section-title::before {
    content: '';
    width: 4px; height: 16px;
    background: var(--primary); border-radius: 2px;
    display: inline-block;
}

.service-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 24px;
    font-size: 13px;
}
.service-table thead th {
    background: var(--dark);
    color: #fff;
    padding: 11px 14px;
    text-align: right;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .5px;
}
.service-table thead th:first-child { border-radius: 0 6px 0 0; }
.service-table thead th:last-child  { border-radius: 6px 0 0 0; text-align: left; }
.service-table tbody td {
    padding: 13px 14px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    color: var(--mid);
}
.service-table tbody tr:last-child td { border-bottom: none; }
.service-table tbody tr:hover { background: #fafbff; }
.service-table td:last-child { text-align: left; font-weight: 700; }
.service-number {
    width: 28px; height: 28px; border-radius: 50%;
    background: var(--accent); color: var(--primary);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 800;
}

/* ── ملخص الحسابات ── */
.totals-wrap {
    display: flex;
    justify-content: flex-start;
    gap: 24px;
    margin-bottom: 28px;
    flex-wrap: wrap;
}
.totals-box {
    min-width: 280px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
}
.totals-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}
.totals-row:last-child { border-bottom: none; }
.totals-row .lbl { color: var(--muted); font-weight: 600; }
.totals-row .val { font-weight: 700; color: var(--dark); }
.totals-total {
    background: var(--dark);
    color: #fff;
    padding: 14px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.totals-total .lbl { font-size: 13px; font-weight: 700; color: rgba(255,255,255,.8); }
.totals-total .val { font-size: 22px; font-weight: 900; color: #fff; }

/* ── توزيع الأرباح ── */
.distribution-box {
    background: linear-gradient(135deg, #f0fdf4, #eff6ff);
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: 18px;
    margin-bottom: 28px;
}
.dist-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px dashed var(--border);
    font-size: 13px;
}
.dist-row:last-child { border-bottom: none; padding-bottom: 0; }
.dist-label { display: flex; align-items: center; gap: 8px; color: var(--mid); font-weight: 600; }
.dist-badge {
    font-size: 10px; font-weight: 800;
    padding: 2px 7px; border-radius: 5px;
}
.dist-val { font-weight: 800; font-size: 14px; }

/* ── ملاحظات وشروط ── */
.inv-notes {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
    padding: 14px 16px;
    font-size: 12px;
    color: #92400e;
    margin-bottom: 24px;
    line-height: 1.8;
}
.inv-notes strong { display: block; margin-bottom: 5px; font-size: 13px; }

.inv-terms {
    font-size: 11px;
    color: var(--muted);
    line-height: 1.8;
    padding-top: 16px;
    border-top: 1px dashed var(--border);
}
.inv-terms ol { padding-right: 18px; }
.inv-terms ol li { margin-bottom: 3px; }

/* ── تذييل ── */
.inv-footer {
    background: var(--light);
    border-top: 1px solid var(--border);
    padding: 18px 40px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.inv-footer .brand-sm { font-size: 14px; font-weight: 800; color: var(--primary); }
.inv-footer .generated { font-size: 11px; color: var(--muted); }
.inv-footer .seal {
    width: 54px; height: 54px; border-radius: 50%;
    border: 2.5px dashed var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 9px; font-weight: 800; color: var(--primary);
    text-align: center; line-height: 1.3; padding: 4px;
}

/* ── أزرار التحكم (للشاشة فقط، تختفي عند الطباعة) ── */
.print-actions {
    max-width: 820px;
    margin: 20px auto 0;
    display: flex;
    gap: 10px;
    justify-content: center;
}
.btn-print-now {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 28px; border-radius: 8px;
    background: var(--primary); color: #fff;
    font-family: 'Tajawal', sans-serif;
    font-size: 14px; font-weight: 700;
    border: none; cursor: pointer;
    transition: all .2s;
}
.btn-print-now:hover { background: #3451d1; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(67,97,238,.4); }
.btn-close {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 20px; border-radius: 8px;
    background: #fff; color: var(--mid);
    font-family: 'Tajawal', sans-serif;
    font-size: 14px; font-weight: 700;
    border: 1.5px solid var(--border); cursor: pointer;
    transition: all .2s; text-decoration: none;
}
.btn-close:hover { border-color: var(--muted); }

/* ═══ PRINT STYLES ═══════════════════════════════════ */
@media print {
    body { background: #fff; padding: 0; font-size: 13px; }
    .print-actions { display: none !important; }
    .invoice-wrap {
        box-shadow: none;
        border-radius: 0;
        max-width: 100%;
    }
    .inv-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .inv-status-bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .service-table thead { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .totals-total { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @page {
        margin: 15mm 12mm;
        size: A4 portrait;
    }
}
</style>
</head>
<body>

<!-- ════════════════════════════════════════
     أزرار التحكم (شاشة فقط)
════════════════════════════════════════ -->
<div class="print-actions">
    <button class="btn-print-now" onclick="window.print()">
        🖨️ طباعة الفاتورة
    </button>
    <a href="javascript:window.close()" class="btn-close">
        ✕ إغلاق
    </a>
</div>

<!-- ════════════════════════════════════════
     الفاتورة
════════════════════════════════════════ -->
<div class="invoice-wrap">

    <!-- ── الترويسة ── -->
    <div class="inv-header">
        <div class="inv-brand">
            <div class="inv-logo">خ</div>
            <div>
                <div class="inv-brand-name">منصة خدماتي</div>
                <div class="inv-brand-sub">منصة الخدمات المحلية والأعمال الحرة</div>
                <div class="inv-brand-sub" style="margin-top:4px;">
                    📧 info@khadamati.ps &nbsp;|&nbsp; 🌐 khadamati.ps
                </div>
            </div>
        </div>
        <div class="inv-meta">
            <div class="inv-label">فاتورة رقم</div>
            <div class="inv-number"><?= $invoice_number ?></div>
            <div class="inv-date">
                📅 تاريخ الإصدار: <?= $invoice_date ?><br>
                🕐 الوقت: <?= $invoice_time ?>
            </div>
        </div>
    </div>

    <!-- ── شريط الحالة ── -->
    <div class="inv-status-bar">
        <div class="inv-status-item">
            🔖 رقم الطلب:
            <strong>#<?= $order_id ?></strong>
        </div>
        <div class="inv-status-item">
            📂 التصنيف:
            <strong><?= htmlspecialchars($order['category_name'] ?: 'غير مصنف') ?></strong>
        </div>
        <div class="inv-status-item">
            حالة الطلب:
            <span class="status-pill" style="color: <?= $status_info['color'] ?>;">
                <?= $status_info['label'] ?>
            </span>
        </div>
        <div class="inv-status-item">
            💳 حالة الدفع:
            <strong><?= htmlspecialchars($order['payment_status'] ?? 'غير محدد') ?></strong>
        </div>
    </div>

    <!-- ── المحتوى ── -->
    <div class="inv-body">

        <!-- بيانات الأطراف -->
        <div class="parties-grid">
            <!-- المُصدِر -->
            <div class="party-box issuer">
                <div class="party-label">
                    <span></span> صادرة عن
                </div>
                <div class="party-name">منصة خدماتي</div>
                <div class="party-info">
                    <b>النوع:</b> منصة وساطة خدمات محلية<br>
                    <b>البريد:</b> info@khadamati.ps<br>
                    <b>الموقع:</b> khadamati.ps<br>
                    <b>المنطقة:</b> فلسطين
                </div>
            </div>

            <!-- العميل -->
            <div class="party-box">
                <div class="party-label">
                    <span></span> صادرة إلى (العميل)
                </div>
                <div class="party-name"><?= htmlspecialchars($order['client_name']) ?></div>
                <div class="party-info">
                    <b>البريد:</b> <?= htmlspecialchars($order['client_email']) ?><br>
                    <?php if (!empty($order['client_phone'])): ?>
                    <b>الهاتف:</b> <?= htmlspecialchars($order['client_phone']) ?><br>
                    <?php endif; ?>
                    <b>تاريخ الطلب:</b> <?= htmlspecialchars($order['order_date'] ?? '—') ?>
                </div>
            </div>
        </div>

        <!-- تفاصيل الخدمة -->
        <div class="inv-section-title">تفاصيل الخدمة المطلوبة</div>

        <table class="service-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>اسم الخدمة</th>
                    <th>مزود الخدمة</th>
                    <th>التصنيف</th>
                    <th style="text-align:left;">المبلغ (₪)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="service-number">1</span></td>
                    <td>
                        <div style="font-weight:700;color:#1a2332;margin-bottom:3px;">
                            <?= htmlspecialchars($order['service_title']) ?>
                        </div>
                        <div style="font-size:11px;color:#6b7280;line-height:1.5;">
                            <?= mb_substr(htmlspecialchars($order['service_description']), 0, 100) ?>
                            <?= mb_strlen($order['service_description']) > 100 ? '...' : '' ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($order['provider_name']) ?></div>
                        <div style="font-size:11px;color:#6b7280;"><?= htmlspecialchars($order['provider_email']) ?></div>
                    </td>
                    <td>
                        <span style="background:#eef2ff;color:#4361ee;padding:3px 9px;border-radius:12px;font-size:11px;font-weight:700;">
                            <?= htmlspecialchars($order['category_name'] ?: '—') ?>
                        </span>
                    </td>
                    <td style="font-size:16px;color:#00ab55;font-weight:900;">
                        <?= number_format($order_amount, 2) ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- ملخص المبالغ -->
        <div class="inv-section-title">ملخص المبالغ</div>

        <div class="totals-wrap">
            <div class="totals-box">
                <div class="totals-row">
                    <span class="lbl">سعر الخدمة الأصلي</span>
                    <span class="val"><?= number_format((float)$order['service_price'], 2) ?> ₪</span>
                </div>
                <div class="totals-row">
                    <span class="lbl">المبلغ المدفوع</span>
                    <span class="val" style="color:var(--success);"><?= number_format($order_amount, 2) ?> ₪</span>
                </div>
                <?php if ($vat_rate > 0): ?>
                <div class="totals-row">
                    <span class="lbl">ضريبة القيمة المضافة (<?= ($vat_rate*100) ?>%)</span>
                    <span class="val"><?= number_format($vat_amount, 2) ?> ₪</span>
                </div>
                <?php endif; ?>
                <div class="totals-total">
                    <span class="lbl">💰 إجمالي الفاتورة</span>
                    <span class="val"><?= number_format($total_with_vat, 2) ?> ₪</span>
                </div>
            </div>
        </div>

        <!-- توزيع الأرباح -->
        <div class="inv-section-title">توزيع الأرباح والعمولة</div>

        <div class="distribution-box">
            <div class="dist-row">
                <div class="dist-label">
                    🏦 عمولة المنصة
                    <span class="dist-badge" style="background:#dcfce7;color:#16a34a;">15%</span>
                </div>
                <div class="dist-val" style="color:var(--success);">
                    <?= number_format($admin_commission, 2) ?> ₪
                </div>
            </div>
            <div class="dist-row">
                <div class="dist-label">
                    👷 صافي مزود الخدمة
                    <span class="dist-badge" style="background:#fef3c7;color:#d97706;">85%</span>
                </div>
                <div class="dist-val" style="color:var(--warning);">
                    <?= number_format($provider_earning, 2) ?> ₪
                    <div style="font-size:11px;color:#6b7280;font-weight:500;margin-top:2px;">
                        المستحق لـ: <?= htmlspecialchars($order['provider_name']) ?>
                    </div>
                </div>
            </div>
            <div class="dist-row" style="padding-top:10px;">
                <div class="dist-label" style="font-weight:800;color:#1a2332;">
                    💵 إجمالي المبلغ
                </div>
                <div class="dist-val" style="color:var(--primary);font-size:16px;">
                    <?= number_format($order_amount, 2) ?> ₪
                </div>
            </div>
        </div>

        <!-- ملاحظات -->
        <?php if (!empty($order['payment_proof'])): ?>
        <div class="inv-notes">
            <strong>📎 إثبات الدفع:</strong>
            <?= htmlspecialchars($order['payment_proof']) ?>
        </div>
        <?php endif; ?>

        <!-- الشروط والأحكام -->
        <div class="inv-terms">
            <strong style="color:var(--mid);font-size:12px;">الشروط والأحكام:</strong>
            <ol>
                <li>هذه الفاتورة وثيقة رسمية تثبت تنفيذ المعاملة بين الطرفين عبر منصة خدماتي.</li>
                <li>تحتسب عمولة المنصة بنسبة 15% من قيمة كل طلب مكتمل.</li>
                <li>يُعدّ هذا الطلب ملزماً للطرفين وفق الشروط المتفق عليها عند التسجيل.</li>
                <li>لأي استفسار يرجى التواصل عبر البريد: info@khadamati.ps</li>
            </ol>
        </div>

    </div>

    <!-- ── التذييل ── -->
    <div class="inv-footer">
        <div>
            <div class="brand-sm">منصة خدماتي 🇵🇸</div>
            <div class="generated">
                تم إصدار هذه الفاتورة بتاريخ <?= $invoice_date ?> الساعة <?= $invoice_time ?>
            </div>
        </div>
        <div style="font-size:11px;color:#6b7280;text-align:center;">
            هذه وثيقة إلكترونية رسمية<br>
            لا تحتاج إلى توقيع أو ختم
        </div>
        <div class="seal">
            وثيقة<br>رسمية<br>✓
        </div>
    </div>

</div><!-- /invoice-wrap -->

<script>
// طباعة تلقائية عند الفتح (اختياري - شيله لو ما بدك)
// window.onload = () => window.print();
</script>
</body>
</html>