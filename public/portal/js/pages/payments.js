'use strict';

const PaymentsPage = {
  state: { status: '', date: '', q: '', page: 1 },

  async fetchList() {
    const q = U.qsFrom({ status: this.state.status || undefined, date: this.state.date || undefined, q: this.state.q || undefined, page: this.state.page, per_page: FPS.pageSize });
    const res = await Api.get('/api/v1/operator/payments' + q, { handleMaintenance: false });
    if (!res.ok) return null;
    const d = Api.unwrap(res.data);
    if (Array.isArray(d)) return { items: d };
    return d;
  },

  showDetail(id) {
    Api.get('/api/v1/operator/payments/' + id, { handleMaintenance: false }).then((res) => {
      if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
      const d = Api.unwrap(res.data);
      const p = d && (d.payment || d);
      if (!p) return;
      const grid = U.el('div', { class: 'detail-grid' });
      [
        ['Payment #', p.procurement_number],
        ['Farmer', p.farmer ? p.farmer.name : ''],
        ['Mobile', p.farmer ? p.farmer.mobile : ''],
        ['Crop', p.crop_name],
        ['Weight', p.accepted_weight],
        ['Rate', U.fmtMoney(p.rate_per_kg)],
        ['Amount', U.fmtMoney(p.amount)],
        ['Status', Pg.badge(p.status)],
        ['Method', p.payment_method],
        ['Reference', p.payment_reference],
        ['Centre', p.centre_name],
        ['Created', U.fmtDateTime(p.created_at)],
        ['Released', p.released_at ? U.fmtDateTime(p.released_at) : ''],
      ].forEach(([k, v]) => {
        grid.appendChild(U.el('div', { class: 'detail-item' }, [U.el('div', { class: 'k', text: k }), U.el('div', { class: 'v', html: typeof v === 'string' && v.startsWith('<') ? v : U.esc(v) })]));
      });
      openModal({ title: 'Payment Detail', body: grid });
    });
  },

  canRelease() {
    if (!Auth.can('payments.release')) return false;
    if (!Auth.user) return false;
    const r = String(Auth.user.role || '').toUpperCase();
    return ['CENTRE_MANAGER', 'DISTRICT_ADMIN', 'SUPER_ADMIN'].includes(r);
  },

  async release(p) {
    if (!this.canRelease()) { toast('Permission denied', 'error'); return; }
    const body = U.el('div', null, [
      U.el('p', { text: 'Release ' + U.fmtMoney(p.amount) + ' to ' + (p.farmer ? p.farmer.name : '') + '?', style: 'margin-bottom:12px' }),
    ]);
    const grid = U.el('div', { class: 'form-grid' });
    const fMethod = Pg.select('Method', 'method', 'UPI', ['upi', 'bank_transfer', 'cash'].map((m) => ({ value: m, label: m })));
    const fRef = Pg.input('Reference', 'payment_reference', '');
    grid.appendChild(fMethod);
    grid.appendChild(fRef);
    body.appendChild(grid);
    const okBtn = U.el('button', { class: 'btn btn-primary', text: 'Release' });
    const modal = openModal({ title: 'Release Payment', body, footer: [okBtn] });
    okBtn.addEventListener('click', async () => {
      okBtn.disabled = true;
      const res = await Api.put('/api/v1/operator/payments/' + p.id + '/release', { method: fMethod.querySelector('select').value, payment_reference: fRef.querySelector('input').value }, { handleMaintenance: false });
      if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); okBtn.disabled = false; return; }
      toast('Released', 'success');
      modal.close();
      this.render(this.host);
    });
  },

  async reverse(p) {
    if (!Auth.can('payments.reverse')) { toast('Permission denied', 'error'); return; }
    const want = await confirmDialog('Reverse payment ' + p.procurement_number + '?', { danger: true });
    if (!want) return;
    const res = await Api.post('/api/v1/admin/payments/' + p.id + '/reverse', {}, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    toast('Payment reversed', 'success');
    this.render(this.host);
  },

  paint(host, list, pg) {
    const items = Pg.itemsOf(list);
    host.innerHTML = '';
    const toolbar = U.el('div', { class: 'toolbar' });
    const statuses = ['', 'PENDING', 'INITIATED', 'RELEASED', 'REVERSED', 'CANCELLED'];
    const sel = Pg.select('', 'status', this.state.status, statuses.map((s) => ({ value: s, label: s || 'All' })));
    toolbar.appendChild(sel);
    const dateInput = U.el('input', { type: 'date', value: this.state.date, style: 'width:auto' });
    toolbar.appendChild(dateInput);
    const qInput = U.el('input', { placeholder: Locale.t('search'), value: this.state.q, style: 'width:auto' });
    toolbar.appendChild(qInput);
    toolbar.appendChild(U.el('button', { class: 'btn btn-primary', text: Locale.t('filter'), onclick: async () => {
      this.state.status = sel.querySelector('select').value;
      this.state.date = dateInput.value;
      this.state.q = qInput.value.trim();
      this.state.page = 1;
      this.load(host);
    } }));
    toolbar.appendChild(U.el('div', { class: 'spacer' }));
    toolbar.appendChild(U.el('button', { class: 'btn btn-outline', text: 'CSV', onclick: () => {
      Pg.exportCsv('payments.csv', ['#', 'Farmer', 'Crop', 'Weight', 'Rate', 'Amount', 'Status', 'Method', 'Released'], items.map((p) => [p.procurement_number, p.farmer ? p.farmer.name : '', p.crop_name, p.accepted_weight, p.rate_per_kg, p.amount, p.status, p.payment_method, p.released_at]));
    } }));
    host.appendChild(toolbar);

    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Payments' })]));
    const pbody = U.el('div', { class: 'panel-body no-pad' });
    if (!items.length) {
      pbody.appendChild(U.el('div', { class: 'empty-state', text: Locale.t('noData') }));
    } else {
      pbody.appendChild(renderTable({
        columns: [
          { title: '#', render: (r) => U.esc(r.procurement_number) },
          { title: 'Farmer', render: (r) => (r.farmer ? U.esc(r.farmer.name) : '') },
          { title: 'Mobile', render: (r) => (r.farmer ? U.esc(r.farmer.mobile) : '') },
          { title: 'Crop', render: (r) => U.esc(r.crop_name || '') },
          { title: 'Weight (kg)', render: (r) => String(r.accepted_weight || 0) },
          { title: 'Amount', render: (r) => U.fmtMoney(r.amount) },
          { title: 'Status', render: (r) => Pg.badge(r.status) },
          { title: 'Method', render: (r) => U.esc(r.payment_method || '') },
          { title: '', render: (r) => {
            const span = U.el('span', { style: 'display:inline-flex;gap:6px' });
            span.appendChild(Pg.actionBtn(Locale.t('view'), 'btn-outline', () => this.showDetail(r.id)));
            if (this.canRelease() && r.status === 'PENDING') span.appendChild(Pg.actionBtn('Release', 'btn-primary', () => this.release(r)));
            if (Auth.can('payments.reverse') && ['PENDING', 'INITIATED', 'RELEASED'].includes(r.status)) span.appendChild(Pg.actionBtn('Reverse', 'btn-danger', () => this.reverse(r)));
            return span;
          } },
        ],
        rows: items,
      }));
    }
    panel.appendChild(pbody);
    if (pg) {
      panel.appendChild(renderPagination(pg, (n) => { this.state.page = n; this.load(host); }));
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