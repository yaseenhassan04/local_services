document.addEventListener('DOMContentLoaded', function(){
    // مثال: تحكم في رسالة الترحيب أو التحقق البسيط
    console.log("Local services app loaded");
});

// تضمن عدم إرسال طلب AJAX لكل ضغطة مفتاح، بل بعد التوقف عن الكتابة بمدة محددة (500ms)
let debounceTimeout;

/**
 * دالة جلب الخدمات باستخدام AJAX (Fetch API)
 */
function fetchServices() {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        
        const container = document.getElementById('servicesContainer');
        const searchInput = document.getElementById('searchInput');
        const categoryFilter = document.getElementById('categoryFilter');
        const loadingIndicator = document.getElementById('loadingIndicator');

        // عرض مؤشر التحميل وتفريغ النتائج القديمة
        container.innerHTML = `<p style="grid-column: 1 / -1; text-align: center;" id="loadingIndicator">جار البحث...</p>`;

        // بناء بيانات الاستعلام
        const query = searchInput.value;
        const categoryId = categoryFilter.value;
        
        // بناء رابط نقطة الاتصال مع البيانات
        const apiUrl = `/local_services/api/search.php?q=${encodeURIComponent(query)}&category=${encodeURIComponent(categoryId)}`;

        fetch(apiUrl)
            .then(response => {
                // التحقق من أن الاستجابة ناجحة (Status 200)
                if (!response.ok) {
                    throw new Error('فشل في جلب البيانات من الخادم.');
                }
                return response.json();
            })
            .then(data => {
                // مسح مؤشر التحميل والنتائج السابقة
                container.innerHTML = ''; 

                if (data.length === 0) {
                    container.innerHTML = `<p style="grid-column: 1 / -1; text-align: center; color: #777;">عذراً، لم يتم العثور على خدمات مطابقة لمعايير البحث.</p>`;
                    return;
                }

                // بناء بطاقات الخدمات (Service Cards)
                data.forEach(service => {
                    const card = createServiceCard(service);
                    container.appendChild(card);
                });
            })
            .catch(error => {
                console.error('Error fetching services:', error);
                container.innerHTML = `<p style="grid-column: 1 / -1; text-align: center; color: red;">خطأ في الاتصال: ${error.message}</p>`;
            });

    }, 500); // 500 ملي ثانية تأخير بعد توقف الكتابة
}

/**
 * دالة مساعدة لإنشاء بطاقة الخدمة (DOM Element)
 */
function createServiceCard(service) {
    const card = document.createElement('div');
    card.className = 'service-card';
    card.style.cssText = 'background: #fff; border: 1px solid #eee; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.05); transition: transform 0.2s;';
    
    // يمكنك إضافة المزيد من التفاصيل هنا
    card.innerHTML = `
        <img src="/local_services/assets/uploads/${service.image || 'default.jpg'}" alt="${service.title}" 
             style="width: 100%; height: 200px; object-fit: cover;">
        <div style="padding: 15px;">
            <h3 style="font-size: 20px; color: var(--primary-dark); margin-bottom: 5px;">
                ${service.title}
            </h3>
            <p style="color: #555; font-size: 14px; margin-bottom: 10px; max-height: 40px; overflow: hidden;">
                ${service.description.substring(0, 70)}...
            </p>
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #eee; padding-top: 10px;">
                <span style="font-weight: 700; color: var(--accent);">${service.price} ر.س</span>
                <a href="/local_services/view_service.php?id=${service.id}" 
                   style="background: var(--accent); color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none;">
                    عرض التفاصيل
                </a>
            </div>
        </div>
    `;
    return card;
}
