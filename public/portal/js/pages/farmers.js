'use strict';

const FarmersPage = {
  state: { q: '', district: '', status: '', page: 1 },

  async fetchList() {
    const q = U.qsFrom({ q: this.state.q || undefined, district_id: this.state.district || undefined, status: this.state.status || undefined, page: this.state.page, per_page: FPS.pageSize });
    const res = await Api.get('/api/v1/admin/farmers' + q, { handleMaintenance: false });
    if (!res.ok) return null;
    return Api.unwrap(res.data);
  },

  async fetchDistricts() {
    const res = await Api.get('/api/v1/districts');
    if (!res.ok) return [];
    const d = Api.unwrap(res.data);
    return (d && d.districts) || [];
  },

  genPassword() {
    const lower = 'abcdefghjkmnpqrstuvwxyz';
    const upper = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    const digits = '23456789';
    let p = upper[Math.floor(Math.random() * upper.length)]
      + digits[Math.floor(Math.random() * digits.length)]
      + lower[Math.floor(Math.random() * lower.length)];
    const all = lower + upper + digits;
    while (p.length < 12) p += all[Math.floor(Math.random() * all.length)];
    return p;
  },

  credentialsModal(title, mobile, password) {
    const body = U.el('div', null);
    body.appendChild(U.el('p', { text: 'Share these credentials securely with the farmer. They can login in the mobile app using the mobile number and this password.', style: 'font-size:13px;color:var(--text-light);margin-bottom:12px' }));
    const row = (label, value) => {
      const w = U.el('div', { style: 'display:flex;align-items:center;gap:8px;margin-bottom:8px' });
      const lab = U.el('div', { style: 'min-width:70px;font-size:13px;color:var(--text-light)', text: label });
      const val = U.el('code', { style: 'flex:1;padding:8px 10px;background:var(--bg);border:1px solid var(--border);border-radius:6px;word-break:break-all', text: value });
      const copy = U.el('button', { class: 'btn btn-outline btn-sm', text: 'Copy' });
      copy.addEventListener('click', () => {
        navigator.clipboard.writeText(value).then(() => toast('Copied', 'success')).catch(() => {});
      });
      w.appendChild(lab); w.appendChild(val); w.appendChild(copy);
      return w;
    };
    body.appendChild(row('Mobile', mobile));
    body.appendChild(row('Password', password));
    body.appendChild(U.el('div', { class: 'status-banner warn', style: 'margin-top:8px', text: 'The password is shown only once. Save it now.' }));
    openModal({ title: title || 'Farmer Credentials', size: 'md', body });
  },

  async showDetail(id) {
    const res = await Api.get('/api/v1/admin/farmers/' + id, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    const u = Api.unwrap(res.data);
    const f = (u && u.farmer) || u;
    const grid = U.el('div', { class: 'detail-grid' });
    const crops = (f.primary_crops && f.primary_crops.length) ? f.primary_crops.map((c) => (c && c.crop) || c).join(', ') : '—';
    [
      ['Name', f.name],
      ['Mobile', f.mobile],
      ['Village', f.village],
      ['District', f.district ? f.district.name : '—'],
      ['State', f.state],
      ['Land (acres)', f.land_area_acres != null ? f.land_area_acres : '—'],
      ['Primary crops', crops],
      ['Status', Pg.badge(f.status)],
      ['Verification', Pg.badge(f.verification_status)],
      ['Last login', f.last_login_at ? U.fmtDateTime(f.last_login_at) : '—'],
      ['Created', U.fmtDateTime(f.created_at)],
    ].forEach(([k, v]) => grid.appendChild(U.el('div', { class: 'detail-item' }, [U.el('div', { class: 'k', text: k }), U.el('div', { class: 'v', html: typeof v === 'string' && v.startsWith('<') ? v : U.esc(v !== null && v !== undefined ? v : '-') })])));
    openModal({ title: 'Farmer Detail', size: 'lg', body: grid });
  },

  async openForm() {
    if (!Auth.can('manage_farmers')) { toast('Permission denied', 'error'); return; }
    const districts = await this.fetchDistricts();
    const body = U.el('div', null);
    const grid = U.el('div', { class: 'form-grid' });
    const fName = Pg.input('Name', 'name', '');
    const fMobile = Pg.input('Mobile', 'mobile', '');
    const pwWrap = Pg.input('Password', 'password', '');
    const pwInput = pwWrap.querySelector('input');
    pwInput.type = 'password';
    const pwBtns = U.el('div', null);
    const genBtn = U.el('button', { class: 'btn btn-outline btn-sm', type: 'button', text: 'Generate' });
    const showBtn = U.el('button', { class: 'btn btn-outline btn-sm', type: 'button', text: 'Show' });
    genBtn.addEventListener('click', () => { pwInput.value = this.genPassword(); });
    showBtn.addEventListener('click', () => { pwInput.type = pwInput.type === 'password' ? 'text' : 'password'; });
    pwBtns.appendChild(genBtn);
    pwBtns.appendChild(showBtn);
    pwWrap.appendChild(pwBtns);
    const fVillage = Pg.input('Village', 'village', '');
    const fDistrict = Pg.select('District', 'district_id', '', districts.map((d) => ({ value: String(d.id), label: d.name })));
    const fLand = Pg.input('Land (acres)', 'land_area_acres', '');
    const fAltMobile = Pg.input('Alternative mobile', 'alternative_mobile', '');
    [fName, fMobile, pwWrap, fVillage, fDistrict, fLand, fAltMobile].forEach((el) => grid.appendChild(el));
    body.appendChild(grid);
    body.appendChild(U.el('p', { text: 'The farmer will be able to login in the mobile app using their mobile number and this password.', style: 'font-size:12px;color:var(--text-light);margin-top:8px' }));
    const okBtn = U.el('button', { class: 'btn btn-primary', text: Locale.t('save') });
    const modal = openModal({ title: 'New Farmer', size: 'lg', body, footer: [okBtn] });
    okBtn.addEventListener('click', async () => {
      clearFieldErrors(body);
      const payload = {
        name: fName.querySelector('input').value.trim(),
        mobile: fMobile.querySelector('input').value.trim(),
        password: pwInput.value,
        village: fVillage.querySelector('input').value.trim(),
        district_id: parseInt(fDistrict.querySelector('select').value, 10),
      };
      const land = fLand.querySelector('input').value.trim();
      if (land !== '') payload.land_area_acres = parseFloat(land);
      const alt = fAltMobile.querySelector('input').value.trim();
      if (alt !== '') payload.alternative_mobile = alt;
      if (!payload.name) { fieldError(fName.querySelector('input'), Locale.t('errorRequired')); return; }
      if (!/^[6-9]\d{9}$/.test(payload.mobile)) { fieldError(fMobile.querySelector('input'), 'A valid 10-digit mobile number is required'); return; }
      if (!payload.password) { fieldError(pwInput, Locale.t('errorRequired')); return; }
      if (!payload.village) { fieldError(fVillage.querySelector('input'), Locale.t('errorRequired')); return; }
      if (!payload.district_id) { fieldError(fDistrict.querySelector('select'), Locale.t('errorRequired')); return; }
      okBtn.disabled = true;
      const res = await Api.post('/api/v1/admin/farmers', payload, { handleMaintenance: false });
      if (!res.ok) { okBtn.disabled = false; applyApiErrors(body, res); return; }
      const data = res.data && res.data.data ? res.data.data : {};
      const creds = data.credentials || {};
      toast('Farmer created', 'success');
      modal.close();
      this.credentialsModal('Farmer Created', creds.mobile || payload.mobile, creds.password || payload.password);
      this.render(this.host);
    });
  },

  async openPassword(u) {
    if (!Auth.can('manage_farmers')) { toast('Permission denied', 'error'); return; }
    const body = U.el('div', null);
    body.appendChild(U.el('p', { text: 'Set a new password for ' + u.name + '. The farmer logs in with mobile number and this password.', style: 'margin-bottom:12px' }));
    const pwWrap = Pg.input('New password', 'password', '');
    const pwInput = pwWrap.querySelector('input');
    pwInput.type = 'password';
    const pwBtns = U.el('div', null);
    const genBtn = U.el('button', { class: 'btn btn-outline btn-sm', type: 'button', text: 'Generate' });
    const showBtn = U.el('button', { class: 'btn btn-outline btn-sm', type: 'button', text: 'Show' });
    genBtn.addEventListener('click', () => { pwInput.value = this.genPassword(); });
    showBtn.addEventListener('click', () => { pwInput.type = pwInput.type === 'password' ? 'text' : 'password'; });
    pwBtns.appendChild(genBtn);
    pwBtns.appendChild(showBtn);
    pwWrap.appendChild(pwBtns);
    body.appendChild(pwWrap);
    const okBtn = U.el('button', { class: 'btn btn-primary', text: Locale.t('save') });
    const modal = openModal({ title: 'Reset Farmer Password', size: 'sm', body, footer: [okBtn] });
    okBtn.addEventListener('click', async () => {
      clearFieldErrors(body);
      const password = pwInput.value;
      if (!password) { fieldError(pwInput, Locale.t('errorRequired')); return; }
      okBtn.disabled = true;
      const res = await Api.put('/api/v1/admin/farmers/' + u.id + '/password', { password }, { handleMaintenance: false });
      if (!res.ok) { okBtn.disabled = false; applyApiErrors(body, res); return; }
      const data = res.data && res.data.data ? res.data.data : {};
      const creds = data.credentials || {};
      toast('Password updated', 'success');
      modal.close();
      this.credentialsModal('Password Updated', creds.mobile || u.mobile, creds.password || password);
    });
  },

  async setStatus(u) {
    if (!Auth.can('manage_farmers')) { toast('Permission denied', 'error'); return; }
    const body = U.el('div', null);
    body.appendChild(U.el('p', { text: 'Status for ' + u.name, style: 'margin-bottom:12px' }));
    const sel = Pg.select('Status', 'status', u.status, ['ACTIVE', 'INACTIVE', 'LOCKED'].map((s) => ({ value: s, label: s })));
    body.appendChild(sel);
    const modal = openModal({ title: 'Change Status', size: 'sm', body });
    modal.footer.appendChild(U.el('button', { class: 'btn btn-primary', text: Locale.t('save'), onclick: async () => {
      const res = await Api.put('/api/v1/admin/farmers/' + u.id + '/status', { status: sel.querySelector('select').value }, { handleMaintenance: false });
      if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
      toast('Updated', 'success');
      modal.close();
      this.render(this.host);
    } }));
  },

  async paint(host, list, pg) {
    const items = (list && list.data) || (list && list.items) || [];
    host.innerHTML = '';
    const toolbar = U.el('div', { class: 'toolbar' });
    const qI = U.el('input', { placeholder: Locale.t('search'), value: this.state.q, style: 'width:auto' });
    toolbar.appendChild(qI);
    const statuses = ['', 'ACTIVE', 'INACTIVE', 'LOCKED', 'PENDING'];
    const sel = Pg.select('', 'status', this.state.status, statuses.map((s) => ({ value: s, label: s || 'All' })));
    toolbar.appendChild(sel);
    toolbar.appendChild(U.el('button', { class: 'btn btn-primary', text: Locale.t('filter'), onclick: () => {
      this.state.q = qI.value.trim();
      this.state.status = sel.querySelector('select').value;
      this.state.page = 1;
      this.load(host);
    } }));
    toolbar.appendChild(U.el('div', { class: 'spacer' }));
    if (Auth.can('manage_farmers')) toolbar.appendChild(U.el('button', { class: 'btn btn-success', text: '+ Farmer', onclick: () => this.openForm() }));
    toolbar.appendChild(U.el('button', { class: 'btn btn-outline', text: 'CSV', onclick: () => Pg.exportCsv('farmers.csv', ['Name', 'Mobile', 'Village', 'District', 'Status', 'Created'], items.map((s) => [s.name, s.mobile, s.village, s.district && s.district.name, s.status, s.created_at])) }));
    host.appendChild(toolbar);
    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Farmers' })]));
    const pbody = U.el('div', { class: 'panel-body no-pad' });
    if (!items.length) {
      pbody.appendChild(U.el('div', { class: 'empty-state', text: Locale.t('noData') }));
    } else {
      pbody.appendChild(renderTable({
        columns: [
          { title: 'Name', render: (r) => U.esc(r.name) },
          { title: 'Mobile', render: (r) => U.esc(r.mobile) },
          { title: 'Village', render: (r) => U.esc(r.village || '') },
          { title: 'District', render: (r) => U.esc((r.district && r.district.name) || '') },
          { title: 'Status', render: (r) => Pg.badge(r.status) },
          { title: 'Created', render: (r) => U.esc(U.fmtDate(r.created_at)) },
          { title: '', render: (r) => {
            const span = U.el('span', { style: 'display:inline-flex;gap:6px' });
            span.appendChild(Pg.actionBtn(Locale.t('view'), 'btn-outline', () => this.showDetail(r.id)));
            if (Auth.can('manage_farmers')) span.appendChild(Pg.actionBtn('Password', 'btn-outline', () => this.openPassword(r)));
            if (Auth.can('manage_farmers')) span.appendChild(Pg.actionBtn('Status', 'btn-outline', () => this.setStatus(r)));
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