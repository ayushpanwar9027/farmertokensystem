'use strict';

const ApprovalsPage = {
  state: { status: 'PENDING_APPROVAL', centreId: '', page: 1 },

  async fetchList() {
    const q = U.qsFrom({ status: this.state.status || undefined, centre_id: this.state.centreId || undefined });
    const res = await Api.get('/api/v1/admin/approvals' + q, { handleMaintenance: false });
    if (!res.ok) return null;
    return Api.unwrap(res.data);
  },

  showDetail(id) {
    Api.get('/api/v1/admin/approvals/' + id, { handleMaintenance: false }).then((res) => {
      if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
      const d = Api.unwrap(res.data);
      const p = d && d.approval ? d.approval : (d || {});
      const grid = U.el('div', { class: 'detail-grid' });
      [
        ['Proc #', p.procurement_number],
        ['Booking', p.booking_number],
        ['Farmer', p.farmer ? p.farmer.name : ''],
        ['Mobile', p.farmer ? p.farmer.mobile : ''],
        ['Crop', p.crop_name],
        ['Variety', p.variety],
        ['Booked (kg)', p.booked_qty],
        ['Accepted (kg)', p.accepted_weight],
        ['Damage (kg)', p.damaged_qty],
        ['Grade', p.grade],
        ['Moisture', p.moisture_pct],
        ['Amount', U.fmtMoney(p.approved_amount)],
        ['Status', Pg.badge(p.status)],
        ['Centre', p.centre_name],
        ['Operator note', p.operator_note || '-'],
        ['Quality notes', p.quality_notes || '-'],
        ['Rejection reason', p.rejection_reason || '-'],
      ].forEach(([k, v]) => grid.appendChild(U.el('div', { class: 'detail-item' }, [U.el('div', { class: 'k', text: k }), U.el('div', { class: 'v', html: typeof v === 'string' && v.startsWith('<') ? v : U.esc(v !== null && v !== undefined ? v : '-') })])));
      openModal({ title: 'Approval Detail', size: 'lg', body: grid });
    });
  },

  async decide(id, action) {
    if (action === 'reject') {
      const modal = openModal({
        title: 'Reject Procurement',
        size: 'sm',
        body: (() => { const w = U.el('div'); w.appendChild(Pg.textarea('Reason', 'reason', '')); return w; })(),
      });
      const reasonEl = modal.overlay.querySelector('textarea');
      const okBtn = U.el('button', { class: 'btn btn-danger', text: Locale.t('confirm') });
      modal.footer.appendChild(okBtn);
      okBtn.addEventListener('click', async () => {
        const reason = reasonEl.value.trim();
        if (!reason) { toast('Reason required', 'warn'); return; }
        okBtn.disabled = true;
        const res = await Api.post('/api/v1/admin/approvals/' + id + '/reject', { reason }, { handleMaintenance: false });
        if (!res.ok) { okBtn.disabled = false; applyApiErrors(modal.overlay, res); return; }
        toast('Rejected', 'success');
        modal.close();
        this.render(this.host);
      });
      return;
    }
    const want = await confirmDialog('Approve this procurement? The payment will be created.', {});
    if (!want) return;
    const res = await Api.post('/api/v1/admin/approvals/' + id + '/approve', {}, { handleMaintenance: false });
    if (!res.ok) { toast(Api.errorMessage(res.data), 'error'); return; }
    toast('Approved', 'success');
    this.render(this.host);
  },

  async paint(host, list) {
    const items = (list && list.items) || [];
    host.innerHTML = '';
    const toolbar = U.el('div', { class: 'toolbar' });
    const statuses = ['PENDING_APPROVAL', 'VERIFIED', 'REJECTED'];
    const sel = Pg.select('', 'status', this.state.status, statuses.map((s) => ({ value: s, label: s })));
    toolbar.appendChild(sel);
    toolbar.appendChild(U.el('button', { class: 'btn btn-primary', text: Locale.t('filter'), onclick: async () => {
      this.state.status = sel.querySelector('select').value;
      this.load(host);
    } }));
    toolbar.appendChild(U.el('div', { class: 'spacer' }));
    if (list && typeof list.count !== 'undefined') toolbar.appendChild(U.el('span', { text: list.count + ' records', style: 'font-size:13px;color:var(--text-light)' }));
    host.appendChild(toolbar);

    const panel = U.el('div', { class: 'panel' });
    panel.appendChild(U.el('div', { class: 'panel-head' }, [U.el('div', { class: 'panel-title', text: 'Approvals' })]));
    const pbody = U.el('div', { class: 'panel-body no-pad' });
    if (!items.length) {
      pbody.appendChild(U.el('div', { class: 'empty-state', text: Locale.t('noData') }));
    } else {
      pbody.appendChild(renderTable({
        columns: [
          { title: '#', render: (r) => U.esc(r.procurement_number) },
          { title: 'Farmer', render: (r) => (r.farmer ? U.esc(r.farmer.name) : '') },
          { title: 'Crop', render: (r) => U.esc(r.crop_name || '') },
          { title: 'Weight (kg)', render: (r) => String(r.accepted_weight || 0) },
          { title: 'Grade', render: (r) => U.esc(r.grade || '') },
          { title: 'Amount', render: (r) => U.fmtMoney(r.approved_amount) },
          { title: 'Status', render: (r) => Pg.badge(r.status) },
          { title: 'Centre', render: (r) => U.esc(r.centre_name || '') },
          { title: '', render: (r) => {
            const span = U.el('span', { style: 'display:inline-flex;gap:6px' });
            span.appendChild(Pg.actionBtn(Locale.t('view'), 'btn-outline', () => this.showDetail(r.id)));
            if (r.status === 'PENDING_APPROVAL') {
              span.appendChild(Pg.actionBtn('Approve', 'btn-success', () => this.decide(r.id, 'approve')));
              span.appendChild(Pg.actionBtn('Reject', 'btn-danger', () => this.decide(r.id, 'reject')));
            }
            return span;
          } },
        ],
        rows: items,
      }));
    }
    panel.appendChild(pbody);
    host.appendChild(panel);
  },

  async load(host) {
    host.innerHTML = '';
    host.appendChild(Pg.loading());
    const list = await this.fetchList();
    if (!list) { host.innerHTML = ''; return; }
    this.paint(host, list);
  },

  render(host) {
    this.host = host;
    this.load(host);
    return { destroy: () => { this.host = null; } };
  },
};