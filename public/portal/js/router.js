'use strict';

const Router = {
  page: null,
  current: null,
  destroyFns: [],
  pageEl: null,
  timer: null,

  routes: {
    dashboard: { title: 'Dashboard', permission: null, permAny: null, page: 'DashboardPage' },
    queue: { title: 'Queue', permission: null, permAny: ['manage_queue', 'queue.view'], page: 'QueuePage' },
    procurements: { title: 'Procurements', permission: null, permAny: ['procurements.view_any', 'procurements.view_own'], page: 'ProcurementsPage' },
    payments: { title: 'Payments', permission: null, permAny: ['payments.view', 'payments.view_own'], page: 'PaymentsPage' },
    bookings: { title: 'Bookings', permission: null, permAny: ['bookings.view_any', 'bookings.view_own'], page: 'BookingsPage' },
    approvals: { title: 'Approvals', permission: 'procurements.approve', permAny: null, page: 'ApprovalsPage' },
    centres: { title: 'Centres', permission: null, permAny: ['centres.view', 'view_centres'], page: 'CentresPage' },
    slots: { title: 'Slots', permission: null, permAny: ['slots.view', 'view_slots'], page: 'SlotsPage' },
    staff: { title: 'Staff', permission: null, permAny: ['staff.view', 'manage_staff'], page: 'StaffPage' },
    farmers: { title: 'Farmers', permission: null, permAny: ['view_farmers', 'manage_farmers'], page: 'FarmersPage' },
    rates: { title: 'Rates', permission: 'rates.manage', permAny: null, page: 'RatesPage' },
    notifications: { title: 'Notifications', permission: null, permAny: ['notifications.view', 'notifications.test'], page: 'NotificationsPage' },
    settings: { title: 'Settings', permission: null, permAny: ['settings.view', 'settings.manage'], page: 'SettingsPage' },
    secrets: { title: 'Secrets', permission: null, permAny: ['settings.secret_manage', 'manage_secrets'], page: 'SecretsPage' },
    maintenance: { title: 'Maintenance', permission: null, permAny: ['maintenance.manage', 'manage_maintenance'], page: 'MaintenancePage' },
    translations: { title: 'Translations', permission: null, permAny: ['translations.view', 'translations.manage'], page: 'TranslationsPage' },
    files: { title: 'Files', permission: null, permAny: ['files.manage_all', 'files.download', 'manage_files'], page: 'FilesPage' },
    audit: { title: 'Audit Log', permission: 'view_audit_logs', permAny: null, page: 'AuditPage' },
    reports: { title: 'Reports', permission: null, permAny: ['view_reports'], page: 'ReportsPage' },
  },

  canAccess(route) {
    const r = this.routes[route];
    if (!r) return false;
    if (r.permission) return Auth.can(r.permission);
    if (!r.permAny) return true;
    return r.permAny.some((p) => Auth.can(p));
  },

  start() {
    Locale.init();
    Api.onUnauthorized = () => { this.onUnauthorized(); };
    Api.onMaintenance = () => { this.onMaintenance(); };
    Api.onRateLimit = (secs) => { toast('Too many requests. Try again in ' + secs + 's.', 'warn', 6000); };

    const init = async () => {
      const ok = await Auth.boot();
      if (!ok) {
        this.renderShell('login', Auth.renderLogin(), null);
        return;
      }
      const route = this.validRoute(this.routeFromHash()) || 'dashboard';
      this.load(route);
      window.addEventListener('hashchange', () => this.load(this.validRoute(this.routeFromHash()) || 'dashboard'));
      this.startSessionTimer();
    };
    init();
  },

  routeFromHash() {
    const h = location.hash.replace(/^#\/?/, '');
    return h.split('?')[0] || 'dashboard';
  },

  validRoute(route) {
    return this.routes[route] && this.canAccess(route) ? route : null;
  },

  goTo(route) {
    location.hash = '#/' + route;
  },
  refresh() {
    if (Auth.user) {
      const target = this.validRoute(this.routeFromHash()) || (this.validRoute(this.intended) ? this.intended : null) || 'dashboard';
      this.intended = null;
      this.load(target);
    }
    else this.renderShell('login', Auth.renderLogin(), null);
  },

  afterLogin() {
    const target = this.validRoute(this.intended) || 'dashboard';
    this.intended = null;
    if (this.routeFromHash() !== target) location.hash = '#/' + target;
    else this.load(target);
  },

  onUnauthorized() {
    toast(Locale.t('sessionExpired'), 'error');
    Auth.user = null;
    Auth.permissions = [];
    this.intended = location.hash.replace(/^#\/?/, '') || 'dashboard';
    location.hash = '#/dashboard';
    this.renderShell('login', Auth.renderLogin(), null);
  },

  changePassword() {
    const body = U.el('div', null);
    const grid = U.el('div', { class: 'form-grid' });
    const fCur = Pg.input('Current password', 'current_password', '');
    const fNew = Pg.input('New password', 'new_password', '');
    const fConf = Pg.input('Confirm new password', 'new_password_confirmation', '');
    [fCur, fNew, fConf].forEach((el) => grid.appendChild(el));
    [fCur, fNew, fConf].forEach((w) => { w.querySelector('input').type = 'password'; });
    body.appendChild(grid);
    const okBtn = U.el('button', { class: 'btn btn-primary', text: Locale.t('save') });
    const modal = openModal({ title: 'Change Password', size: 'sm', body, footer: [okBtn] });
    okBtn.addEventListener('click', async () => {
      clearFieldErrors(body);
      const payload = {
        current_password: fCur.querySelector('input').value,
        new_password: fNew.querySelector('input').value,
        new_password_confirmation: fConf.querySelector('input').value,
      };
      if (!payload.current_password) { fieldError(fCur.querySelector('input'), Locale.t('errorRequired')); return; }
      if (!payload.new_password) { fieldError(fNew.querySelector('input'), Locale.t('errorRequired')); return; }
      if (payload.new_password !== payload.new_password_confirmation) { fieldError(fConf.querySelector('input'), 'Passwords do not match'); return; }
      okBtn.disabled = true;
      const res = await Api.post('/web/password/change', payload, { handleMaintenance: false });
      if (!res.ok) { okBtn.disabled = false; applyApiErrors(body, res); return; }
      toast('Password changed', 'success');
      modal.close();
      this.load(this.current || 'dashboard');
    });
  },

  onMaintenance() {
    const app = document.getElementById('app');
    app.innerHTML = '';
    app.appendChild(Auth.renderMaintenance());
    this.pageEl = null;
  },

  startSessionTimer() {
    setInterval(async () => {
      if (!Auth.user) return;
      const res = await Api.get('/web/me', { handleMaintenance: false });
      if (!res.ok) {
        if (res.status === 503) { this.onMaintenance(); }
        else if (res.status === 401) { this.onUnauthorized(); }
      }
    }, FPS.sessionCheckMs);
  },

  load(route) {
    if (route === 'login') {
      this.renderShell('login', Auth.renderLogin(), null);
      return;
    }
    if (!Auth.user) {
      if (route && route !== 'dashboard') this.intended = route;
      this.renderShell('login', Auth.renderLogin(), null);
      return;
    }
    if (!this.routes[route] || !this.canAccess(route)) {
      this.renderAccessDenied();
      return;
    }

    this.destroyFns.forEach((fn) => { try { fn(); } catch (e) {} });
    this.destroyFns = [];
    if (this.timer) { clearInterval(this.timer); this.timer = null; }

    const lib = window[this.routes[route].page];
    if (!lib) {
      this.renderShell(route, U.el('div', { class: 'status-banner error', text: 'Page not implemented: ' + route }), this.routes[route].title);
      return;
    }

    this.current = route;
    this.renderShell(route, null, this.routes[route].title);
    const result = lib.render(this.pageEl, U.params(location.hash));
    if (result && typeof result.destroy === 'function') this.destroyFns.push(result.destroy);
    if (result && result.poll) {
      const startPoll = () => {
        this.timer = setInterval(async () => {
          try { await result.poll(this.pageEl); } catch (e) {}
        }, FPS.pollingIntervalMs);
      };
      startPoll();
      if (result.destroy && typeof result.destroy === 'function') {
        const orig = result.destroy;
        this.destroyFns = [() => { clearInterval(this.timer); this.timer = null; orig(); }];
      }
    }
    this.activateNav(route);
  },

  renderShell(route, content, title) {
    const app = document.getElementById('app');
    app.innerHTML = '';
    if (route === 'login') {
      app.appendChild(content || U.el('div'));
      return;
    }
    const shell = U.el('div', { class: 'app-shell' });
    shell.appendChild(this.sidebar());
    const main = U.el('div', { class: 'main' });
    const topbar = U.el('div', { class: 'topbar' });
    const toggle = U.el('button', { class: 'icon-btn sidebar-toggle', 'aria-label': 'Menu' });
    toggle.innerHTML = '&#9776;';
    toggle.addEventListener('click', () => U.qs('.sidebar').classList.toggle('open'));
    const titleEl = U.el('div', { class: 'topbar-title', text: title });
    const tools = U.el('div', { class: 'topbar-tools' });
    const langSel = U.el('select', { style: 'width:auto;padding:6px 10px;font-size:13px' }, [
      U.el('option', { value: 'en', text: Locale.t('english'), selected: Locale.current === 'en' ? 'selected' : null }),
      U.el('option', { value: 'hi', text: Locale.t('hindi'), selected: Locale.current === 'hi' ? 'selected' : null }),
    ]);
    langSel.addEventListener('change', () => { Locale.set(langSel.value); Router.refresh(); });
    const userBtn = U.el('button', { class: 'icon-btn' });
    userBtn.innerHTML = escapeHtml((Auth.user && Auth.user.name) || '');
    const userWrap = U.el('div', { class: 'user-menu' });
    const menu = U.el('div', { class: 'user-menu-drop' });
    const chg = U.el('button', { type: 'button', class: 'user-menu-item', text: 'Change Password' });
    chg.addEventListener('click', () => { menu.classList.remove('open'); this.changePassword(); });
    menu.appendChild(chg);
    userWrap.appendChild(userBtn);
    userWrap.appendChild(menu);
    userBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      menu.classList.toggle('open');
    });
    document.addEventListener('click', (e) => { if (!userWrap.contains(e.target)) menu.classList.remove('open'); });
    tools.appendChild(langSel);
    tools.appendChild(userWrap);
    topbar.appendChild(toggle);
    topbar.appendChild(titleEl);
    topbar.appendChild(tools);
    main.appendChild(topbar);

    this.pageEl = U.el('div', { class: 'content' });
    if (content) this.pageEl.appendChild(content);
    main.appendChild(this.pageEl);
    shell.appendChild(main);
    app.appendChild(shell);
  },

  sidebar() {
    const sections = [
      { title: null, items: ['dashboard'] },
      { title: 'Operations', items: ['queue', 'procurements', 'payments', 'approvals', 'bookings'] },
      { title: 'Administration', items: ['centres', 'slots', 'staff', 'farmers', 'rates', 'reports', 'notifications'] },
      { title: 'System', items: ['settings', 'secrets', 'maintenance', 'translations', 'files', 'audit'] },
    ];
    const sb = U.el('aside', { class: 'sidebar' });
    sb.appendChild(U.el('div', { class: 'sidebar-brand' }, [
      U.el('span', { html: '<svg width="30" height="30" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="#ffffff"/><path d="M8 10h3v12H8zm6-3h3v15h-3zM20 7h3v15h-3z" fill="#1b6d3f"/></svg>' }),
      U.el('span', null, [
        U.el('div', { class: 'brand-name', text: Locale.t('brand') }),
        U.el('div', { class: 'brand-sub', text: Locale.t('portal') }),
      ]),
    ]));
    const nav = U.el('nav', { class: 'sidebar-nav' });
    sections.forEach((sec) => {
      if (sec.title) nav.appendChild(U.el('div', { class: 'sidebar-sec-title', text: Locale.t(sec.title.toLowerCase()) || sec.title }));
      sec.items.forEach((route) => {
        if (!this.canAccess(route)) return;
        const item = U.el('button', { class: 'nav-item' + (this.current === route ? ' active' : ''), 'data-route': route });
        item.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="${Router.icons[route] || ''}"/></svg> ${escapeHtml(Locale.t(route))}`;
        item.addEventListener('click', () => Router.goTo(route));
        nav.appendChild(item);
      });
    });
    sb.appendChild(nav);

    const user = Auth.user;
    if (user) {
      sb.appendChild(U.el('div', { class: 'sidebar-user' }, [
        U.el('div', { class: 'u-name', text: user.name }),
        U.el('div', { class: 'u-role', text: (user.role || '').toLowerCase() }),
        U.el('button', { class: 'btn btn-outline btn-sm', style: 'margin-top:8px;width:100%;justify-content:center;background:rgba(255,255,255,0.08);color:#fff;border-color:rgba(255,255,255,0.25)', text: Locale.t('logout'), onclick: () => { confirmDialog('Logout?').then((ok) => { if (ok) Auth.logout(); }); } }),
      ]));
    }
    return sb;
  },

  activateNav(route) {
    U.qsa('.nav-item').forEach((el) => el.classList.toggle('active', el.dataset.route === route));
  },

  renderAccessDenied() {
    this.renderShell(this.current, U.el('div', { class: 'status-banner error', text: '403 - Access denied' }), 'Forbidden');
  },

  icons: {
    dashboard: 'M4 13h6V4H4v9zm0 7h6v-4H4v4zm10 0h6V11h-6v9zm0-16v4h6V4h-6z',
    queue: 'M2 4v16h20V4H2zm3 3h12v2H5V7zm0 4h12v2H5v-2zm0 4h8v2H5v-2z',
    procurements: 'M7 2L4 5v15a2 2 0 002 2h12a2 2 0 002-2V5l-3-3H7zm0 2h10v3H7V4zm2 8h6v2H9v-2z',
    payments: 'M2 6a2 2 0 012-2h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6zm2 0v12h16V6H4zm4 2h8v2H8V8zm0 4h8v2H8v-2z',
    bookings: 'M5 2h14a2 2 0 012 2v16a2 2 0 01-2 2H5a2 2 0 01-2-2V4a2 2 0 012-2zm1 3v14h12V5H6zm3 3h6v2H9V8zm0 4h6v2H9v-2z',
    approvals: 'M12 2l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4L4.2 7.7l5.4-.8L12 2zm-1 8.5v4h2v-4h-2z',
    centres: 'M12 2L2 12h3v8h6v-6h2v6h6v-8h3L12 2z',
    slots: 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2v5h5m-8 5h8v2H7v-2zm0 4h8v2H7v-2z',
    staff: 'M12 2a4 4 0 100 8 4 4 0 000-8zm-7 18c0-3.9 3.1-7 7-7s7 3.1 7 7H5z',
    farmers: 'M2 6a1 1 0 011-1h2V3a1 1 0 112 0v2h4V3a1 1 0 112 0v2h2a1 1 0 011 1v2h-1l-2 3h3v2h-1l-1 3h3v2h-3v3h-2v-7h-2v7H8v-7H6v7H4v-3H3v-2h3l-1-3H4V6zm2 0h3v1H6V6zm5 0h3v1h-3V6z',
    rates: 'M4 4h16v4H4V4zm2 6h12v2H6v-2zm0 4h12v2H6v-2zm0 4h8v2H6v-2z',
    notifications: 'M12 22a2.5 2.5 0 002.5-2.5h-5A2.5 2.5 0 0012 22zM18 16v-5a6 6 0 00-4-5.6V4a2 2 0 00-4 0v1.4A6 6 0 006 11v5l-2 2v1h16v-1l-2-2z',
    settings: 'M12 8a4 4 0 100 8 4 4 0 000-8zm9 4a7 7 0 00-.1-1.3l2-1.6-2-3.4-2.4.9a7 7 0 00-2.3-1.3L16 3h-4l-.3 2.3a7 7 0 00-2.3 1.3l-2.4-.9-2 3.4 2 1.6A7 7 0 003.1 12a7 7 0 00.1 1.3l-2 1.6 2 3.4 2.4-.9a7 7 0 002.3 1.3L8 21h4l.3-2.3a7 7 0 002.3-1.3l2.4.9 2-3.4-2-1.6c.1-.4.1-.8.1-1.3z',
    secrets: 'M12 3a3 3 0 00-3 3v2H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V10a2 2 0 00-2-2h-2V6a3 3 0 00-3-3zm0 2a1 1 0 011 1v2h-2V6a1 1 0 011-1z',
    maintenance: 'M19 14.6a8 8 0 01-9 0L3 22h18l-2-7.4zM6 2l1 4H4l2-4zm5 0l1 4H9l2-4zm5 0l1 4h-3l2-4z',
    translations: 'M4 5h12v2H9v2h5v2H9v2h3v2H4a2 2 0 010-4V5zM7 6a20 20 0 0110-2 20 20 0 011 5h-5l-1 3h-1l1-3H8l-1-3zm11 14h-2l-1-3h-2l-1 3h-2l2-5h2l2 5z',
    files: 'M6 2h8l6 6v14a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2zm7 1.5V9h5.5L13 3.5z',
    audit: 'M12 2a10 10 0 100 20 10 10 0 000-20zm-1 5h2v6h-2V7zm0 8h2v2h-2v-2z',
    reports: 'M4 4h2v16H4V4zm4 4h2v12H8V8zm4 3h2v9h-2v-9zm4 3h2v6h-2v-6z',
  },
};

Router.start();