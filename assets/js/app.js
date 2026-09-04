document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  const base = (window.DCOS_BASE_URL || '/college_management').replace(/\/$/, '');
  const savedTheme = localStorage.getItem('dcos-theme');
  if (savedTheme === 'dark') body.classList.add('dark');
  document.querySelectorAll('[data-theme-toggle]').forEach(btn => btn.addEventListener('click', () => {
    const dark = body.classList.toggle('dark');
    localStorage.setItem('dcos-theme', dark ? 'dark' : 'light');
    btn.setAttribute('aria-pressed', String(dark));
  }));
  const sidebar = document.getElementById('sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  const toggle = document.querySelector('[data-sidebar-toggle]');
  if (toggle && sidebar) toggle.addEventListener('click', () => { sidebar.classList.toggle('open'); if (overlay) overlay.classList.toggle('active'); });
  if (overlay) overlay.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('active'); });
  const q = document.getElementById('globalSearch');
  if (q) {
    let timer;
    q.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(async () => {
        const value = q.value.trim();
        if (!value) { q.title=''; return; }
        try {
          const u = new URL(base + '/ajax/search.php', location.origin);
          u.searchParams.set('q', value);
          const r = await fetch(u, {headers:{'X-Requested-With':'XMLHttpRequest'}});
          const j = await r.json();
          q.title = (j.results || []).map(x => x.title).join(' • ') || 'No authorized results';
        } catch(e) { q.title = 'Search unavailable'; }
      }, 250);
    });
  }
  window.showToast = function(title, message, type='success') {
    let wrap=document.querySelector('.toast-container');
    if(!wrap){wrap=document.createElement('div');wrap.className='toast-container';document.body.appendChild(wrap);}
    const el=document.createElement('div');el.className='app-toast '+type;
    el.innerHTML='<div class=\"app-toast-icon\">'+(type==='success'?'✓':type==='danger'?'!':'i')+'</div><div class=\"app-toast-content\"><strong></strong><span></span></div>';
    el.querySelector('strong').textContent=title;el.querySelector('span').textContent=message;wrap.appendChild(el);
    setTimeout(()=>{el.remove();},3500);
  };
});
