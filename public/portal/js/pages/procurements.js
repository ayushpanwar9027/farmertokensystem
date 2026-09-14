'use strict';

const ProcurementsPage = {
  state: { status: '', date: '', q: '', page: 1 },

  async fetchList() {
    const q = U.qsFrom({ status: this.state.status || undefined, date: this.state.date || undefined, q: this.state.q || undefined, page: this.state.page, per_page: FPS.pageSize });
    const res = await Api.get('/api/v1/operator/procurements' + q, { handleMaintenance: false });
    if (!res.ok) return null;
    const d = Api.unwrap(res.data);
    return d;
  },

  async detail(id) {
    const res = await Api.get('/api/v1/operator/procurements/' + id, { handleMaintenance: false });
    return res.ok ? Api.unwrap(res.data) : null;
  },

  async showDetail(id) {
    const res = await Api.get('/api/v1/operator/procurements/' + id, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    const d = Api.unwrap(res.data);
    const p = d && (d.procurement || d);
    if (!p) return;
    const items = [
      ['Procurement #', p.procurement_number],
      ['Booking', p.booking_number],
      ['Farmer', p.farmer ? p.farmer.name : ''],
      ['Mobile', p.farmer ? p.farmer.mobile : ''],
      ['Crop', p.crop_name],
      ['Variety', p.variety],
      ['Booked Qty', (p.booked_qty || 0) + ' kg'],
      ['Accepted', (p.accepted_weight || 0) + ' kg'],
      ['Damage', (p.damaged_qty || 0) + ' kg'],
      ['Grade', p.grade],
      ['Moisture', (p.moisture_pct || 0) + '%'],
      ['Status', p.status],
      ['Rejection', p.rejection_reason],
      ['Amount', U.fmtMoney(p.approved_amount)],
      ['Centre', p.centre_name],
    ];
    const gallery = U.el('div', { class: 'detail-grid' });
    if (p.photos && p.photos.length) {
      gallery.appendChild(U.el('div', { class: 'panel-title', style: 'grid-column:1/-1;margin-top:10px', text: 'Photos' }));
      p.photos.forEach((ph) => {
        const src = ph.data_url || ph.url;
        const a = U.el('a', { href: src, target: '_blank' });
        const img = U.el('img', { src, style: 'width:100%;max-width:200px;border-radius:8px;display:block', alt: 'photo' });
        a.appendChild(img);
        gallery.appendChild(a);
      });
    }
    const grid = U.el('div', { class: 'detail-grid' });
    items.forEach(([k, v]) => {
      grid.appendChild(U.el('div', { class: 'detail-item' }, [U.el('div', { class: 'k', text: k }), U.el('div', { class: 'v', text: String(v === null || v === undefined ? '' : v) })]));
    });
    if (gallery.childNodes.length) gallery.appendChild(gallery.firstChild && gallery);
    openModal({
      title: 'Procurement Detail',
      size: 'lg',
      body: U.el('div', null, [grid, gallery]),
      footer: [],
    });
  },

  async edit(id) {
    const res = await Api.get('/api/v1/operator/procurements/' + id, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    const d = Api.unwrap(res.data);
    const p = d && (d.procurement || d);
    const body = U.el('div', null, [
      U.el('p', { text: 'Capture weight & QC for ' + (p.procurement_number || '') + ' (' + (p.crop_name || '') + ')', style: 'margin-bottom:12px;color:var(--text-light)' }),
    ]);
    const grid = U.el('div', { class: 'form-grid' });
    const fWeight = Pg.input('Accepted weight (kg)', 'accepted_weight', '');
    const fDamage = Pg.input('Damaged qty (kg)', 'damaged_qty', '');
    const fGrade = Pg.select('Grade', 'grade', '', ['A', 'B', 'C', 'REJECT'].map((g) => ({ value: g, label: g })));
    const fMoisture = Pg.input('Moisture %', 'moisture_pct', '');
    const fSkills = Pg.textarea('Quality notes', 'quality_notes', '');
    const fOpNote = Pg.textarea('Operator note', 'operator_note', '');
    [fWeight, fDamage, fGrade, fMoisture, fSkills, fOpNote].forEach((el) => grid.appendChild(el));
    body.appendChild(grid);
    const errors = U.el('div', { style: 'margin-top:10px' });
    body.appendChild(errors);
    const submitBtn = U.el('button', { class: 'btn btn-primary', text: 'Save' });
    const modalOpts = openModal({
      title: 'Capture Data',
      size: 'lg',
      body,
      footer: [submitBtn],
    });
    submitBtn.addEventListener('click', async () => {
      clearFieldErrors(body);
      const payload = {
        accepted_weight: parseFloat(fWeight.querySelector('input').value),
        damaged_qty: parseFloat(fDamage.querySelector('input').value) || 0,
        grade: fGrade.querySelector('select').value,
        moisture_pct: parseFloat(fMoisture.querySelector('input').value) || 0,
        quality_notes: fSkills.querySelector('textarea').value || '',
        operator_note: fOpNote.querySelector('textarea').value || '',
      };
      if (isNaN(payload.accepted_weight)) { fieldError(fWeight.querySelector('input'), 'Required'); return; }
      submitBtn.disabled = true;
      const upd = await Api.put('/api/v1/operator/procurements/' + id, payload, { handleMaintenance: false });
      if (!upd.ok) {
        submitBtn.disabled = false;
        applyApiErrors(body, upd);
        return;
      }
      toast('Saved', 'success');
      modalOpts.close();
      this.render(this.host);
    });
  },

  async submit(id) {
    const res = await Api.post('/api/v1/operator/procurements/' + id + '/submit', {}, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    toast('Submitted for approval', 'success');
    this.render(this.host);
  },

  async reject(id) {
    const modal = openModal({
      title: 'Reject Procurement',
      size: 'sm',
      body: (() => { const w = U.el('div'); w.appendChild(Pg.textarea('Reason', 'reason', '')); return w; })(),
      footer: [],
    });
    const reasonEl = modal.overlay.querySelector('textarea');
    const confirmBtn = U.el('button', { class: 'btn btn-danger', text: 'Reject' });
    modal.footer.appendChild(confirmBtn);
    confirmBtn.addEventListener('click', async () => {
      const reason = reasonEl.value.trim();
      if (!reason) { toast('Reason required', 'warn'); return; }
      confirmBtn.disabled = true;
      const res = await Api.post('/api/v1/operator/procurements/' + id + '/reject', { reason }, { handleMaintenance: false });
      if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); confirmBtn.disabled = false; return; }
      toast('Rejected', 'success');
      modal.close();
      this.render(this.host);
    });
  },

  paint(host, list, pg) {
    const items = list && list.items ? list.items : Array.isArray(list) ? list : [];
    host.innerHTML = '';
    const toolbar = U.el('div', { class: 'toolbar' });
    const statuses = ['', 'PENDING', 'PENDING_APPROVAL', 'VERIFIED', 'REJECTED'];
    toolbar.appendChild(Pg.select('', 'status', this.state.status, statuses.map((s) => ({ value: s, label: s || 'All' }))));
    const dateInput = U.el('input', { type: 'date', value: this.state.date, style: 'width:auto' });
    toolbar.appendChild(dateInput);
    const qInput = U.el('input', { placeholder: Locale.t('search'), value: this.state.q, style: 'width:auto' });
    toolbar.appendChild(qInput);
    const applyBtn = U.el('button', { class: 'btn btn-primary', text: Locale.t('filter'), onclick: async () => {
      this.state.status = toolbar.querySelector('select').value;
      this.state.date = dateInput.value;
      this.state.q = qInput.value.trim();
      this.state.page = 1;
      await this.load(host);
    } });
    toolbar.appendChild(applyBtn);
    toolbar.appendChild(U.el('div', { class: 'spacer' }));
    const expBtn = U.el('button', { class: 'btn btn-outline', text: 'CSV', onclick: () => {
      Pg.exportCsv('procurements.csv', ['#', 'Booking', 'Farmer', 'Crop', 'Accepted', 'Grade', 'Amount', 'Status'], items.map((p) => [p.procurement_number, p.booking_number, p.farmer ? p.farmer.name : '', p.crop_name, p.accepted_weight, p.grade, p.approved_amount, p.status]));
    } });
    toolbar.appendChild(expBtn);
    host.appendChild(toolbar);

    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Procurements' })]));
    const pbody = U.el('div', { class: 'panel-body no-pad' });
    if (!items.length) {
      pbody.appendChild(U.el('div', { class: 'empty-state', text: Locale.t('noData') }));
    } else {
      const table = renderTable({
        columns: [
          { title: '#', render: (r) => U.esc(r.procurement_number) },
          { title: 'Farmer', render: (r) => (r.farmer ? U.esc(r.farmer.name) : '') },
          { title: 'Mobile', render: (r) => (r.farmer ? U.esc(r.farmer.mobile) : '') },
          { title: 'Crop', render: (r) => U.esc(r.crop_name || '') },
          { title: 'Accepted (kg)', render: (r) => String(r.accepted_weight || 0) },
          { title: 'Grade', render: (r) => U.esc(r.grade || '') },
          { title: 'Amount', render: (r) => U.fmtMoney(r.approved_amount) },
          { title: 'Status', render: (r) => Pg.badge(r.status) },
          { title: '', render: (r) => {
            const span = U.el('span', { style: 'display:inline-flex;gap:6px;flex-wrap:wrap' });
            span.appendChild(Pg.actionBtn(Locale.t('view'), 'btn-outline', () => this.showDetail(r.id)));
            if (Auth.can('procurements.update') && (r.status === 'PENDING')) {
              span.appendChild(Pg.actionBtn('Edit', 'btn-outline', () => this.edit(r.id)));
              span.appendChild(Pg.actionBtn('Submit', 'btn-primary', () => this.submit(r.id)));
              span.appendChild(Pg.actionBtn('Reject', 'btn-danger', () => this.reject(r.id)));
            }
            return span;
          } },
        ],
        rows: items,
      });
      pbody.appendChild(table);
    }
    panel.appendChild(pbody);
    if (pg) {
      const pFoot = U.el('div');
      pFoot.appendChild(renderPagination(pg, (n) => { this.state.page = n; this.load(host); }));
      panel.appendChild(pFoot);
    }
    host.appendChild(panel);
  },

  async load(host) {
    host.innerHTML = '';
    host.appendChild(Pg.loading());
    const list = await this.fetchList();
    if (!list) { host.innerHTML = ''; return; }
    const pg = Pg.pgFrom({ data: list });
    this.paint(host, list, pg);
  },

  render(host) {
    this.host = host;
    this.load(host);
    return { destroy: () => { this.host = null; } };
  },
};