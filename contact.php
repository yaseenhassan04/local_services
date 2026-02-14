<?php

// 1. تضمين الملفات الأساسية
include_once __DIR__ . '/includes/header.php'; 
?>

<div class="site-main container">

    <?php
    if (isset($_GET['status'])) {
        $status = $_GET['status'];
        $default_msg = ($status == 'success') 
            ? 'تم استلام رسالتك بنجاح. سنرد عليك في أقرب وقت!' 
            : 'عذراً، حدث خطأ أثناء إرسال رسالتك. يرجى التأكد من البيانات والمحاولة مجدداً.';
            
        $message = $_GET['msg'] ?? $default_msg;
        
        $style = ($status == 'success') 
            ? 'background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;' 
            : 'background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;';
            
        echo '<div style="padding: 15px; margin-bottom: 20px; border-radius: 8px; text-align: center; ' . $style . '">';
        echo htmlspecialchars(urldecode($message));
        echo '</div>';
    }
    ?>
    <header class="page-header" style="text-align: center; margin-bottom: 50px; padding: 20px 0;">
        <h1 style="font-size: 38px; color: var(--primary-dark); font-weight: 700; border-bottom: 3px solid var(--accent); display: inline-block; padding-bottom: 5px;">
            تواصل معنا 
        </h1>
        <p class="muted" style="font-size: 18px; margin-top: 10px;">
            نحن هنا للإجابة على جميع استفساراتك وتقديم الدعم. لا تتردد في التواصل معنا.
        </p>
    </header>

    <section class="contact-grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 40px; margin-bottom: 50px;">

        <div class="contact-info" style="background: var(--primary-dark); color: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--shadow-strong);">
            <h2 style="font-size: 24px; margin-bottom: 25px; border-bottom: 1px solid rgba(255, 255, 255, 0.3); padding-bottom: 10px;">
                معلومات التواصل الأساسية
            </h2>
            
            <div class="info-item" style="margin-bottom: 20px;">
                <h4 style="margin-bottom: 5px; color: var(--accent);">📧 البريد الإلكتروني</h4>
                <p style="opacity: 0.9;">info@khadamati.com</p>
            </div>

            <div class="info-item" style="margin-bottom: 20px;">
                <h4 style="margin-bottom: 5px; color: var(--accent);">📞 الهاتف</h4>
                <p style="opacity: 0.9;">+972 59X XXX XXX</p>
            </div>

            <div class="info-item" style="margin-bottom: 20px;">
                <h4 style="margin-bottom: 5px; color: var(--accent);">📍 العنوان</h4>
                <p style="opacity: 0.9;">مدينة غزة - مجمع العمال، الطابق الثالث</p>
            </div>
            
            <div class="info-item">
                <h4 style="margin-bottom: 5px; color: var(--accent);">🕒 أوقات العمل</h4>
                <p style="opacity: 0.9;">السبت - الخميس: 9:00 صباحاً - 5:00 مساءً</p>
            </div>
        </div>

        <div class="contact-form-wrapper" style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: var(--shadow-soft);">
            <h2 style="font-size: 24px; color: var(--primary-dark); margin-bottom: 25px;">أرسل لنا رسالة</h2>
            
            <form action="/local_services/process_contact.php" method="POST" style="display: grid; gap: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <input type="text" name="name" placeholder="الاسم الكامل *" required 
                           style="padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;">
                    <input type="email" name="email" placeholder="البريد الإلكتروني *" required
                           style="padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;">
                </div>
                
                <input type="text" name="subject" placeholder="الموضوع"
                       style="padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;">
                
                <textarea name="message" placeholder="رسالتك... *" required rows="6"
                          style="padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px; resize: vertical;"></textarea>
                
                <button type="submit" class="btn" style="background: var(--accent); color: #fff; padding: 14px; font-size: 18px; border: none; border-radius: 6px; cursor: pointer;">
                    إرسال الرسالة
                </button>
            </form>
        </div>

    </section>

    <section class="map-section" style="margin-bottom: 60px; border-radius: 12px; overflow: hidden; box-shadow: var(--shadow-strong);">
        <h2 style="text-align: center; color: var(--primary-dark); margin-bottom: 20px;">موقعنا على الخريطة</h2>
        
        <div class="map-embed" style="height: 450px; width: 100%;">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3404.707736637497!2d34.46328368484964!3d31.50029965313988!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x14030635f7957973%3A0x673c6838a37f594!2sGaza%20City!5e0!3m2!1sen!2sps!4v1628173456789!5m2!1sen!2sps" 
                    width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
        </div>
    </section>

</div>

<?php 
include_once __DIR__ . '/includes/footer.php';
?>