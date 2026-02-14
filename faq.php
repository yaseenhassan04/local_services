<?php

// 1. تضمين الملفات الأساسية
include_once __DIR__ . '/includes/header.php'; 
?>

<div class="site-main container">

    <header class="page-header" style="text-align: center; margin-bottom: 50px; padding: 20px 0;">
        <h1 style="font-size: 38px; color: var(--primary-dark); font-weight: 700; border-bottom: 3px solid var(--primary); display: inline-block; padding-bottom: 5px;">
            الأسئلة الشائعة (FAQ)
        </h1>
        <p class="muted" style="font-size: 18px; margin-top: 10px;">
            إجابات وافية لأكثر الاستفسارات شيوعاً حول منصة خدماتي.
        </p>
    </header>

    <section class="faq-content" style="max-width: 900px; margin: 0 auto 50px;">
        
        <h2 style="color: var(--accent); margin-top: 40px; margin-bottom: 20px; border-bottom: 1px solid var(--border-light); padding-bottom: 10px;">
            أسئلة عامة حول المنصة
        </h2>

        <div class="faq-item" style="margin-bottom: 15px; background: var(--card); border-radius: 8px; box-shadow: var(--shadow-soft);">
            <button class="faq-question" 
                    style="width: 100%; text-align: right; background: none; border: none; padding: 18px 25px; font-size: 18px; font-weight: 600; color: var(--text); cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                كيف يمكنني التسجيل كمزود خدمة؟
                <span class="icon" style="font-size: 20px; color: var(--primary);">+</span>
            </button>
            <div class="faq-answer" style="padding: 0 25px 20px; font-size: 16px; color: var(--muted); border-top: 1px solid #eee;"> 
                <p style="margin-top: 10px;">
                    يمكنك التسجيل كمزود خدمة بالضغط على زر "انضم كمزود خدمة" في الصفحة الرئيسية، ثم ملء نموذج التسجيل وتقديم المستندات المطلوبة للتحقق من هويتك وخبرتك.
                </p>
            </div>
        </div>

        <div class="faq-item" style="margin-bottom: 15px; background: var(--card); border-radius: 8px; box-shadow: var(--shadow-soft);">
            <button class="faq-question" 
                    style="width: 100%; text-align: right; background: none; border: none; padding: 18px 25px; font-size: 18px; font-weight: 600; color: var(--text); cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                ما هي رسوم استخدام منصة خدماتي؟
                <span class="icon" style="font-size: 20px; color: var(--primary);">+</span>
            </button>
            <div class="faq-answer" style="padding: 0 25px 20px; font-size: 16px; color: var(--muted); border-top: 1px solid #eee;">
                <p style="margin-top: 10px;">
                    التسجيل وتصفح الخدمات مجاني بالكامل. يتم خصم عمولة بسيطة فقط عند إتمام أي عملية بيع ناجحة لمزود الخدمة. تفاصيل الرسوم موضحة في صفحة الشروط والأحكام.
                </p>
            </div>
        </div>
        
        <h2 style="color: var(--accent); margin-top: 40px; margin-bottom: 20px; border-bottom: 1px solid var(--border-light); padding-bottom: 10px;">
            أسئلة حول الأمان والدفع
        </h2>
        
        <div class="faq-item" style="margin-bottom: 15px; background: var(--card); border-radius: 8px; box-shadow: var(--shadow-soft);">
            <button class="faq-question" 
                    style="width: 100%; text-align: right; background: none; border: none; padding: 18px 25px; font-size: 18px; font-weight: 600; color: var(--text); cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                هل معلومات الدفع آمنة؟
                <span class="icon" style="font-size: 20px; color: var(--primary);">+</span>
            </button>
            <div class="faq-answer" style="padding: 0 25px 20px; font-size: 16px; color: var(--muted); border-top: 1px solid #eee;">
                <p style="margin-top: 10px;">
                    نعم، نستخدم أحدث تقنيات التشفير (SSL) لضمان أمان وحماية جميع بيانات الدفع والمعلومات الشخصية لعملائنا ومزودي الخدمات.
                </p>
            </div>
        </div>
        
    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const faqQuestions = document.querySelectorAll('.faq-question');

    faqQuestions.forEach(question => {
        question.addEventListener('click', () => {
            const answer = question.nextElementSibling;
            
            // تبديل فئة Active و Open
            question.classList.toggle('active');
            answer.classList.toggle('open');
            
            // لفتح وإغلاق الإجابة
            if (answer.classList.contains('open')) {
                answer.style.maxHeight = answer.scrollHeight + "px"; // تعيين الارتفاع الفعلي
                question.querySelector('.icon').textContent = '−'; // تغيير + إلى -
            } else {
                answer.style.maxHeight = '0';
                question.querySelector('.icon').textContent = '+'; // إعادة +
            }
        });
    });
});
</script> <?php 
// 7. تضمين ملف التذييل
include_once __DIR__ . '/includes/footer.php';
?>