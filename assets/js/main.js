/* ═══════════════════════════════════════════════════════════
   Giftz Inventory — Main JS
   ═══════════════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {

  // ─── Sidebar Toggle ─────────────────────────────────────
  const sidebar  = document.querySelector('.sidebar');
  const mainCont = document.querySelector('.main-content');
  const toggle   = document.getElementById('sidebarToggle');
  const overlay  = document.querySelector('.sidebar-overlay');

  function isMobile() { return window.innerWidth <= 768; }

  function saveSidebarState(collapsed) {
    localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
  }

  function applyDesktopState() {
    const collapsed = localStorage.getItem('sidebarCollapsed') === '1';
    if (collapsed) {
      sidebar?.classList.add('collapsed');
      mainCont?.classList.add('expanded');
    } else {
      sidebar?.classList.remove('collapsed');
      mainCont?.classList.remove('expanded');
    }
  }

  if (!isMobile()) applyDesktopState();

  toggle?.addEventListener('click', () => {
    if (isMobile()) {
      sidebar?.classList.toggle('mobile-open');
      overlay?.classList.toggle('open');
    } else {
      const collapsed = sidebar?.classList.toggle('collapsed');
      mainCont?.classList.toggle('expanded', collapsed);
      saveSidebarState(collapsed);
    }
  });

  overlay?.addEventListener('click', () => {
    sidebar?.classList.remove('mobile-open');
    overlay?.classList.remove('open');
  });

  // ─── User Dropdown ───────────────────────────────────────
  const userDropdown = document.querySelector('.user-dropdown');
  const userToggle   = document.querySelector('.user-dropdown-toggle');

  userToggle?.addEventListener('click', (e) => {
    e.stopPropagation();
    userDropdown?.classList.toggle('open');
  });

  document.addEventListener('click', (e) => {
    if (!userDropdown?.contains(e.target)) {
      userDropdown?.classList.remove('open');
    }
  });

  // ─── Flash Message Dismiss ───────────────────────────────
  document.querySelectorAll('.flash-close').forEach(btn => {
    btn.addEventListener('click', () => {
      const flash = btn.closest('.flash');
      flash.style.animation = 'none';
      flash.style.opacity = '0';
      flash.style.transition = 'opacity .2s';
      setTimeout(() => flash.remove(), 200);
    });
  });

  // Auto-dismiss after 5 seconds
  setTimeout(() => {
    document.querySelectorAll('.flash').forEach(flash => {
      flash.style.opacity = '0';
      flash.style.transition = 'opacity .4s';
      setTimeout(() => flash.remove(), 400);
    });
  }, 5000);

  // ─── Confirm Dialog ──────────────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', (e) => {
      const msg = el.dataset.confirm || 'Are you sure?';
      if (!confirm(msg)) e.preventDefault();
    });
  });

  // ─── Image Preview ───────────────────────────────────────
  const imgInputs = document.querySelectorAll('input[type="file"][data-preview]');
  imgInputs.forEach(input => {
    const previewId = input.dataset.preview;
    const preview   = document.getElementById(previewId);
    if (!preview) return;

    input.addEventListener('change', () => {
      const file = input.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
      reader.readAsDataURL(file);
    });
  });

  // ─── Tooltip (title attribute) ───────────────────────────
  // Simple tooltip via title — browser native

  // ─── Active Nav Link ─────────────────────────────────────
  const currentPath = window.location.pathname;
  document.querySelectorAll('.nav-item').forEach(link => {
    const href = link.getAttribute('href') || '';
    if (href && currentPath.startsWith(href.replace(/\/[^/]*\.php.*$/, ''))) {
      // handled server-side via active class
    }
  });

  // ─── Auto-format number inputs ───────────────────────────
  document.querySelectorAll('[data-format="number"]').forEach(input => {
    input.addEventListener('blur', () => {
      const val = parseFloat(input.value);
      if (!isNaN(val)) input.value = val.toFixed(2);
    });
  });

  // ─── Select All Checkbox ─────────────────────────────────
  const checkAll = document.getElementById('checkAll');
  checkAll?.addEventListener('change', () => {
    document.querySelectorAll('.row-check').forEach(cb => {
      cb.checked = checkAll.checked;
    });
  });

  // ─── Responsive table overflow hint ──────────────────────
  document.querySelectorAll('.table-responsive').forEach(wrapper => {
    if (wrapper.scrollWidth > wrapper.clientWidth) {
      wrapper.style.borderBottom = '2px solid var(--accent)';
    }
  });

});
