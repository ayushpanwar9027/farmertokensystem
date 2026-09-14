'use strict';

const CentresPage = {
  state: { q: '', districtId: '', status: '', page: 1 },

  async fetchList() {
    const q = U.qsFrom({ q: this.state.q || undefined, district_id: this.state.districtId || undefined, status: this.state.status || undefined, page: this.state.page, per_page: FPS.pageSize });
    const res = await Api.get('/api/v1/centres' + q, { handleMaintenance: false });
    if (!res.ok) return null;
    const d = Api.unwrap(res.data);
    return d;
  },

  async showDetail(id) {
    const res = await Api.get('/api/v1/admin/centres/' + id, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    const d = Api.unwrap(res.data);
    const c = d && (d.centre || d);
    const grid = U.el('div', { class: 'detail-grid' });
    [
      ['Name', c.name],
      ['Code', c.code],
      ['District', c.district ? c.district.name : ''],
      ['Status', Pg.badge(c.status)],
      ['Address', c.address],
      ['Phone', c.contact_phone],
      ['Email', c.contact_email],
      ['Capacity', c.daily_capacity],
      ['Slot (min)', c.slot_duration_minutes],
      ['Hours', c.working_hours ? (c.working_hours.start + '-' + c.working_hours.end) : ''],
    ].forEach(([k, v]) => grid.appendChild(U.el('div', { class: 'detail-item' }, [U.el('div', { class: 'k', text: k }), U.el('div', { class: 'v', text: U.esc(v !== null && v !== undefined ? v : '-') })])));
    if (c.days) {
      grid.appendChild(U.el('div', { class: 'detail-item' }, [U.el('div', { class: 'k', text: 'Days' }), U.el('div', { class: 'v', text: U.esc(c.days.join(', ')) })]));
    }
    if (c.location && (c.location.latitude || c.location.longitude)) {
      grid.appendChild(U.el('div', { class: 'detail-item' }, [U.el('div', { class: 'k', text: 'Location' }), U.el('div', { class: 'v', text: String(c.location.latitude || '') + ', ' + String(c.location.longitude || '') })]));
    }
    if (c.manager) {
      grid.appendChild(U.el('div', { class: 'detail-item' }, [U.el('div', { class: 'k', text: 'Manager' }), U.el('div', { class: 'v', text: U.esc(c.manager.name) })]));
    }
    openModal({ title: 'Centre Detail', size: 'lg', body: grid });
  },

  async openForm(c) {
    if (!Auth.can('centres.manage')) { toast('Permission denied', 'error'); return; }
    const districts = await Api.get('/api/v1/districts');
    const districtOptions = districts.ok ? (Api.unwrap(districts.data).districts || []).map((o) => ({ value: String(o.id), label: o.name })) : [];
    const body = U.el('div', null);
    const grid = U.el('div', { class: 'form-grid' });
    const fName = Pg.input('Name', 'name', c ? c.name : '');
    const fCode = Pg.input('Code', 'code', c ? c.code : '');
    const fDistrict = Pg.select('District', 'district_id', c && c.district_id ? String(c.district_id) : '', districtOptions);
    const fPhone = Pg.input('Phone', 'contact_phone', c ? c.contact_phone : '');
    const fEmail = Pg.input('Email', 'contact_email', c ? c.contact_email : '');
    const fAddress = Pg.textarea('Address', 'address', c ? c.address : '');
    const fCapacity = Pg.input('Daily capacity', 'daily_capacity', c ? c.daily_capacity : '');
    const fDuration = Pg.input('Slot duration (min)', 'slot_duration_minutes', c ? c.slot_duration_minutes : '15');
    const fStart = Pg.input('Open (HH:MM)', 'wh_start', c && c.working_hours ? c.working_hours.start : '08:00');
    const fEnd = Pg.input('Close (HH:MM)', 'wh_end', c && c.working_hours ? c.working_hours.end : '17:00');
    [fName, fCode, fDistrict, fPhone, fEmail, fAddress, fCapacity, fDuration, fStart, fEnd].forEach((el) => grid.appendChild(el));
    body.appendChild(grid);
    const okBtn = U.el('button', { class: 'btn btn-primary', text: Locale.t('save') });
    const modal = openModal({ title: c ? 'Edit Centre' : 'New Centre', size: 'lg', body, footer: [okBtn] });
    okBtn.addEventListener('click', async () => {
      clearFieldErrors(body);
      const payload = {
        name: fName.querySelector('input').value.trim(),
        code: fCode.querySelector('input').value.trim().toUpperCase(),
        district_id: parseInt(fDistrict.querySelector('select').value, 10),
        contact_phone: fPhone.querySelector('input').value.trim(),
        contact_email: fEmail.querySelector('input').value.trim(),
        address: fAddress.querySelector('textarea').value.trim(),
        daily_capacity: parseInt(fCapacity.querySelector('input').value, 10) || 0,
        slot_duration_minutes: parseInt(fDuration.querySelector('input').value, 10) || 15,
        working_hours: { start: fStart.querySelector('input').value, end: fEnd.querySelector('input').value },
      };
      if (!payload.name) { fieldError(fName.querySelector('input'), Locale.t('errorRequired')); return; }
      if (!payload.code) { fieldError(fCode.querySelector('input'), Locale.t('errorRequired')); return; }
      okBtn.disabled = true;
      const res = c
        ? await Api.put('/api/v1/admin/centres/' + c.id, payload, { handleMaintenance: false })
        : await Api.post('/api/v1/admin/centres', payload, { handleMaintenance: false });
      if (!res.ok) { okBtn.disabled = false; applyApiErrors(body, res); return; }
      toast('Saved', 'success');
      modal.close();
      this.render(this.host);
    });
  },

  async setStatus(c) {
    const options = [
      { value: 'ACTIVE', label: 'Active' },
      { value: 'INACTIVE', label: 'Inactive' },
      { value: 'CLOSED', label: 'Closed' },
    ];
    const body = U.el('div', null);
    body.appendChild(U.el('p', { text: 'New status for ' + c.name, style: 'margin-bottom:12px' }));
    const sel = Pg.select('Status', 'status', c.status, options);
    body.appendChild(sel);
    const modal = openModal({ title: 'Change Status', size: 'sm', body });
    modal.footer.appendChild(U.el('button', { class: 'btn btn-primary', text: Locale.t('save'), onclick: async () => {
      const res = await Api.post('/api/v1/admin/centres/' + c.id + '/status', { status: sel.querySelector('select').value }, { handleMaintenance: false });
      if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
      toast('Updated', 'success');
      modal.close();
      this.render(this.host);
    } }));
  },

  paint(host, list, pg) {
    const items = (list && list.data) || (list && Array.isArray(list) ? list : []);
    host.innerHTML = '';
    const toolbar = U.el('div', { class: 'toolbar' });
    const qI = U.el('input', { placeholder: Locale.t('search'), value: this.state.q, style: 'width:auto' });
    toolbar.appendChild(qI);
    const statuses = ['', 'ACTIVE', 'INACTIVE', 'CLOSED'];
    const sel = Pg.select('', 'status', this.state.status, statuses.map((s) => ({ value: s, label: s || 'All' })));
    toolbar.appendChild(sel);
    toolbar.appendChild(U.el('button', { class: 'btn btn-primary', text: Locale.t('filter'), onclick: () => {
      this.state.q = qI.value.trim();
      this.state.status = sel.querySelector('select').value;
      this.state.page = 1;
      this.load(host);
    } }));
    toolbar.appendChild(U.el('div', { class: 'spacer' }));
    if (Auth.can('centres.manage')) toolbar.appendChild(U.el('button', { class: 'btn btn-success', text: '+ New', onclick: () => this.openForm(null) }));
    toolbar.appendChild(U.el('button', { class: 'btn btn-outline', text: 'CSV', onclick: () => Pg.exportCsv('centres.csv', ['Name', 'Code', 'District', 'Status', 'Phone'], items.map((c) => [c.name, c.code, c.district ? c.district.name : '', c.status, c.contact_phone])) }));
    host.appendChild(toolbar);
    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Centres' })]));
    const pbody = U.el('div', { class: 'panel-body no-pad' });
    if (!items.length) {
      pbody.appendChild(U.el('div', { class: 'empty-state', text: Locale.t('noData') }));
    } else {
      pbody.appendChild(renderTable({
        columns: [
          { title: 'Name', render: (r) => U.esc(r.name) },
          { title: 'Code', render: (r) => U.esc(r.code) },
          { title: 'District', render: (r) => (r.district ? U.esc(r.district.name) : '') },
          { title: 'Status', render: (r) => Pg.badge(r.status) },
          { title: 'Phone', render: (r) => U.esc(r.contact_phone || '') },
          { title: '', render: (r) => {
            const span = U.el('span', { style: 'display:inline-flex;gap:6px' });
            span.appendChild(Pg.actionBtn(Locale.t('view'), 'btn-outline', () => this.showDetail(r.id)));
            if (Auth.can('centres.manage')) {
              span.appendChild(Pg.actionBtn('Edit', 'btn-outline', () => this.openForm(r)));
            }
            if (Auth.can('centres.status.manage')) span.appendChild(Pg.actionBtn('Status', 'btn-outline', () => this.setStatus(r)));
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