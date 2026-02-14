<?php

include_once __DIR__ . '/includes/header.php'; 
?>

<div class="site-main container">

    <header class="page-header" style="text-align: center; margin-bottom: 50px; padding: 20px 0;">
        <h1 style="font-size: 38px; color: var(--primary-dark); font-weight: 700; border-bottom: 3px solid var(--accent); display: inline-block; padding-bottom: 5px;">
            قصتنا ورؤيتنا
        </h1>
        <p class="muted" style="font-size: 18px; margin-top: 10px;">
            من نحن؟ وما الهدف من تأسيس منصة خدماتي؟
        </p>
    </header>

    <section class="vision-mission" style="padding: 40px 0; background: var(--card); border-radius: 12px; box-shadow: var(--shadow-soft);">
        <div style="max-width: 900px; margin: 0 auto; padding: 0 20px;">
            <h2 style="color: var(--primary); text-align: center; margin-bottom: 30px;">
                بدايتنا: جسر الثقة في عالم الخدمات
            </h2>
            <p style="font-size: 17px; line-height: 1.8; text-align: justify;">
                تأسست منصة **خدماتي** انطلاقاً من إيماننا العميق بضرورة إيجاد نقطة التقاء موثوقة وعالية الجودة تجمع بين مزودي الخدمات المحليين والعملاء الباحثين عن الاحترافية. لاحظنا وجود فجوة في السوق المحلي، حيث كان من الصعب على الأفراد والشركات العثور على خبراء معتمدين بسرعة وبدون عناء.
            </p>
            <p style="font-size: 17px; line-height: 1.8; text-align: justify; margin-top: 15px;">
                من هنا، وُلدت منصتنا لتكون الحل الشامل الذي يضمن شفافية التعامل، جودة الأداء، والأمان لكلا الطرفين. نحن لا نقدم خدمات؛ بل نبني شراكات قائمة على الاحترام والخبرة المتبادلة.
            </p>
            
            <h3 style="color: var(--accent); margin-top: 40px; text-align: center;">رؤيتنا للمستقبل</h3>
            <p style="font-size: 17px; line-height: 1.8; text-align: center;">
                أن نكون المنصة الرقمية الرائدة والأكثر تأثيراً في ربط المجتمع المحلي بالخدمات الاحترافية، مما يساهم في نمو الاقتصاد المحلي ودعم المستقلين.
            </p>
        </div>
    </section>
    
    <section class="core-values" style="padding: 60px 0;">
        <h2 style="text-align: center; color: var(--primary-dark); margin-bottom: 40px;">قيمنا الأساسية</h2>
        
        <div class="features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px;">
            
            <div class="value-card" style="text-align: center; padding: 25px; background: #fff; border-radius: 10px; box-shadow: var(--shadow-soft); border-bottom: 4px solid var(--primary);">
                <div class="feature-icon" style="font-size: 35px; color: var(--primary); margin-bottom: 15px;">🤝</div>
                <h3 style="margin-top: 0; font-size: 20px;">الثقة والشفافية</h3>
                <p class="muted">نلتزم بأعلى معايير الشفافية في عرض الخدمات وتقييم المزودين لضمان بناء الثقة.</p>
            </div>
            
            <div class="value-card" style="text-align: center; padding: 25px; background: #fff; border-radius: 10px; box-shadow: var(--shadow-soft); border-bottom: 4px solid var(--primary);">
                <div class="feature-icon" style="font-size: 35px; color: var(--primary); margin-bottom: 15px;">🥇</div>
                <h3 style="margin-top: 0; font-size: 20px;">جودة لا تُضاهى</h3>
                <p class="muted">نركز على الكفاءة والاحترافية، حيث يتم التحقق من خبرات مقدمي الخدمات قبل الانضمام.</p>
            </div>
            
            <div class="value-card" style="text-align: center; padding: 25px; background: #fff; border-radius: 10px; box-shadow: var(--shadow-soft); border-bottom: 4px solid var(--primary);">
                <div class="feature-icon" style="font-size: 35px; color: var(--primary); margin-bottom: 15px;">⚙️</div>
                <h3 style="margin-top: 0; font-size: 20px;">الابتكار والكفاءة</h3>
                <p class="muted">نعمل باستمرار على تطوير أدوات المنصة لتكون عملية طلب الخدمة سهلة وسريعة للغاية.</p>
            </div>
            
        </div>
    </section>

    <section class="cta-section" style="padding: 50px; text-align: center; background: #eaf3ff; border-radius: 12px; margin-top: 30px; margin-bottom: 40px;">
        <h2 style="color: var(--primary-dark); font-size: 30px;">انضم إلى مجتمع خدماتي اليوم!</h2>
        <p style="font-size: 18px; color: var(--muted); margin-bottom: 30px;">
            سواء كنت تبحث عن خدمة ممتازة أو تسعى لتقديم خبرتك، نحن المكان المناسب لك.
        </p>
        <a href="/local_services/register.php" class="btn" style="background: var(--accent); padding: 14px 35px; font-size: 18px; margin: 0 10px;">
            ابدأ الآن
        </a>
        <a href="/local_services/services.php" class="btn" style="background: var(--primary-dark); padding: 14px 35px; font-size: 18px; margin: 0 10px;">
            تصفح الخدمات
        </a>
    </section>

</div>

<?php 
include_once __DIR__ . '/includes/footer.php';
?>