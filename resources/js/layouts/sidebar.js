const sidebar = document.querySelector('.sidebar');

// Restaurar scroll (sin jQuery, sin wrapper, sin load)
if (sidebar) {
    const saved = parseInt(localStorage.getItem('sidebar-scroll'), 10);
    if (!Number.isNaN(saved)) sidebar.scrollTop = saved;

    // Guardar scroll con debounce
    let scrollTimeout;
    sidebar.addEventListener('scroll', () => {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            localStorage.setItem('sidebar-scroll', String(sidebar.scrollTop));
        }, 150);
    });
}

// Rotar chevron según estado del collapse
document.querySelectorAll('[data-bs-toggle="collapse"]').forEach((btn) => {
    const targetId = btn.getAttribute('data-bs-target');
    if (!targetId?.startsWith('#')) return;

    const collapseEl = document.querySelector(targetId);
    const chevron = btn.querySelector('.chevron-icon');
    if (!collapseEl || !chevron) return;

    const rotate = (deg) => { chevron.style.transform = `rotate(${deg}deg)`; };

    if (collapseEl.classList.contains('show')) rotate(180);

    collapseEl.addEventListener('show.bs.collapse', () => rotate(180));
    collapseEl.addEventListener('hide.bs.collapse', () => rotate(0));
});