<?php
// includes/admin_nav.php
$me = getCurrentUser();
?>
<nav class="top-nav">
    <div class="nav-brand">
        <button id="sidebarToggle" class="nav-icon" style="margin-left: 10px; background: transparent; border: none; cursor: pointer;">
            <i class="las la-bars" style="font-size: 20px;"></i>
        </button>
        <i class="las la-shield-alt" style="color:var(--danger); font-size:22px;"></i>
        <span>لوحة تحكم الإدارة</span>
    </div>
    <div class="nav-spacer"></div>
    <a href="/local_services/index.php" class="nav-icon" title="الموقع الرئيسي" target="_blank">
        <i class="las la-external-link-alt"></i>
    </a>
    <?php if ($me): ?>
    <div class="nav-user">
        <div class="nav-avatar"><?php echo mb_substr($me['full_name'], 0, 1); ?></div>
        <div>
            <div class="nav-uname"><?php echo htmlspecialchars($me['full_name']); ?></div>
            <div class="nav-urole"><i class="las la-crown"></i> مدير النظام</div>
        </div>
    </div>
    <?php endif; ?>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('appSidebar');
    
    if(toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            document.querySelector('.main')?.classList.toggle('expanded');
        });
    }
});
</script>