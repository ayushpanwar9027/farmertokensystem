'use strict';

const StaffPage = {
  state: { q: '', role: '', status: '', page: 1 },

  async fetchList() {
    const q = U.qsFrom({ q: this.state.q || undefined, role: this.state.role || undefined, status: this.state.status || undefined, page: this.state.page, per_page: FPS.pageSize });
    const res = await Api.get('/api/v1/admin/staff' + q, { handleMaintenance: false });
    if (!res.ok) return null;
    return Api.unwrap(res.data);
  },

  async showDetail(id) {
    const res = await Api.get('/api/v1/admin/staff/' + id, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    const s = Api.unwrap(res.data);
    const u = s && (s.staff || s.user || s);
    const grid = U.el('div', { class: 'detail-grid' });
    [
      ['Name', u.name],
      ['Mobile', u.mobile],
      ['Email', u.email],
      ['Username', u.username],
      ['Role', u.role && typeof u.role === 'object' ? u.role.name : u.role_name],
      ['Status', Pg.badge(u.status)],
      ['Password set', u.password_set ? 'Yes' : 'No'],
      ['Centre', u.centre ? u.centre.name : '—'],
      ['Last login', u.last_login_at ? U.fmtDateTime(u.last_login_at) : '—'],
      ['Created', U.fmtDateTime(u.created_at)],
    ].forEach(([k, v]) => grid.appendChild(U.el('div', { class: 'detail-item' }, [U.el('div', { class: 'k', text: k }), U.el('div', { class: 'v', html: typeof v === 'string' && v.startsWith('<') ? v : U.esc(v !== null && v !== undefined ? v : '-') })])));
    openModal({ title: 'Staff Detail', size: 'lg', body: grid });
  },

  async openForm(u) {
    if (!Auth.can('staff.manage')) { toast('Permission denied', 'error'); return; }
    const rolesRes = await Api.get('/api/v1/admin/roles');
    const roles = rolesRes.ok ? (Api.unwrap(rolesRes.data) || []).filter((r) => r.assignable).map((r) => ({ value: String(r.level), label: r.display_name || r.name })).sort((a, b) => a.value - b.value) : [];
    const centresRes = await Api.get('/api/v1/centres' + U.qsFrom({ status: 'ACTIVE', per_page: 100 }));
    const centres = centresRes.ok ? ((Api.unwrap(centresRes.data).data || [])).map((c) => ({ value: String(c.id), label: c.name })) : [];
    const body = U.el('div', null);
    const grid = U.el('div', { class: 'form-grid' });
    const fName = Pg.input('Name', 'name', u ? u.name : '');
    const fMobile = Pg.input('Mobile', 'mobile', u ? u.mobile : '');
    const fEmail = Pg.input('Email', 'email', u ? u.email : '');
    const fUsername = Pg.input('Username', 'username', u ? u.username : '');
    const fRole = Pg.select('Role', 'role', u ? String((u.role_name || (u.role && u.role.name))) : '', roles);
    const fCentre = Pg.select('Centre', 'centre_id', u && u.centre ? String(u.centre.id) : '', centres);
    [fName, fMobile, fEmail, fUsername, fRole, fCentre].forEach((el) => grid.appendChild(el));
    body.appendChild(grid);
    body.appendChild(U.el('p', { text: 'New staff set their own password via forgot-password flow.', style: 'font-size:12px;color:var(--text-light);margin-top:8px' }));
    const okBtn = U.el('button', { class: 'btn btn-primary', text: Locale.t('save') });
    const modal = openModal({ title: u ? 'Edit Staff' : 'New Staff', size: 'lg', body, footer: [okBtn] });
    okBtn.addEventListener('click', async () => {
      clearFieldErrors(body);
      const payload = {
        name: fName.querySelector('input').value.trim(),
        mobile: fMobile.querySelector('input').value.trim(),
        email: fEmail.querySelector('input').value.trim(),
        username: fUsername.querySelector('input').value.trim(),
      };
      if (!u) {
        payload.role = fRole.querySelector('select').value;
        const cid = fCentre.querySelector('select').value;
        if (cid) payload.centre_id = parseInt(cid, 10);
      }
      if (!payload.name) { fieldError(fName.querySelector('input'), Locale.t('errorRequired')); return; }
      if (!payload.mobile) { fieldError(fMobile.querySelector('input'), Locale.t('errorRequired')); return; }
      okBtn.disabled = true;
      const res = u
        ? await Api.put('/api/v1/admin/staff/' + u.id, payload, { handleMaintenance: false })
        : await Api.post('/api/v1/admin/staff', payload, { handleMaintenance: false });
      if (!res.ok) { okBtn.disabled = false; applyApiErrors(body, res); return; }
      toast('Saved', 'success');
      modal.close();
      this.render(this.host);
    });
  },

  async setStatus(u) {
    if (!Auth.can('staff.manage')) { toast('Permission denied', 'error'); return; }
    const body = U.el('div', null);
    body.appendChild(U.el('p', { text: 'Status for ' + u.name, style: 'margin-bottom:12px' }));
    const sel = Pg.select('Status', 'status', u.status, ['ACTIVE', 'INACTIVE'].map((s) => ({ value: s, label: s })));
    body.appendChild(sel);
    const modal = openModal({ title: 'Change Status', size: 'sm', body });
    modal.footer.appendChild(U.el('button', { class: 'btn btn-primary', text: Locale.t('save'), onclick: async () => {
      const res = await Api.put('/api/v1/admin/staff/' + u.id + '/status', { status: sel.querySelector('select').value }, { handleMaintenance: false });
      if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
      toast('Updated', 'success');
      modal.close();
      this.render(this.host);
    } }));
  },

  async openPermissions(u) {
    if (!Auth.can('manage_staff')) { toast('Permission denied', 'error'); return; }
    const [catRes, detail] = await Promise.all([
      Api.get('/api/v1/admin/permissions'),
      Api.get('/api/v1/admin/staff/' + u.id),
    ]);
    const modules = catRes.ok ? Api.unwrap(catRes.data) : [];
    let userPerms = [];
    if (detail.ok) {
      const dd = Api.unwrap(detail.data);
      const us = dd && (dd.staff || dd.user || dd);
      userPerms = us.permissions || [];
    }
    const body = U.el('div', null);
    const status = U.el('div', { style: 'margin-bottom:10px;font-size:13px;color:var(--text-light)' });
    body.appendChild(status);
    const grant = [];
    const revoke = [];
    modules.forEach((m) => {
      const sec = U.el('div', { style: 'margin-bottom:8px' });
      sec.appendChild(U.el('div', { class: 'panel-title', style: 'font-size:13px;margin-bottom:4px', text: m.module }));
      const box = U.el('div', { style: 'display:flex;flex-wrap:wrap;gap:6px' });
      m.permissions.forEach((p) => {
        if (p.is_system) return;
        const has = userPerms.includes(p.name);
        const lbl = U.el('label', { style: 'display:inline-flex;align-items:center;gap:4px;font-size:13px;font-weight:400;padding:2px 6px;border:1px solid var(--border);border-radius:6px;background:var(--bg)' });
        const cb = U.el('input', { type: 'checkbox', value: p.name, checked: has ? 'checked' : null, style: 'width:auto' });
        lbl.appendChild(cb);
        lbl.appendChild(U.el('span', { text: p.name }));
        box.appendChild(lbl);
      });
      sec.appendChild(box);
      body.appendChild(sec);
    });
    const okBtn = U.el('button', { class: 'btn btn-primary', text: Locale.t('save') });
    const modal = openModal({ title: 'Permissions for ' + u.name, size: 'lg', body, footer: [okBtn] });
    okBtn.addEventListener('click', async () => {
      const cbEls = U.qsa('input[type=checkbox]', body);
      cbEls.forEach((cb) => {
        const isChecked = cb.checked;
        const wasChecked = userPerms.includes(cb.value);
        if (isChecked && !wasChecked) grant.push(cb.value);
        if (!isChecked && wasChecked) revoke.push(cb.value);
      });
      const res = await Api.put('/api/v1/admin/staff/' + u.id + '/permissions', { grant, revoke }, { handleMaintenance: false });
      if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
      toast('Permissions updated', 'success');
      modal.close();
    });
  },

  async paint(host, list, pg) {
    const items = (list && list.data) || (list && list.items) || [];
    host.innerHTML = '';
    const toolbar = U.el('div', { class: 'toolbar' });
    const qI = U.el('input', { placeholder: Locale.t('search'), value: this.state.q, style: 'width:auto' });
    toolbar.appendChild(qI);
    const statuses = ['', 'ACTIVE', 'INACTIVE', 'LOCKED'];
    const sel = Pg.select('', 'status', this.state.status, statuses.map((s) => ({ value: s, label: s || 'All' })));
    toolbar.appendChild(sel);
    toolbar.appendChild(U.el('button', { class: 'btn btn-primary', text: Locale.t('filter'), onclick: () => {
      this.state.q = qI.value.trim();
      this.state.status = sel.querySelector('select').value;
      this.state.page = 1;
      this.load(host);
    } }));
    toolbar.appendChild(U.el('div', { class: 'spacer' }));
    if (Auth.can('staff.manage')) toolbar.appendChild(U.el('button', { class: 'btn btn-success', text: '+ Staff', onclick: () => this.openForm(null) }));
    toolbar.appendChild(U.el('button', { class: 'btn btn-outline', text: 'CSV', onclick: () => Pg.exportCsv('staff.csv', ['Name', 'Mobile', 'Username', 'Role', 'Status'], items.map((s) => [s.name, s.mobile, s.username, s.role_name, s.status])) }));
    host.appendChild(toolbar);
    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Staff' })]));
    const pbody = U.el('div', { class: 'panel-body no-pad' });
    if (!items.length) {
      pbody.appendChild(U.el('div', { class: 'empty-state', text: Locale.t('noData') }));
    } else {
      pbody.appendChild(renderTable({
        columns: [
          { title: 'Name', render: (r) => U.esc(r.name) },
          { title: 'Mobile', render: (r) => U.esc(r.mobile || '') },
          { title: 'Username', render: (r) => U.esc(r.username || '') },
          { title: 'Role', render: (r) => U.esc(r.role_name || '') },
          { title: 'Status', render: (r) => Pg.badge(r.status) },
          { title: 'Created', render: (r) => U.esc(U.fmtDate(r.created_at)) },
          { title: '', render: (r) => {
            const span = U.el('span', { style: 'display:inline-flex;gap:6px' });
            span.appendChild(Pg.actionBtn(Locale.t('view'), 'btn-outline', () => this.showDetail(r.id)));
            if (Auth.can('staff.manage')) span.appendChild(Pg.actionBtn('Edit', 'btn-outline', () => this.openForm(r)));
            if (Auth.can('manage_staff')) span.appendChild(Pg.actionBtn('Perms', 'btn-outline', () => this.openPermissions(r)));
            if (Auth.can('staff.manage')) span.appendChild(Pg.actionBtn('Status', 'btn-outline', () => this.setStatus(r)));
            return span;
          } },
        ],
        rows: items,
      }));
    }
    panel.appendChild(pbody);
    if (pg) panel.appendChild(renderPagination(pg, (n) => { this.state.page = n; this.load(host); }));
    host.appendChild(panel);
  },

  async load(host) {
    host.innerHTML = '';
    host.appendChild(Pg.loading());
    const list = await this.fetchList();
    if (!list) { host.innerHTML = ''; return; }
    this.paint(host, list, Pg.pgFrom({ data: list }));
  },

  render(host) {
    this.host = host;
    this.load(host);
    return { destroy: () => { this.host = null; } };
  },
};