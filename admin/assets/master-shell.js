(() => {
  const path = location.pathname.split('/').pop() || 'index.php';
  const active = path === 'index.php' ? 'dashboard' :
    (['questions.php','results.php','edit_question.php','save_question.php','exam_link.php','preview.php'].includes(path) ? 'ujian' :
    (['participants.php','save_participant.php'].includes(path) ? 'peserta' :
    (path === 'update.php' ? 'update' : '')));

  const sidebar = document.querySelector('.admin-sidebar');
  if (sidebar) {
    const currentVersion = document.querySelector('.version-chip')?.textContent.trim() || document.querySelector('.admin-footer span:last-child')?.textContent.replace(/^Versi\s*/i,'').trim() || '';
    if (!sidebar.querySelector('.admin-brand')) {
      sidebar.insertAdjacentHTML('afterbegin', '<div class="admin-brand"><span class="mark">✓</span> Ujian Online</div>');
    }
    const systemBox = sidebar.querySelector('.system-box');
    if (!systemBox) {
      sidebar.insertAdjacentHTML('beforeend', `<div class="system-box master-system-box"><h4>Informasi Sistem</h4><div class="system-row"><span>Versi Aplikasi</span><span class="version-chip">${currentVersion || '—'}</span></div><div class="system-row"><span>PHP Version</span><b>${window.__ADMIN_PHP_VERSION || 'PHP'}</b></div><div class="system-row"><span>Environment</span><span class="prod-chip">Production</span></div></div>`);
    }
    const links = [...sidebar.querySelectorAll('.admin-nav a')];
    links.forEach(a => a.classList.remove('active'));
    const match = active === 'dashboard' ? links.find(a => /index\.php$/.test(a.getAttribute('href')||'')) :
      active === 'ujian' ? links.find(a => /#ujian-list/.test(a.getAttribute('href')||'') || /questions\.php/.test(a.getAttribute('href')||'')) :
      active === 'peserta' ? links.find(a => /participants\.php/.test(a.getAttribute('href')||'')) :
      active === 'update' ? links.find(a => /update\.php/.test(a.getAttribute('href')||'')) : null;
    if (match) match.classList.add('active');
  }

  const topbar = document.querySelector('.admin-topbar');
  if (topbar) {
    const profile = topbar.querySelector('.profile');
    if (profile) {
      const name = window.__ADMIN_NAME || profile.querySelector('b')?.textContent.trim() || 'Administrator';
      const avatar = profile.querySelector('.avatar');
      const nameEl = profile.querySelector('b');
      if (avatar) avatar.textContent = (name.trim().charAt(0) || 'A').toUpperCase();
      if (nameEl) nameEl.textContent = name;
      if (!profile.querySelector('.logout-link')) profile.insertAdjacentHTML('beforeend', '<a class="logout-link" href="../logout.php">Keluar</a>');
    }
  }
})();
