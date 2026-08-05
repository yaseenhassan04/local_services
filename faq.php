<?php
// 1. تضمين الملفات الأساسية
include_once __DIR__ . '/includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
<link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">

<style>
    /* تمكين المتغيرات لتطابق الهوية المستقبلية */
    :root {
        --primary: #00d4ff;
        --primary-dark: #0099cc;
        --secondary: #ff006e;
        --accent: #00f5ff;
        --dark-bg: #0a0e27;
        --dark-card: #1a1f3a;
        --dark-border: #2d3561;
        --text-primary: #ffffff;
        --text-secondary: #b0b8d4;
        --gradient-accent: linear-gradient(135deg, #00d4ff 0%, #ff006e 100%);
        --shadow: 0 8px 32px rgba(0, 212, 255, 0.15);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* تهيئة الخلفية الديناميكية المضيئة لتطابق الصفحات الأخرى */
    body {
        background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 50%, #0f1f35 100%) !important;
        color: var(--text-primary) !important;
        position: relative;
    }

    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 200%;
        height: 200%;
        background:
            radial-gradient(circle at 30% 30%, rgba(0, 212, 255, 0.12) 0%, transparent 50%),
            radial-gradient(circle at 70% 70%, rgba(255, 0, 110, 0.08) 0%, transparent 50%);
        animation: floatFAQ 25s ease-in-out infinite;
        pointer-events: none;
        z-index: 0;
    }

    @keyframes floatFAQ {

        0%,
        100% {
            transform: translate(0, 0);
        }

        50% {
            transform: translate(-30px, 30px);
        }
    }

    .site-main {
        position: relative;
        z-index: 1;
        padding-top: 40px;
    }

    /* العناوين والتأثير النيوني */
    .page-title {
        font-size: clamp(32px, 5vw, 46px) !important;
        font-weight: 900 !important;
        background: var(--gradient-accent);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        display: inline-block;
        margin-bottom: 15px;
    }

    .section-title {
        font-size: 22px !important;
        font-weight: 800 !important;
        color: var(--accent) !important;
        margin-top: 50px !important;
        margin-bottom: 25px !important;
        border-bottom: 1px solid var(--dark-border) !important;
        padding-bottom: 12px !important;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* كروت الأسئلة بتأثير الزجاج الشفاف Glassmorphism */
    .faq-item {
        background: rgba(26, 31, 58, 0.6) !important;
        backdrop-filter: blur(12px);
        border: 1px solid var(--dark-border) !important;
        border-radius: 12px !important;
        margin-bottom: 20px !important;
        overflow: hidden;
        transition: var(--transition) !important;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
    }

    .faq-item:hover {
        border-color: var(--primary) !important;
        box-shadow: var(--shadow) !important;
        transform: translateY(-2px);
    }

    /* أزرار الأسئلة */
    .faq-question {
        color: var(--text-primary) !important;
        padding: 20px 25px !important;
        font-size: 17px !important;
        font-weight: 700 !important;
        transition: var(--transition) !important;
    }

    .faq-question:hover {
        background: rgba(0, 212, 255, 0.05) !important;
        color: var(--primary) !important;
    }

    .faq-question .icon {
        color: var(--primary) !important;
        font-weight: bold;
        transition: var(--transition);
        background: rgba(0, 212, 255, 0.1);
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }

    .faq-question.active .icon {
        background: rgba(255, 0, 110, 0.2);
        color: var(--secondary) !important;
        transform: rotate(180deg);
    }

    /* منطقة الإجابات المنسدلة */
    .faq-answer {
        border-top: 1px solid transparent !important;
        max-height: 0;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        background: rgba(10, 14, 39, 0.4);
    }

    .faq-answer p {
        padding: 20px 25px !important;
        font-size: 15px !important;
        color: var(--text-secondary) !important;
        line-height: 1.8 !important;
        margin: 0 !important;
    }

    /* عند فتح الإجابة يتم إبراز خط الفصل علوياً */
    .faq-question.active+.faq-answer {
        border-top-color: var(--dark-border) !important;
    }
</style>

<div class="site-main container">

    <header class="page-header" style="text-align: center; margin-bottom: 60px; padding: 20px 0;" data-aos="fade-down">
        <h1 class="page-title">الأسئلة الشائعة (FAQ) 🧠</h1>
        <p style="font-size: 18px; margin-top: 10px; color: var(--text-secondary);">
            إجابات وافية لأكثر الاستفسارات شيوعاً حول منصة خدماتي المستقبلية.
        </p>
    </header>

    <section class="faq-content" style="max-width: 850px; margin: 0 auto 80px;">

        <h2 class="section-title" data-aos="fade-left">
            <i class="las la-info-circle"></i> أسئلة عامة حول المنصة
        </h2>

        <div class="faq-item" data-aos="fade-up" data-aos-delay="100">
            <button class="faq-question"
                style="width: 100%; text-align: right; background: none; border: none; padding: 18px 25px; font-size: 18px; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                كيف يمكنني التسجيل كمزود خدمة؟
                <span class="icon">+</span>
            </button>
            <div class="faq-answer">
                <p>
                    يمكنك التسجيل كمزود خدمة بالضغط على زر "انضم كمزود خدمة" في الصفحة الرئيسية، ثم ملء نموذج التسجيل وتقديم المستندات المطلوبة للتحقق من هويتك وخبرتك.
                </p>
            </div>
        </div>

        <div class="faq-item" data-aos="fade-up" data-aos-delay="200">
            <button class="faq-question"
                style="width: 100%; text-align: right; background: none; border: none; padding: 18px 25px; font-size: 18px; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                ما هي رسوم استخدام منصة خدماتي؟
                <span class="icon">+</span>
            </button>
            <div class="faq-answer">
                <p>
                    التسجيل وتصفح الخدمات مجاني بالكامل. يتم خصم عمولة بسيطة فقط عند إتمام أي عملية بيع ناجحة لمزود الخدمة. تفاصيل الرسوم موضحة في صفحة الشروط والأحكام.
                </p>
            </div>
        </div>

        <h2 class="section-title" data-aos="fade-left" data-aos-delay="300">
            <i class="las la-shield-alt"></i> أسئلة حول الأمان والدفع
        </h2>

        <div class="faq-item" data-aos="fade-up" data-aos-delay="400">
            <button class="faq-question"
                style="width: 100%; text-align: right; background: none; border: none; padding: 18px 25px; font-size: 18px; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                هل معلومات الدفع آمنة؟
                <span class="icon">+</span>
            </button>
            <div class="faq-answer">
                <p>
                    نعم، نستخدم أحدث تقنيات التشفير (SSL) لضمان أمان وحماية جميع بيانات الدفع والمعلومات الشخصية لعملائنا ومزودي الخدمات.
                </p>
            </div>
        </div>

    </section>

</div>

<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // تفعيل أنيميشن الـ AOS لظهور الأسئلة بشكل متتابع
        AOS.init({
            duration: 700,
            once: true
        });

        const faqQuestions = document.querySelectorAll('.faq-question');

        faqQuestions.forEach(question => {
            question.addEventListener('click', () => {
                const answer = question.nextElementSibling;

                // تبديل فئة Active و Open
                question.classList.toggle('active');
                answer.classList.toggle('open');

                // تحريك فتح وإغلاق الإجابة بنعومة وسلاسة
                if (answer.classList.contains('open')) {
                    answer.style.maxHeight = answer.scrollHeight + "px";
                    question.querySelector('.icon').textContent = '−';
                } else {
                    answer.style.maxHeight = '0';
                    question.querySelector('.icon').textContent = '+';
                }
            });
        });
    });
</script>

<?php
// 7. تضمين ملف التذييل
include_once __DIR__ . '/includes/footer.php';
?>